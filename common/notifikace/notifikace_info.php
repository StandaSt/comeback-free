<?php
declare(strict_types=1);
/* Účel: Uloží jednu obecnou zprávu všem příjemcům a doručí ji přes Web Push; trvalý klíč chrání před opakováním. */
require_once __DIR__ . '/notifikace_2fa.php';

/** Příjemci i obsah se nejdříve atomicky uloží. Chybějící zařízení a selhání transportu zůstávají v push_audit. */
function cb_info_posli_jednou(array $users, string $type, string $title, string $content, string $key, array $detail): array
{
    global $PROSTREDI;
    if (($PROSTREDI ?? '') !== 'SERVER') {
        throw new RuntimeException('Odesílání evidované notifikace je povoleno pouze na serveru.');
    }
    $users = array_values(array_unique(array_filter(array_map('intval', $users), static fn(int $id): bool => $id > 0)));
    if ($users === [] || $key === '' || trim($content) === '' || strlen($type) > 40) {
        throw new InvalidArgumentException('Neplatná evidovaná notifikace.');
    }
    $db = db();
    $lock = 'cb_info_' . substr(hash('sha256', $key), 0, 48);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->bind_param('s', $lock);
    $stmt->execute();
    if ((int)$stmt->get_result()->fetch_row()[0] !== 1) {
        throw new RuntimeException('Stejnou notifikaci právě zpracovává jiný běh.');
    }
    try {
        $stmt = $db->prepare("SELECT id_admin_info FROM admin_info WHERE typ=?
            AND JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(pozn), pozn, '{}'), '$.klic'))=? LIMIT 1");
        $stmt->bind_param('ss', $type, $key);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if ($existing) {
            // Již uloženou zprávu automaticky neposíláme znovu ani po přerušení předchozího procesu.
            return ['id_admin_info' => (int)$existing['id_admin_info'], 'opakovani' => true, 'odeslano' => 0, 'selhalo' => 0];
        }
        $note = json_encode(['klic' => $key, 'prijemci' => $users, 'detail' => $detail], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $db->begin_transaction();
        try {
            $stmt = $db->prepare('INSERT INTO admin_info (typ, nadpis, obsah, pozn, id_odeslal) VALUES (?, ?, ?, ?, NULL)');
            $stmt->bind_param('ssss', $type, $title, $content, $note);
            $stmt->execute();
            $id = (int)$db->insert_id;
            $tokens = [];
            foreach ($users as $user) {
                $token = bin2hex(random_bytes(32));
                $stmt = $db->prepare('INSERT INTO admin_info_user (id_admin_info, id_user, token) VALUES (?, ?, ?)');
                $stmt->bind_param('iis', $id, $user, $token);
                $stmt->execute();
                $tokens[$user] = $token;
            }
            $db->commit();
        } catch (Throwable $error) {
            $db->rollback();
            throw $error;
        }

        $transport = null;
        $transportError = '';
        try {
            if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT') || !cb_push_has_vendor()) {
                throw new RuntimeException('Chybí konfigurace nebo knihovna Web Push.');
            }
            require_once cb_push_vendor_autoload();
            $transport = new Minishlink\WebPush\WebPush(['VAPID' => [
                'subject' => (string)CB_VAPID_SUBJECT, 'publicKey' => (string)CB_VAPID_PUBLIC, 'privateKey' => (string)CB_VAPID_PRIVATE,
            ]]);
        } catch (Throwable $error) {
            $transportError = $error->getMessage();
        }
        $sent = 0;
        $failed = 0;
        foreach ($tokens as $user => $token) {
            $delivered = false;
            try {
                $devices = cb_push_load_devices($user);
                if (!$devices || !$transport) {
                    cb_info_audit($db, $user, null, $type, 'fail', null,
                        $transportError !== '' ? $transportError : 'Příjemce nemá aktivní push zařízení.');
                } else {
                    // Úplný souhrn zůstává v uloženém detailu; náhled push může systém telefonu zkrátit.
                    $payload = json_encode([
                        'type' => 'ADMIN_INFO', 'title' => $title, 'body' => $content,
                        'url' => cb_push_url_abs(cb_module_url('provoz') . 'mobil/admin_info.php?t=' . rawurlencode($token)),
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    foreach ($devices as $device) {
                        $ok = false;
                        $status = null;
                        $reason = null;
                        try {
                            $subscription = Minishlink\WebPush\Subscription::create([
                                'endpoint' => $device['endpoint'], 'publicKey' => $device['klic_public'], 'authToken' => $device['klic_auth'],
                            ]);
                            $report = $transport->sendOneNotification($subscription, $payload);
                            $ok = $report && $report->isSuccess();
                            $status = $report && $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
                            $reason = $ok ? null : ($report ? $report->getReason() : 'Push: bez reportu');
                        } catch (Throwable $error) {
                            $reason = $error->getMessage();
                        }
                        cb_info_audit($db, $user, (int)$device['id'], $type, $ok ? 'ok' : 'fail', $status, $reason);
                        $delivered = $delivered || $ok;
                    }
                }
            } catch (Throwable $error) {
                // Chyba jednoho příjemce nesmí zabránit pokusu o doručení ostatním.
                cb_info_audit($db, $user, null, $type, 'fail', null, $error->getMessage());
            }
            if ($delivered) {
                $stmt = $db->prepare('UPDATE admin_info_user SET odeslano=NOW() WHERE id_admin_info=? AND id_user=?');
                $stmt->bind_param('ii', $id, $user);
                $stmt->execute();
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['id_admin_info' => $id, 'opakovani' => false, 'odeslano' => $sent, 'selhalo' => $failed];
    } finally {
        $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->bind_param('s', $lock);
        $stmt->execute();
    }
}

/** Povinný audit této odesílací cesty nesmí tiše zahodit chybu zápisu jako nepovinný audit přihlášení. */
function cb_info_audit(mysqli $db, int $user, ?int $device, string $type, string $state, ?int $status, ?string $error): void
{
    $stmt = $db->prepare('INSERT INTO push_audit (id_user, id_zarizeni, typ, stav, http_status, chyba, vytvoreno)
        VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->bind_param('iissis', $user, $device, $type, $state, $status, $error);
    $stmt->execute();
}
