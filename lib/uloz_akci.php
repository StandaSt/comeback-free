<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db_user_akce.php';

if (!function_exists('cb_user_akce_should_log')) {
    function cb_user_akce_should_log(int $idUser): bool
    {
        if ($idUser <= 0) {
            return false;
        }

        $global = (int)cb_system_setting('log_akce', 0);
        $conn = db();
        $stmt = $conn->prepare('SELECT log_on, log_off FROM user_akce_on_off WHERE id_user = ? LIMIT 1');
        if (!$stmt instanceof mysqli_stmt) {
            return ($global === 1);
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($logOn, $logOff);
        $hasRow = $stmt->fetch();
        $stmt->close();

        if ($hasRow) {
            if ((int)$logOff === 1) {
                return false;
            }
            if ((int)$logOn === 1) {
                return true;
            }
        }

        return ($global === 1);
    }
}

if (!function_exists('cb_user_akce_zapis')) {
    /**
     * @param array<string, mixed> $payload
     */
    function cb_user_akce_zapis(array $payload): bool
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($user) && isset($user['id_user'])) ? (int)$user['id_user'] : 0;
        if ($idUser <= 0) {
            return false;
        }

        if (!cb_user_akce_should_log($idUser)) {
            return false;
        }

        $idAkce = (int)($payload['id_akce'] ?? 0);
        if (!in_array($idAkce, [1, 2, 3, 4, 5], true)) {
            return false;
        }

        $idKarta = (int)($payload['id_karta'] ?? 0);
        $vysledek = ((int)($payload['vysledek'] ?? 1) === 1) ? 1 : 0;
        $errMsg = trim((string)($payload['err_msg'] ?? ''));

        $zdroj = trim((string)($payload['zdroj'] ?? ''));
        if ($zdroj === '') {
            $zdroj = 'karty';
        }
        $detail = ['zdroj' => $zdroj];

        return db_user_akce_insert([
            'id_user' => $idUser,
            'id_login' => (int)($_SESSION['cb_id_login'] ?? 0),
            'id_karta' => ($idKarta > 0) ? $idKarta : null,
            'id_akce' => $idAkce,
            'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
            'metoda' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'vysledek' => $vysledek,
            'err_msg' => $errMsg,
        ]);
    }
}
