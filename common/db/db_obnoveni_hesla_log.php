<?php
declare(strict_types=1);

/*
 * DB log zadosti o obnoveni hesla.
 * Resi pouze zalozeni a dokonceni akce typu 21 v user_akce_new.
 */

/* Zalozi log pred odeslanim emailu a vrati id nove akce. */
function db_obnoveni_hesla_log_zahaj(mysqli $db, int $idUser): int
{
    if ($idUser <= 0) {
        throw new RuntimeException('Chybí uživatel pro zápis obnovy hesla.');
    }

    $detailJson = json_encode(
        ['zdroj' => 'zapomenute_heslo', 'stav' => 'odesila_se'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($detailJson)) {
        throw new RuntimeException('Nepodařilo se připravit detail obnovy hesla.');
    }

    $stmt = $db->prepare(
        "INSERT INTO user_akce_new
            (id_user, id_login, id_modul, id_user_akce_typ, objekt, id_objektu, detail_json, vysledek, err_msg)
         VALUES
            (?, NULL, 1, 21, 'user', ?, ?, 0, NULL)"
    );
    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException('Nepodařilo se připravit zápis obnovy hesla.');
    }
    $stmt->bind_param('iis', $idUser, $idUser, $detailJson);
    $stmt->execute();
    $idAkce = (int)$db->insert_id;
    $stmt->close();

    if ($idAkce <= 0) {
        throw new RuntimeException('Nepodařilo se uložit zápis obnovy hesla.');
    }
    return $idAkce;
}

/* Uzavre log podle vysledku predani emailu SMTP serveru. */
function db_obnoveni_hesla_log_dokonci(mysqli $db, int $idAkce, bool $uspech, string $chyba = ''): void
{
    if ($idAkce <= 0) {
        throw new RuntimeException('Chybí záznam obnovy hesla k dokončení.');
    }

    $detailJson = json_encode(
        [
            'zdroj' => 'zapomenute_heslo',
            'stav' => $uspech ? 'predano_smtp' : 'chyba_smtp',
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($detailJson)) {
        throw new RuntimeException('Nepodařilo se připravit výsledek obnovy hesla.');
    }

    $vysledek = $uspech ? 1 : 0;
    $errMsg = trim($chyba);
    if ($errMsg === '') {
        $errMsg = null;
    } elseif (mb_strlen($errMsg, 'UTF-8') > 255) {
        $errMsg = mb_substr($errMsg, 0, 255, 'UTF-8');
    }

    $stmt = $db->prepare(
        'UPDATE user_akce_new
         SET detail_json=?, vysledek=?, err_msg=?
         WHERE id_user_akce=? AND id_user_akce_typ=21'
    );
    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException('Nepodařilo se připravit dokončení obnovy hesla.');
    }
    $stmt->bind_param('sisi', $detailJson, $vysledek, $errMsg, $idAkce);
    $stmt->execute();
    $ulozeno = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$ulozeno) {
        throw new RuntimeException('Nepodařilo se dokončit zápis obnovy hesla.');
    }
}
