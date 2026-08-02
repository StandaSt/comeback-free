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

        $idAkce = (int)($payload['id_akce'] ?? 0);
        if (!in_array($idAkce, [1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17, 20], true)) {
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
        if (isset($payload['detail']) && is_array($payload['detail'])) {
            foreach ((array)$payload['detail'] as $k => $v) {
                $key = trim((string)$k);
                if ($key === '' || $key === 'zdroj') {
                    continue;
                }
                if (is_scalar($v) || $v === null) {
                    $detail[$key] = $v;
                }
            }
        }

        $saved = db_user_akce_insert([
            'id_user' => $idUser,
            'id_login' => (int)($_SESSION['cb_id_login'] ?? 0),
            'id_karta' => ($idKarta > 0) ? $idKarta : null,
            'id_akce' => $idAkce,
            'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'vysledek' => $vysledek,
            'err_msg' => $errMsg,
        ]);

        if ($saved && function_exists('cb_tmp_measure_detail_add')) {
            cb_tmp_measure_detail_add([
                'typ' => 'ajax',
                'nazev' => 'user_akce_' . $idAkce,
                'id_karta' => ($idKarta > 0) ? $idKarta : null,
                'detail' => [
                    'id_akce' => $idAkce,
                    'id_user' => $idUser,
                    'id_login' => (int)($_SESSION['cb_id_login'] ?? 0),
                    'vysledek' => $vysledek,
                    'err_msg' => $errMsg,
                    'payload_detail' => $detail,
                ],
            ]);
        }

        return $saved;
    }
}
