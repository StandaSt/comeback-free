<?php
// lib/helpdesk_notifikace.php * Verze: V1 * Aktualizace: 24.06.2026
declare(strict_types=1);

require_once __DIR__ . '/helpdesk_prava.php';
require_once __DIR__ . '/../../www/notifikace/notifikace_2fa.php';

function cb_helpdesk_push_secret(): string
{
    if (defined('CB_VAPID_PRIVATE')) {
        return (string)CB_VAPID_PRIVATE;
    }

    return 'helpdesk';
}

function cb_helpdesk_push_token_make(int $idUser, int $idNotifikace): string
{
    if ($idUser <= 0 || $idNotifikace <= 0) {
        return '';
    }

    $payload = json_encode([
        'id_user' => $idUser,
        'id_notifikace' => $idNotifikace,
        'exp' => time() + (86400 * 30),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        return '';
    }

    $payload64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sign = hash_hmac('sha256', $payload64, cb_helpdesk_push_secret());

    return $payload64 . '.' . $sign;
}

function cb_helpdesk_push_token_parse(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !str_contains($token, '.')) {
        return null;
    }

    [$payload64, $sign] = explode('.', $token, 2);
    $payload64 = trim($payload64);
    $sign = trim($sign);
    if ($payload64 === '' || $sign === '') {
        return null;
    }

    $expected = hash_hmac('sha256', $payload64, cb_helpdesk_push_secret());
    if (!hash_equals($expected, $sign)) {
        return null;
    }

    $payload = base64_decode(strtr($payload64, '-_', '+/'), true);
    if (!is_string($payload) || $payload === '') {
        return null;
    }

    $data = json_decode($payload, true);
    if (!is_array($data)) {
        return null;
    }

    $idUser = (int)($data['id_user'] ?? 0);
    $idNotifikace = (int)($data['id_notifikace'] ?? 0);
    $exp = (int)($data['exp'] ?? 0);
    if ($idUser <= 0 || $idNotifikace <= 0 || $exp <= 0 || $exp < time()) {
        return null;
    }

    return [
        'id_user' => $idUser,
        'id_notifikace' => $idNotifikace,
        'exp' => $exp,
    ];
}

function cb_helpdesk_push_body(string $typ, string $text = ''): string
{
    $text = trim($text);

    return match ($typ) {
        'admin_odpoved', 'nova_odpoved', 'zmena_stavu' => $text !== '' ? $text : 'HelpDesk',
        'nova_priloha' => 'V HelpDesku byla přidána příloha.',
        default => 'Nový ticket v HelpDesku.',
    };
}

function cb_helpdesk_push_odeslat(int $idUser, int $idNotifikace, string $typ, string $text): void
{
    global $PROSTREDI;

    if ((string)($PROSTREDI ?? 'SERVER') !== 'SERVER') {
        return;
    }

    if ($idUser <= 0 || $idNotifikace <= 0) {
        return;
    }

    if (!defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_SUBJECT')) {
        return;
    }

    if (!cb_push_has_vendor()) {
        return;
    }

    $devices = cb_push_load_devices($idUser);
    if ($devices === []) {
        return;
    }

    $token = cb_helpdesk_push_token_make($idUser, $idNotifikace);
    if ($token === '') {
        return;
    }

    require_once __DIR__ . '/../../www/vendor/autoload.php';

    $auth = [
        'VAPID' => [
            'subject' => (string)CB_VAPID_SUBJECT,
            'publicKey' => (string)CB_VAPID_PUBLIC,
            'privateKey' => (string)CB_VAPID_PRIVATE,
        ],
    ];

    $webPush = new Minishlink\WebPush\WebPush($auth);
    $payload = json_encode([
        'type' => 'HELPDESK',
        'title' => 'Comeback',
        'body' => cb_helpdesk_push_body($typ, $text),
        'url' => cb_url_abs('mobil/mobil_helpdesk.php?t=' . rawurlencode($token)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload) || $payload === '') {
        return;
    }

    foreach ($devices as $d) {
        $stav = 'ok';
        $httpStatus = null;
        $chyba = null;

        try {
            $sub = Minishlink\WebPush\Subscription::create([
                'endpoint' => $d['endpoint'],
                'publicKey' => $d['klic_public'],
                'authToken' => $d['klic_auth'],
            ]);

            $report = $webPush->sendOneNotification($sub, $payload);

            if ($report) {
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
            } else {
                $stav = 'fail';
                $chyba = 'Push: bez reportu';
            }
        } catch (Throwable $e) {
            $stav = 'fail';
            $chyba = $e->getMessage();
        }

        cb_push_audit_try_insert(
            $idUser,
            (int)($d['id'] ?? 0),
            'helpdesk_' . $typ,
            $stav,
            $httpStatus,
            $chyba
        );
    }
}

function cb_helpdesk_notifikace_pridat(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idUser, string $typ, string $text): void
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        return;
    }

    $typ = trim($typ);
    $text = trim($text);
    if ($typ === '' || $text === '') {
        return;
    }

    if (mb_strlen($text, 'UTF-8') > 255) {
        $text = mb_substr($text, 0, 252, 'UTF-8') . '...';
    }

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_notifikace
        (id_helpdesk, id_helpdesk_zprava, id_user, typ, text, vytvoreno)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return;
    }

    $stmt->bind_param('iiiss', $idHelpdesk, $idZprava, $idUser, $typ, $text);
    $stmt->execute();
    $idNotifikace = (int)$stmt->insert_id;
    $stmt->close();

    cb_helpdesk_push_odeslat($idUser, $idNotifikace, $typ, $text);
}

