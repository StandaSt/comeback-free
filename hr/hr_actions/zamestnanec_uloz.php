<?php
declare(strict_types=1);

require_once __DIR__ . '/../../www/lib/session_boot.php';
require_once __DIR__ . '/../../www/config/secrets.php';
require_once __DIR__ . '/../../www/lib/app.php';
require_once __DIR__ . '/../hr_includes/hr_data.php';

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . cb_module_url('hr') . '?page=novy_zamestnanec');
    exit;
}

try {
    $idZamestnanec = hr_insert_employee(db(), $_POST, hr_current_user_id());
    $_SESSION['hr_flash'] = [
        'type' => 'success',
        'text' => 'Zaměstnanec byl uložen.',
    ];
    header('Location: ' . cb_module_url('hr') . '?page=zamestnanec&id=' . $idZamestnanec);
    exit;
} catch (Throwable $e) {
    $_SESSION['hr_flash'] = [
        'type' => 'error',
        'text' => $e->getMessage(),
    ];
    header('Location: ' . cb_module_url('hr') . '?page=novy_zamestnanec');
    exit;
}
