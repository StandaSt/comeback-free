<?php
declare(strict_types=1);

function cb_local_login_sync_vyrid(): void
{
    if (empty($_SESSION['login_ok']) || !isset($_SERVER['HTTP_X_COMEBACK_LOCAL_LOGIN_SYNC'])) {
        return;
    }

    header('Content-Type: application/json; charset=utf-8');

    if ((string)($GLOBALS['PROSTREDI'] ?? '') !== 'LOCAL') {
        echo json_encode(['ok' => true, 'skipped' => 1], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $step = strtolower(trim((string)$_SERVER['HTTP_X_COMEBACK_LOCAL_LOGIN_SYNC']));
    if ($step === 'users') {
        cb_local_login_sync_users();
    }

    if ($step === 'done') {
        unset($_SESSION['cb_local_after_login_sync']);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => 'Neplatný krok synchronizace.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function cb_local_login_sync_users(): void
{
    try {
        if (!defined('CB_SMENY_USER_KONTROLA_AUTO_RUN')) {
            define('CB_SMENY_USER_KONTROLA_AUTO_RUN', false);
        }
        require_once __DIR__ . '/smeny_user_kontrola.php';
        cb_smeny_user_kontrola(true);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('Local smeny user sync selhal: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'err' => 'Synchronizace uživatelů ze Směn selhala.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
