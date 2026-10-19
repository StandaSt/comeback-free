<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacist lokalni systemove a uzivatelske nastaveni do session
 * pri dokonceni prihlaseni. Funkce nema zadnou vazbu na API Smeny.
 */

function cb_login_load_settings_to_session(int $idUser): void
{
    $conn = db();

    $resSystem = $conn->query('SELECT restia_online, on_2fa, system_logout, log_akce, log_1, log_2, log_3, log_4, notif_chyby, notif_bad_login FROM set_system WHERE id_set = 1 LIMIT 1');
    if (!($resSystem instanceof mysqli_result)) {
        throw new RuntimeException('Nepodarilo se nacist set_system.');
    }

    $rowSystem = $resSystem->fetch_assoc();
    $resSystem->free();
    if (!is_array($rowSystem)) {
        throw new RuntimeException('Chybi set_system.');
    }
    cb_store_system_settings($rowSystem);

    $stmtUserSet = $conn->prepare(
        'SELECT pismo, dark, aktivni_modul FROM user_set WHERE id_user = ? LIMIT 1'
    );
    if (!($stmtUserSet instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodarilo se nacist user_set.');
    }

    $stmtUserSet->bind_param('i', $idUser);
    $stmtUserSet->execute();
    $resUserSet = $stmtUserSet->get_result();
    $rowUserSet = ($resUserSet instanceof mysqli_result) ? $resUserSet->fetch_assoc() : null;
    if ($resUserSet instanceof mysqli_result) {
        $resUserSet->free();
    }
    $stmtUserSet->close();

    if (!is_array($rowUserSet)) {
        throw new RuntimeException('Chybi user_set.');
    }

    cb_store_user_settings($rowUserSet);

}
