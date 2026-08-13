<?php
declare(strict_types=1);

if (!function_exists('db_user_akce_insert')) {
    /**
     * @param array<string, mixed> $row
     */
    function db_user_akce_insert(array $row): bool
    {
        $idUser = (int)($row['id_user'] ?? 0);
        $idModul = (int)($row['id_modul'] ?? 0);
        $idTyp = (int)($row['id_user_akce_typ'] ?? ($row['id_akce'] ?? 0));
        if ($idUser <= 0 || $idModul <= 0 || $idTyp <= 0) {
            return false;
        }

        $idLogin = isset($row['id_login']) ? (int)$row['id_login'] : null;
        if ($idLogin !== null && $idLogin <= 0) {
            $idLogin = null;
        }
        $objekt = trim((string)($row['objekt'] ?? ''));
        $idObjektu = isset($row['id_objektu']) ? (int)$row['id_objektu'] : null;
        $pole = trim((string)($row['pole'] ?? ''));
        $hodnotaOld = array_key_exists('hodnota_old', $row) ? (string)$row['hodnota_old'] : null;
        $hodnotaNew = array_key_exists('hodnota_new', $row) ? (string)$row['hodnota_new'] : null;
        $vysledek = ((int)($row['vysledek'] ?? 1) === 1) ? 1 : 0;
        $errMsg = trim((string)($row['err_msg'] ?? ''));

        if ($objekt === '') {
            $objekt = null;
        }
        if ($idObjektu !== null && $idObjektu <= 0) {
            $idObjektu = null;
        }
        if ($pole === '') {
            $pole = null;
        }
        if ($hodnotaOld === '') {
            $hodnotaOld = null;
        }
        if ($hodnotaNew === '') {
            $hodnotaNew = null;
        }
        if ($errMsg === '') {
            $errMsg = null;
        }

        $detailJson = null;
        if (array_key_exists('detail_json', $row)) {
            $detailJsonRaw = (string)$row['detail_json'];
            $detailJsonRaw = trim($detailJsonRaw);
            if ($detailJsonRaw !== '') {
                $detailJson = $detailJsonRaw;
            }
        }

        $conn = db();
        $sql = '
            INSERT INTO user_akce_new
                (
                    id_user, id_login, id_modul, id_user_akce_typ,
                    objekt, id_objektu, pole, hodnota_old, hodnota_new,
                    detail_json, vysledek, err_msg
                )
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $conn->prepare($sql);
        if (!$stmt instanceof mysqli_stmt) {
            return false;
        }

        $stmt->bind_param(
            'iiiisissssis',
            $idUser,
            $idLogin,
            $idModul,
            $idTyp,
            $objekt,
            $idObjektu,
            $pole,
            $hodnotaOld,
            $hodnotaNew,
            $detailJson,
            $vysledek,
            $errMsg
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
