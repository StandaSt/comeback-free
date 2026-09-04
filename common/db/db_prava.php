<?php
declare(strict_types=1);

/*
 * Nacte prava prihlaseneho uzivatele do session.
 * Globalni prava jsou sjednocenim prav vsech jeho roli v user_role.
 */

if (!function_exists('cb_db_user_role_data')) {
    /** @return array<int,string> Role uživatele indexované podle id_role. */
    function cb_db_user_role_data(mysqli $conn, int $idUser): array
    {
        if ($idUser <= 0) {
            return [];
        }

        $stmt = $conn->prepare('
            SELECT ur.id_role, cr.role
            FROM user_role AS ur
            INNER JOIN cis_role AS cr ON cr.id_role = ur.id_role
            WHERE ur.id_user = ?
              AND cr.aktivni = 1
            ORDER BY ur.id_role ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB: načtení rolí uživatele selhalo.');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $result = $stmt->get_result();
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[(int)$row['id_role']] = (string)$row['role'];
        }
        $stmt->close();
        return $roles;
    }
}

if (!function_exists('cb_user_ma_roli')) {
    function cb_user_ma_roli(int $idRole): bool
    {
        if ($idRole <= 0) {
            return false;
        }
        $roles = $_SESSION['cb_user']['roles'] ?? [];
        if (!is_array($roles)) {
            return false;
        }
        foreach ($roles as $role) {
            if (is_array($role) && (int)($role['id_role'] ?? 0) === $idRole) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('cb_db_prava_nacti_do_session')) {
    function cb_db_prava_nacti_do_session(mysqli $conn, int $idUser): void
    {
        $_SESSION['prava'] = [];
        $_SESSION['prava_stav'] = [];

        $roleData = cb_db_user_role_data($conn, $idUser);
        if (!isset($_SESSION['cb_user']) || !is_array($_SESSION['cb_user'])) {
            $_SESSION['cb_user'] = [];
        }
        $_SESSION['cb_user']['roles'] = [];
        foreach ($roleData as $idRole => $roleName) {
            $_SESSION['cb_user']['roles'][] = [
                'id_role' => $idRole,
                'name' => $roleName,
            ];
        }
        $_SESSION['cb_user']['role'] = implode(', ', array_values($roleData));
        unset($_SESSION['cb_user']['id_role']);

        $resRights = $conn->query('SELECT id_pravo, aktivni FROM cis_prava ORDER BY id_pravo');
        if (!($resRights instanceof mysqli_result)) {
            throw new RuntimeException('DB: načtení číselníku práv selhalo.');
        }
        while ($row = $resRights->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo > 0) {
                $_SESSION['prava_stav'][$idPravo] = (int)$row['aktivni'] === 1 ? 1 : 0;
            }
        }
        $resRights->free();

        if ($idUser <= 0 || $roleData === []) {
            return;
        }

        $stmtGlobal = $conn->prepare('
            SELECT DISTINCT globalni.id_pravo
            FROM user_role AS uzivatelska_role
            INNER JOIN prava_global AS globalni
                ON globalni.id_role = uzivatelska_role.id_role
            INNER JOIN cis_prava AS pravo
                ON pravo.id_pravo = globalni.id_pravo
               AND pravo.aktivni = 1
            WHERE uzivatelska_role.id_user = ?
        ');
        if ($stmtGlobal === false) {
            throw new RuntimeException('DB: prepare selhal (prava_global select).');
        }

        $stmtGlobal->bind_param('i', $idUser);
        $stmtGlobal->execute();
        $resGlobal = $stmtGlobal->get_result();
        while ($row = $resGlobal->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo > 0) {
                $_SESSION['prava'][$idPravo] = 1;
            }
        }
        $stmtGlobal->close();

        $stmtVyjimky = $conn->prepare('
            SELECT vyjimka.id_pravo, vyjimka.povoleno
            FROM prava_vyjimky AS vyjimka
            INNER JOIN cis_prava AS pravo
                ON pravo.id_pravo = vyjimka.id_pravo
               AND pravo.aktivni = 1
            WHERE vyjimka.id_user = ?
        ');
        if ($stmtVyjimky === false) {
            throw new RuntimeException('DB: prepare selhal (prava_vyjimky select).');
        }

        $stmtVyjimky->bind_param('i', $idUser);
        $stmtVyjimky->execute();
        $resVyjimky = $stmtVyjimky->get_result();
        while ($row = $resVyjimky->fetch_assoc()) {
            $idPravo = (int)$row['id_pravo'];
            if ($idPravo <= 0) {
                continue;
            }

            $povoleno = $row['povoleno'];
            if ($povoleno !== null && (int)$povoleno === 1) {
                $_SESSION['prava'][$idPravo] = 1;
            } elseif ($povoleno !== null && (int)$povoleno === 0) {
                unset($_SESSION['prava'][$idPravo]);
            }
        }
        $stmtVyjimky->close();
    }
}
