<?php
// notifikace/notifikace_2fa.php * Verze: V2 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * Web Push – odeslání notifikace (server -> mobil)
 *
 * Použití:
 * - require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
 * - cb_push_send_2fa($idUser, $token2fa);
 *
 * Závislosti:
 * - composer balík minishlink/web-push (vendor/autoload.php)
 * - tabulka push_zarizeni (subscription uložená z mobilu)
 * - tabulka push_login_2fa (pro token a stav)
 *
 * Poznámky:
 * - log odeslání zapisuje do push_audit (pokud existuje)
 * - pokud není žádné aktivní zařízení, vrátí false (bez chyby)
 */
function cb_push_has_vendor(): bool
{
    return cb_push_vendor_autoload() !== '';
}

function cb_push_vendor_autoload(): string
{
    $paths = [
        __DIR__ . '/../../vendor/autoload.php',
    ];

    foreach ($paths as $autoload) {
        if (is_file($autoload)) {
            return $autoload;
        }
    }

    return '';
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
        // audit nesmí shodit login
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

    require_once cb_push_vendor_autoload();

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

    $url = cb_module_url('is') . 'mobil/mobil_overeni.php?t=' . rawurlencode($token2fa);

    $payloadArr = [
        'type' => '2FA_LOGIN',
        'title' => 'Comeback',
        'body' => 'Schvalte přihlášení do IS.',
        'url' => $url,
        'token' => $token2fa,
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

function cb_push_send_bad_login_admin(string $email, ?string $ip, int $adminUserId = 1): bool
{
    if (function_exists('cb_push_system_enabled') && !cb_push_system_enabled('notif_bad_login')) {
        return false;
    }

    $adminUserId = (int)$adminUserId;
    if ($adminUserId <= 0) {
        return false;
    }

    if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT')) {
        return false;
    }

    if (!cb_push_has_vendor()) {
        return false;
    }

    require_once cb_push_vendor_autoload();

    $devices = cb_push_load_devices($adminUserId);
    if (count($devices) === 0) {
        return false;
    }

    $email = trim($email);
    if ($email === '') {
        $email = '---';
    }

    $ipText = is_string($ip) ? trim($ip) : '';
    if ($ipText === '') {
        $ipText = '---';
    }

    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);

    $payloadArr = [
        'type' => 'BAD_LOGIN_ADMIN',
        'title' => 'Comeback',
        'body' => 'Neúspěšný pokus o přihlášení: ' . $email . ' IP ' . $ipText,
        'url' => cb_url_abs(''),
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
                    $chyba = is_string($reason) && $reason !== '' ? $reason : 'Push fail';
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
            $adminUserId,
            (int)$d['id'],
            'bad_login_admin',
            $stav,
            $httpStatus,
            $chyba
        );
    }

    return true;
}

function cb_push_system_enabled(string $key): bool
{
    $key = trim($key);
    if (!in_array($key, ['notif_chyby', 'notif_bad_login'], true)) {
        return false;
    }

    try {
        if (function_exists('cb_system_setting') && (int)cb_system_setting($key, 0) === 1) {
            return true;
        }

        $conn = db();
        $sql = 'SELECT `' . $key . '` AS value_on FROM set_system WHERE id_set = 1 LIMIT 1';
        $res = $conn->query($sql);
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $res->free();
            return (int)($row['value_on'] ?? 0) === 1;
        }
    } catch (Throwable $e) {
        return false;
    }

    return false;
}

function cb_push_send_error_admin(string $message, ?string $file = null, ?int $line = null, int $adminUserId = 1): bool
{
    if (!cb_push_system_enabled('notif_chyby')) {
        return false;
    }

    $adminUserId = (int)$adminUserId;
    if ($adminUserId <= 0) {
        return false;
    }

    if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT')) {
        return false;
    }

    if (!cb_push_has_vendor()) {
        return false;
    }

    require_once cb_push_vendor_autoload();

    $devices = cb_push_load_devices($adminUserId);
    if (count($devices) === 0) {
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        $message = 'Neznámá chyba IS';
    }
    if (mb_strlen($message, 'UTF-8') > 140) {
        $message = mb_substr($message, 0, 137, 'UTF-8') . '...';
    }

    $fileText = trim((string)$file);
    $lineText = ($line !== null && $line > 0) ? ':' . (string)$line : '';

    $body = 'Chyba IS: ' . $message;
    if ($fileText !== '') {
        $body .= ' (' . basename($fileText) . $lineText . ')';
    }

    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);

    $payloadArr = [
        'type' => 'SYSTEM_ERROR_ADMIN',
        'title' => 'Comeback',
        'body' => $body,
        'url' => cb_url_abs(''),
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
                    $chyba = is_string($reason) && $reason !== '' ? $reason : 'Push fail';
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
            $adminUserId,
            (int)$d['id'],
            'system_error_admin',
            $stav,
            $httpStatus,
            $chyba
        );
    }

    return true;
}

