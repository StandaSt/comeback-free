<?php
// notifikace/notifikace_2fa.php * Verze: V2 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * Web Push � odesl�n� notifikace (server -> mobil)
 *
 * Pou�it�:
 * - require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
 * - cb_push_send_2fa($idUser, $token2fa);
 *
 * Z�vislosti:
 * - composer bal�k minishlink/web-push (vendor/autoload.php)
 * - tabulka push_zarizeni (subscription ulo�en� z mobilu)
 * - tabulka push_login_2fa (pro token a stav)
 *
 * Pozn�mky:
 * - log odesl�n� zapisuje do push_audit (pokud existuje)
 * - pokud nen� ��dn� aktivn� za��zen�, vr�t� false (bez chyby)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

function cb_push_has_vendor(): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    return is_file($autoload);
}

function cb_push_audit_try_insert(
    int $idUser,
    ?int $idZarizeni,
    string $typ,
    string $stav,
    ?int $httpStatus,
    ?string $chyba
): void {
    try {
        $conn = db();

        $stmt = $conn->prepare('
            INSERT INTO push_audit
            (id_user, id_zarizeni, typ, stav, http_status, chyba, vytvoreno)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');

        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'iissis',
            $idUser,
            $idZarizeni,
            $typ,
            $stav,
            $httpStatus,
            $chyba
        );

        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // audit nesm� shodit login
    }
}

function cb_push_load_devices(int $idUser): array
{
    $out = [];

    $conn = db();
    $stmt = $conn->prepare('
        SELECT id, endpoint, klic_public, klic_auth, nazev
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        ORDER BY id ASC
    ');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->bind_result($id, $endpoint, $klicPublic, $klicAuth, $nazev);

    while ($stmt->fetch()) {
        $out[] = [
            'id' => (int)$id,
            'endpoint' => (string)$endpoint,
            'klic_public' => (string)$klicPublic,
            'klic_auth' => (string)$klicAuth,
            'nazev' => is_string($nazev) ? (string)$nazev : null,
        ];
    }

    $stmt->close();

    return $out;
}

function cb_push_send_2fa(int $idUser, string $token2fa): bool
{
    if ($idUser <= 0 || $token2fa === '') {
        return false;
    }

    if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT')) {
        return false;
    }

    if (!cb_push_has_vendor()) {
        return false;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $devices = cb_push_load_devices($idUser);
    if (count($devices) === 0) {
        return false;
    }

    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);

    $url = cb_url_abs('mobil/mobil_overeni.php?t=' . rawurlencode($token2fa));
    $dbgToken = substr($token2fa, 0, 8);
    $dbgText = 'DBG: V2 | user ' . $idUser . ' | token ' . $dbgToken . ' | stav ceka';

    $payloadArr = [
        'type' => '2FA_LOGIN',
        'title' => 'Comeback',
        'body' => 'Schvalte p�ihl�en� do IS. ' . $dbgText,
        'url' => $url,
        'token' => $token2fa,
        'debug' => $dbgText,
    ];

    $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    foreach ($devices as $d) {

        $sub = Minishlink\WebPush\Subscription::create([
            'endpoint' => $d['endpoint'],
            'publicKey' => $d['klic_public'],
            'authToken' => $d['klic_auth'],
        ]);

        $report = $webPush->sendOneNotification($sub, $payload);

        $stav = 'ok';
        $httpStatus = null;
        $chyba = null;

        if ($report) {
            try {
                $ok = $report->isSuccess();
                if (!$ok) {
                    $stav = 'fail';
                }

                $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : null;
                if (is_int($code)) {
                    $httpStatus = $code;
                }

                if (!$ok) {
                    $reason = $report->getReason();
                    if (is_string($reason) && $reason !== '') {
                        $chyba = $reason;
                    } else {
                        $chyba = 'Push fail';
                    }
                }
            } catch (Throwable $e) {
                $stav = 'fail';
                $chyba = $e->getMessage();
            }
        } else {
            $stav = 'fail';
            $chyba = 'Push: bez reportu';
        }

        cb_push_audit_try_insert(
            $idUser,
            (int)$d['id'],
            '2fa_login',
            $stav,
            $httpStatus,
            $chyba
        );
    }

    return true;
}

// notifikace/notifikace_2fa.php * Verze: V2 * Aktualizace: 07.03.2026 * Po�et ��dk�: 206
// P�edchoz� po�et ��dk�: 202
// Konec souboru
