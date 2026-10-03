<?php
declare(strict_types=1);
/* Účel: Spravuje výhradní zámek plánování pobočky. Čtení stránky zámek nemění. */

/** Provede parametrizované SQL; transakci řídí dispatcher celé uživatelské akce. */
function cb_smeny_planovani_sql(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

/** Vrátí pouze aktuální zámek podle času DB, bez jeho prodlužování při GET. */
function cb_smeny_planovani_zamek_nacist(mysqli $db, int $idBranch): ?array
{
    $stmt = cb_smeny_planovani_sql($db, 'SELECT id_person,token,platny_do FROM smeny_zamek WHERE id_pob=? AND platny_do>NOW()', 'i', [$idBranch]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

/** Zámek vlastní přihlášená relace a osoba; samotné stejné ID osoby nestačí. */
function cb_smeny_planovani_zamek_vlastni(?array $lock, int $idBranch, int $idPerson): bool
{
    $token = (string)($_SESSION['smeny_zamky'][$idBranch] ?? '');
    return $lock !== null && (int)$lock['id_person'] === $idPerson && $token !== '' && hash_equals((string)$lock['token'], $token);
}

/** Volat až po zamčení rodičovské pobočky, které serializuje i dosud neexistující zámek. */
function cb_smeny_planovani_zamek_ziskat(mysqli $db, int $idBranch, int $idPerson): string
{
    $lock = cb_smeny_planovani_zamek_nacist($db, $idBranch);
    if ($lock !== null && !cb_smeny_planovani_zamek_vlastni($lock, $idBranch, $idPerson)) {
        throw new CbUserVisibleException('Pobočku právě plánuje jiný uživatel. Zkuste to po uvolnění zámku nebo po ' . $lock['platny_do'] . '.');
    }
    $token = $lock === null ? bin2hex(random_bytes(16)) : (string)$lock['token'];
    cb_smeny_planovani_sql($db, 'INSERT INTO smeny_zamek (id_pob,id_person,token,platny_do) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE id_person=VALUES(id_person),token=VALUES(token),platny_do=VALUES(platny_do)', 'iis', [$idBranch,$idPerson,$token])->close();
    return $token;
}

/** Každý zápis vyžaduje dosud platný token z formuláře; propadlý nelze tiše převzít. */
function cb_smeny_planovani_zamek_overit(mysqli $db, int $idBranch, int $idPerson, string $token): void
{
    $lock = cb_smeny_planovani_zamek_nacist($db, $idBranch);
    if (!cb_smeny_planovani_zamek_vlastni($lock, $idBranch, $idPerson) || !hash_equals((string)$lock['token'], $token)) {
        throw new CbUserVisibleException('Zámek plánování vypršel nebo patří jiné relaci. Obnovte stránku a znovu převezměte plánování.');
    }
    cb_smeny_planovani_sql($db, 'UPDATE smeny_zamek SET platny_do=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id_pob=?', 'i', [$idBranch])->close();
}
