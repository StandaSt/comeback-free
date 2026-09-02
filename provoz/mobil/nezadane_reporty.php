<?php
// mobil/nezadane_reporty.php * Obsluha mobilniho upozorneni na chybejici reporty
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/system.php';
require_once __DIR__ . '/../../common/config/secrets.php';

function cb_nezadane_reporty_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cb_nezadane_reporty_notification(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare("
        SELECT
            aiu.id_admin_info_user,
            ai.typ,
            ai.nadpis,
            ai.obsah,
            ai.pozn,
            ai.vytvoreno,
            aiu.odeslano,
            aiu.zobrazeno,
            aiu.potvrzeno
        FROM admin_info_user aiu
        INNER JOIN admin_info ai ON ai.id_admin_info = aiu.id_admin_info
        WHERE aiu.token = ?
          AND ai.typ IN ('nezadane_reporty_test', 'nezadane_reporty_cron')
        LIMIT 1
    ");
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

function cb_nezadane_reporty_mark_opened(int $idAdminInfoUser): void
{
    if ($idAdminInfoUser <= 0) {
        return;
    }

    $stmt = db()->prepare('UPDATE admin_info_user SET zobrazeno = COALESCE(zobrazeno, NOW()) WHERE id_admin_info_user = ? LIMIT 1');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('i', $idAdminInfoUser);
    $stmt->execute();
    $stmt->close();
}

function cb_nezadane_reporty_mark_acknowledged(int $idAdminInfoUser): void
{
    if ($idAdminInfoUser <= 0) {
        return;
    }

    $stmt = db()->prepare('UPDATE admin_info_user SET potvrzeno = COALESCE(potvrzeno, NOW()) WHERE id_admin_info_user = ? LIMIT 1');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('i', $idAdminInfoUser);
    $stmt->execute();
    $stmt->close();
}

$token = trim((string)($_GET['t'] ?? ''));
$notification = cb_nezadane_reporty_notification($token);
$idAdminInfoUser = is_array($notification) ? (int)($notification['id_admin_info_user'] ?? 0) : 0;

if ($idAdminInfoUser > 0) {
    cb_nezadane_reporty_mark_opened($idAdminInfoUser);
}

if (
    $idAdminInfoUser > 0
    && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
    && (string)($_POST['action'] ?? '') === 'acknowledge'
) {
    cb_nezadane_reporty_mark_acknowledged($idAdminInfoUser);
    $notification = cb_nezadane_reporty_notification($token);
}

$notificationTitle = is_array($notification) ? trim((string)($notification['nadpis'] ?? '')) : '';
if ($notificationTitle === '') {
    $notificationTitle = 'Kritická chyba !';
}
$notificationContent = is_array($notification)
    ? (string)($notification['obsah'] ?? '')
    : 'Zpráva nebyla nalezena nebo už není dostupná.';
$notificationData = is_array($notification)
    ? json_decode((string)($notification['pozn'] ?? ''), true)
    : null;
if (!is_array($notificationData)) {
    $notificationData = null;
}
$notificationAcknowledged = is_array($notification)
    && trim((string)($notification['potvrzeno'] ?? '')) !== '';

require __DIR__ . '/../modaly/modal_nezadane_reporty.php';

// mobil/nezadane_reporty.php * Konec souboru
