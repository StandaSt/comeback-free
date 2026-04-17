<?php
/* =========================
   0) Logout (GET)
   ========================= */
if (isset($_GET['action']) && (string)$_GET['action'] === 'logout') {
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = 0;
    if (is_array($cbUser) && !empty($cbUser['id_user'])) {
        $idUser = (int)$cbUser['id_user'];
        try {
            $stmt = db()->prepare('INSERT INTO user_login (id_user, akce) VALUES (?,0)');
            if ($stmt) {
                $stmt->bind_param('i', $idUser);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // tichy fail - logout musi pokracovat i kdyz log selze
        }
    }

    if ($idUser === 1) {
        $logDir = __DIR__ . '/../log';
        $logFiles = [
            $logDir . '/merime_casy_AI.txt',
            $logDir . '/merime_casy_user.txt',
        ];
        foreach ($logFiles as $logFile) {
            @file_put_contents($logFile, '', LOCK_EX);
        }
    }

    $_SESSION = [];
    session_destroy();

    header('Location: ' . cb_url('/'));
    exit;
}
