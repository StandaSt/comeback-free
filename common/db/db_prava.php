<?php
declare(strict_types=1);

/*
 * Nacte efektivni prava prihlaseneho uzivatele do session.
 * Globalni pravo je dane existenci dvojice role a prava.
 */

if (!function_exists('cb_db_prava_nacti_do_session')) {
    function cb_db_prava_nacti_do_session(mysqli $conn, int $idUser, int $idRole): void
    {
        $_SESSION['prava'] = [];
        $_SESSION['prava_stav'] = [];

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

        if ($idUser <= 0 || $idRole <= 0) {
            return;
        }

        $stmtGlobal = $conn->prepare('
            SELECT globalni.id_pravo
            FROM prava_global AS globalni
            INNER JOIN cis_prava AS pravo
                ON pravo.id_pravo = globalni.id_pravo
               AND pravo.aktivni = 1
            WHERE globalni.id_role = ?
        ');
        if ($stmtGlobal === false) {
            throw new RuntimeException('DB: prepare selhal (prava_global select).');
        }

        $stmtGlobal->bind_param('i', $idRole);
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
