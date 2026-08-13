<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db_user_akce.php';

if (!function_exists('cb_user_akce_should_log')) {
    function cb_user_akce_should_log(int $idUser): bool
    {
        return $idUser > 0;
    }
}

if (!function_exists('cb_user_akce_id_modul')) {
    function cb_user_akce_id_modul(string $module): int
    {
        static $cache = [];

        $module = strtolower(trim($module));
        if ($module === '' && defined('CB_EMBEDDED_MODULE')) {
            $module = strtolower((string)CB_EMBEDDED_MODULE);
        }
        if ($module === '') {
            $module = strtolower((string)($GLOBALS['CURRENT_MODULE'] ?? ''));
        }

        $names = [
            'administrace' => 'Administrace',
            'provoz' => 'Provoz',
            'hr' => 'HR',
            'smeny' => 'Směny',
            'ukoly' => 'Úkoly',
            'helpdesk' => 'Helpdesk',
        ];
        $fallback = [
            'administrace' => 1,
            'provoz' => 2,
            'hr' => 3,
            'smeny' => 4,
            'ukoly' => 5,
            'helpdesk' => 6,
        ];

        if (!isset($names[$module])) {
            return 0;
        }
        if (isset($cache[$module])) {
            return (int)$cache[$module];
        }

        $id = 0;
        try {
            $conn = db();
            $stmt = $conn->prepare('SELECT id_modul FROM cis_moduly WHERE modul = ? AND aktivni = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $name = $names[$module];
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $stmt->bind_result($dbId);
                if ($stmt->fetch()) {
                    $id = (int)$dbId;
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
            $id = 0;
        }

        if ($id <= 0) {
            $id = (int)$fallback[$module];
        }
        $cache[$module] = $id;

        return $id;
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

        $idTyp = (int)($payload['id_user_akce_typ'] ?? ($payload['id_akce'] ?? 0));
        if ($idTyp <= 0 || $idTyp > 20) {
            return false;
        }

        $module = trim((string)($payload['modul'] ?? ''));
        if ($module === '') {
            $module = (string)($GLOBALS['CURRENT_MODULE'] ?? '');
        }
        $idModul = (int)($payload['id_modul'] ?? 0);
        if ($idModul <= 0) {
            $idModul = cb_user_akce_id_modul($module);
        }
        if ($idModul <= 0) {
            return false;
        }

        $objekt = trim((string)($payload['objekt'] ?? ''));
        $idObjektu = (int)($payload['id_objektu'] ?? 0);
        if ($objekt === '' && isset($payload['id_karta'])) {
            $objekt = 'karta';
            $idObjektu = (int)$payload['id_karta'];
        }
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
            'id_login' => ((int)($_SESSION['cb_id_login'] ?? 0) > 0) ? (int)$_SESSION['cb_id_login'] : null,
            'id_modul' => $idModul,
            'id_user_akce_typ' => $idTyp,
            'objekt' => $objekt,
            'id_objektu' => ($idObjektu > 0) ? $idObjektu : null,
            'pole' => (string)($payload['pole'] ?? ''),
            'hodnota_old' => array_key_exists('hodnota_old', $payload) ? (string)$payload['hodnota_old'] : null,
            'hodnota_new' => array_key_exists('hodnota_new', $payload) ? (string)$payload['hodnota_new'] : null,
            'detail_json' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'vysledek' => $vysledek,
            'err_msg' => $errMsg,
        ]);

        if ($saved && function_exists('cb_tmp_measure_detail_add')) {
            cb_tmp_measure_detail_add([
                'typ' => 'ajax',
                'nazev' => 'user_akce_' . $idTyp,
                'id_karta' => ($objekt === 'karta' && $idObjektu > 0) ? $idObjektu : null,
                'detail' => [
                    'id_user_akce_typ' => $idTyp,
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