function cb_helpdesk_notifikace_adminum(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idAutor, string $typ, string $text): void
{
    $admins = cb_helpdesk_admin_ids($conn);
    foreach ($admins as $idAdmin) {
        if ((int)$idAdmin === $idAutor) {
            continue;
        }
        cb_helpdesk_notifikace_pridat($conn, $idHelpdesk, $idZprava, (int)$idAdmin, $typ, $text);
    }
}

function cb_helpdesk_notifikace_ucastnikum(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idAutor, string $typ, string $text): void
{
    $users = [];

    $stmt = $conn->prepare('SELECT id_user_zalozil FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idHelpdesk);
        $stmt->execute();
        $stmt->bind_result($idZalozil);
        if ($stmt->fetch()) {
            $idZalozil = (int)$idZalozil;
            if ($idZalozil > 0) {
                $users[$idZalozil] = $idZalozil;
            }
        }
        $stmt->close();
    }

    $stmtSledujici = $conn->prepare('SELECT id_user FROM helpdesk_sledujici WHERE id_helpdesk = ?');
    if ($stmtSledujici instanceof mysqli_stmt) {
        $stmtSledujici->bind_param('i', $idHelpdesk);
        $stmtSledujici->execute();
        $res = $stmtSledujici->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                if ($idUser > 0) {
                    $users[$idUser] = $idUser;
                }
            }
            $res->free();
        }
        $stmtSledujici->close();
    }

    $stmtZpravy = $conn->prepare('SELECT DISTINCT id_user FROM helpdesk_zprava WHERE id_helpdesk = ?');
    if ($stmtZpravy instanceof mysqli_stmt) {
        $stmtZpravy->bind_param('i', $idHelpdesk);
        $stmtZpravy->execute();
        $res = $stmtZpravy->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                if ($idUser > 0) {
                    $users[$idUser] = $idUser;
                }
            }
            $res->free();
        }
        $stmtZpravy->close();
    }

    foreach ($users as $idUser) {
        if ((int)$idUser === $idAutor) {
            continue;
        }
        cb_helpdesk_notifikace_pridat($conn, $idHelpdesk, $idZprava, (int)$idUser, $typ, $text);
    }
}

function cb_helpdesk_notifikace_full_name(?string $jmeno, ?string $prijmeni, int $idUser = 0): string
{
    $fullName = trim(trim((string)$jmeno) . ' ' . trim((string)$prijmeni));
    if ($fullName !== '') {
        return $fullName;
    }

    if ($idUser > 0) {
        return 'Uživatel #' . (string)$idUser;
    }

    return 'Uživatel';
}

function cb_helpdesk_notifikace_sledujicim_o_admin_odpovedi(mysqli $conn, int $idHelpdesk, int $idZprava, int $idAutor, string $odpoved, string $textNotifikace = ''): void
{
    if ($idHelpdesk <= 0 || $idZprava <= 0) {
        return;
    }

    $users = [];

    $stmtOwner = $conn->prepare('SELECT id_user_zalozil FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if ($stmtOwner instanceof mysqli_stmt) {
        $stmtOwner->bind_param('i', $idHelpdesk);
        $stmtOwner->execute();
        $stmtOwner->bind_result($idZalozil);
        if ($stmtOwner->fetch()) {
            $idZalozil = (int)$idZalozil;
            if ($idZalozil > 0) {
                $users[$idZalozil] = $idZalozil;
            }
        }
        $stmtOwner->close();
    }

    $stmtWatch = $conn->prepare('SELECT id_user FROM helpdesk_sledujici WHERE id_helpdesk = ?');
    if ($stmtWatch instanceof mysqli_stmt) {
        $stmtWatch->bind_param('i', $idHelpdesk);
        $stmtWatch->execute();
        $res = $stmtWatch->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                if ($idUser > 0) {
                    $users[$idUser] = $idUser;
                }
            }
            $res->free();
        }
        $stmtWatch->close();
    }

    if ($users === []) {
        return;
    }

    $stmtUser = $conn->prepare('SELECT jmeno, prijmeni FROM `user` WHERE id_user = ? LIMIT 1');

    foreach ($users as $idUser) {
        $idUser = (int)$idUser;
        if ($idUser <= 0 || $idUser === $idAutor) {
            continue;
        }

        $fullName = cb_helpdesk_notifikace_full_name(null, null, $idUser);
        if ($stmtUser instanceof mysqli_stmt) {
            $stmtUser->bind_param('i', $idUser);
            $stmtUser->execute();
            $stmtUser->bind_result($jmeno, $prijmeni);
            if ($stmtUser->fetch()) {
                $fullName = cb_helpdesk_notifikace_full_name((string)$jmeno, (string)$prijmeni, $idUser);
            }
            $stmtUser->free_result();
        }

        $text = trim($textNotifikace);
        if ($text === '') {
            $text = 'Admin reagoval na tiket č. ' . (string)$idHelpdesk;
        }

        cb_helpdesk_notifikace_pridat($conn, $idHelpdesk, $idZprava, $idUser, 'admin_odpoved', $text);
    }

    if ($stmtUser instanceof mysqli_stmt) {
        $stmtUser->close();
    }
}

// lib/helpdesk_notifikace.php * Verze: V1 * Aktualizace: 24.06.2026
// Konec souboru