function cb_push_send_first_entry_admin(string $fullName, int $adminUserId = 1): bool
{
    $adminUserId = (int)$adminUserId;
    if ($adminUserId <= 0) {
        return false;
    }

    if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT')) {
        return false;
    }

    if (!cb_push_has_vendor()) {
        return false;
    }

    require_once cb_push_vendor_autoload();

    $devices = cb_push_load_devices($adminUserId);
    if (count($devices) === 0) {
        return false;
    }

    $fullName = trim($fullName);
    if ($fullName === '') {
        $fullName = 'uživatel';
    }

    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);

    $payloadArr = [
        'type' => 'FIRST_ENTRY_ADMIN',
        'title' => 'Comeback',
        'body' => 'První vstup: ' . $fullName,
        'url' => cb_url_abs(''),
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
                    $chyba = is_string($reason) && $reason !== '' ? $reason : 'Push fail';
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
            $adminUserId,
            (int)$d['id'],
            'first_entry_admin',
            $stav,
            $httpStatus,
            $chyba
        );
    }

    return true;
}

function cb_push_send_admin_info(
    array $idUsers,
    string $typ,
    string $obsah,
    string $nadpis = 'Admin info',
    string $pozn = '',
    ?int $idOdeslal = null
): array {
    global $PROSTREDI;

    if ((string)($PROSTREDI ?? 'SERVER') !== 'SERVER') {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }

    $cleanUsers = [];
    foreach ($idUsers as $idUser) {
        $idUser = (int)$idUser;
        if ($idUser > 0) {
            $cleanUsers[$idUser] = $idUser;
        }
    }

    $typ = trim($typ);
    if ($typ === '') {
        $typ = 'admin_info';
    }

    $nadpis = trim($nadpis);
    if ($nadpis === '') {
        $nadpis = 'Admin info';
    }

    $obsah = trim($obsah);
    if ($cleanUsers === [] || $obsah === '') {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }

    $pozn = trim($pozn);
    $idOdeslal = ($idOdeslal !== null && $idOdeslal > 0) ? $idOdeslal : null;

    $canPush = (
        defined('CB_VAPID_PUBLIC')
        && defined('CB_VAPID_PRIVATE')
        && defined('CB_VAPID_SUBJECT')
        && cb_push_has_vendor()
    );
    if (!$canPush) {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }

    $devicesByUser = [];
    foreach ($cleanUsers as $idUser) {
        $devices = cb_push_load_devices($idUser);
        if (count($devices) > 0) {
            $devicesByUser[$idUser] = $devices;
        }
    }
    if ($devicesByUser === []) {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }

    $conn = db();
    $stmtInfo = $conn->prepare('
        INSERT INTO admin_info (typ, nadpis, obsah, pozn, id_odeslal, vytvoreno)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    if (!$stmtInfo) {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }
    $stmtInfo->bind_param('ssssi', $typ, $nadpis, $obsah, $pozn, $idOdeslal);
    $stmtInfo->execute();
    $idAdminInfo = (int)$stmtInfo->insert_id;
    $stmtInfo->close();

    if ($idAdminInfo <= 0) {
        return ['ok' => 0, 'id_admin_info' => 0, 'odeslano' => 0];
    }

    $webPush = null;
    require_once cb_push_vendor_autoload();
    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];
    $webPush = new Minishlink\WebPush\WebPush($auth);
    $odeslano = 0;
    $body = preg_replace('/\s+/', ' ', $obsah) ?? $obsah;
    if (mb_strlen($body, 'UTF-8') > 140) {
        $body = mb_substr($body, 0, 137, 'UTF-8') . '...';
    }

    foreach ($devicesByUser as $idUser => $devices) {
        $token = bin2hex(random_bytes(32));
        $stmtUser = $conn->prepare('
            INSERT INTO admin_info_user (id_admin_info, id_user, token)
            VALUES (?, ?, ?)
        ');
        if (!$stmtUser) {
            continue;
        }
        $stmtUser->bind_param('iis', $idAdminInfo, $idUser, $token);
        $stmtUser->execute();
        $stmtUser->close();

        if (!($webPush instanceof Minishlink\WebPush\WebPush)) {
            continue;
        }

        $url = '/mobil/admin_info.php?t=' . rawurlencode($token);
        $payloadArr = [
            'type' => 'ADMIN_INFO',
            'title' => $nadpis,
            'body' => $body,
            'url' => $url,
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
                        $chyba = is_string($reason) && $reason !== '' ? $reason : 'Push fail';
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
                'admin_info',
                $stav,
                $httpStatus,
                $chyba
            );
        }

        $stmtSent = $conn->prepare('
            UPDATE admin_info_user
            SET odeslano = NOW()
            WHERE id_admin_info = ? AND id_user = ?
            LIMIT 1
        ');
        if ($stmtSent) {
            $stmtSent->bind_param('ii', $idAdminInfo, $idUser);
            $stmtSent->execute();
            $stmtSent->close();
        }
        $odeslano++;
    }

    return ['ok' => 1, 'id_admin_info' => $idAdminInfo, 'odeslano' => $odeslano];
}

// notifikace/notifikace_2fa.php * Verze: V2 * Aktualizace: 07.03.2026 * Počet řádků: 206
// Předchozí počet řádků: 202
// Konec souboru
