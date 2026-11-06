<?php
declare(strict_types=1);
/* Dotazy pro prihlasovaci PIN libovolneho uctu a soubezne potvrzeni push; nacteni souboru zadny dotaz nespousti. */

/** Chybejici migrace nevypne dosavadni prihlasovani bez PINu. */
function cb_db_pin_pripraven(mysqli $db): bool
{
    $result = $db->query("SHOW COLUMNS FROM user_set WHERE Field IN ('pin','pin_hash','pin_pokusy','pin_okno_od','pin_blokace_do')");
    $ready = $result->num_rows === 5;
    $result->free();
    return $ready;
}

/** Platnost pocatecniho PINu se cte i u chybneho motoru; jeho overeni pak bez zamku bezpecne odmitneme. */
function cb_db_pin_zamky(mysqli $db): bool
{
    // Sdilena blokace vyzaduje transakcni radkovy zamek.
    $result = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_set'");
    $row = $result->fetch_assoc();
    $result->free();
    return is_array($row) && strcasecmp((string)$row['ENGINE'], 'InnoDB') === 0;
}

/** Identitu vzdy dodava prihlasovaci tok nebo vlastni profil, nikoli formular. */
function cb_db_pin_nacti(mysqli $db, int $idUser, bool $zamknout = false): ?array
{
    $stmt = $db->prepare('SELECT pin, pin_hash, pin_pokusy, UNIX_TIMESTAMP(pin_okno_od) AS okno_od,
        UNIX_TIMESTAMP(pin_blokace_do) AS blokace_do, UNIX_TIMESTAMP(NOW()) AS ted
        FROM user_set WHERE id_user=? LIMIT 1' . ($zamknout ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : null;
}

/** Pocitani pokusu otevreny PIN nemeni; zmena PINu jej smaze atomicky s ulozenim hashe. */
function cb_db_pin_uloz(mysqli $db, int $idUser, string $hash, int $pokusy, ?int $oknoOd, ?int $blokaceDo, bool $smazatPocatecni): void
{
    $clear = $smazatPocatecni ? 1 : 0;
    $stmt = $db->prepare('UPDATE user_set SET pin_hash=?, pin_pokusy=?,
        pin_okno_od=FROM_UNIXTIME(?), pin_blokace_do=FROM_UNIXTIME(?), pin=IF(?=1,NULL,pin) WHERE id_user=?');
    $stmt->bind_param('siiiii', $hash, $pokusy, $oknoOd, $blokaceDo, $clear, $idUser);
    $stmt->execute();
    $stmt->close();
}

/** Vytvori stejnou mobilni vyzvu jako puvodni 2FA, svazanou s vlastnikem PIN kroku. */
function cb_db_pin_push_vytvor(mysqli $db, int $idUser, int $idZarizeni, string $token): void
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $limit = defined('CB_2FA_LIMIT_SEC') ? max(1, (int)CB_2FA_LIMIT_SEC) : 60;
    $stmt = $db->prepare("INSERT INTO push_login_2fa (id_user, token, stav, ip, prohlizec, vytvoreno, vyprsi, rozhodnuto, id_zarizeni)
        VALUES (?, ?, 'ceka', ?, ?, NOW(), NOW() + INTERVAL ? SECOND, NULL, ?)");
    $stmt->bind_param('isssii', $idUser, $token, $ip, $ua, $limit, $idZarizeni);
    $stmt->execute();
    $stmt->close();
}

/** Token ciziho uzivatele ani drive schvaleny prosly token nesmi otevrit IS. */
function cb_db_pin_push_stav(mysqli $db, int $idUser, string $token): ?array
{
    $stmt = $db->prepare('SELECT stav, vyprsi>NOW() AS platny FROM push_login_2fa WHERE token=? AND id_user=? LIMIT 1');
    $stmt->bind_param('si', $token, $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : null;
}

/** Po vyuziti PINu nebo zruseni okna uz mobilni vyzva nema zustat cekajici. */
function cb_db_pin_push_ukonci(mysqli $db, int $idUser, string $token): void
{
    $stmt = $db->prepare("UPDATE push_login_2fa SET stav='exp' WHERE token=? AND id_user=? AND stav='ceka'");
    $stmt->bind_param('si', $token, $idUser);
    $stmt->execute();
    $stmt->close();
}
