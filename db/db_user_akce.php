<?php
declare(strict_types=1);

if (!function_exists('db_user_akce_insert')) {
    /**
     * @param array<string, mixed> $row
     */
    function db_user_akce_insert(array $row): bool
    {
        $idUser = (int)($row['id_user'] ?? 0);
        $idAkce = (int)($row['id_akce'] ?? 0);
        if ($idUser <= 0 || $idAkce <= 0) {
            return false;
        }

        $idLogin = isset($row['id_login']) ? (int)$row['id_login'] : null;
        $idKarta = isset($row['id_karta']) ? (int)$row['id_karta'] : null;
        $metoda = trim((string)($row['metoda'] ?? ''));
        $requestUri = trim((string)($row['request_uri'] ?? ''));
        $vysledek = ((int)($row['vysledek'] ?? 1) === 1) ? 1 : 0;
        $errMsg = trim((string)($row['err_msg'] ?? ''));

        if ($metoda === '') {
            $metoda = null;
        }
        if ($requestUri === '') {
            $requestUri = null;
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
            INSERT INTO user_akce
                (id_user, id_login, id_karta, id_akce, detail_json, request_uri, metoda, vysledek, err_msg)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $conn->prepare($sql);
        if (!$stmt instanceof mysqli_stmt) {
            return false;
        }

        $stmt->bind_param(
            'iiiisssis',
            $idUser,
            $idLogin,
            $idKarta,
            $idAkce,
            $detailJson,
            $requestUri,
            $metoda,
            $vysledek,
            $errMsg
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

