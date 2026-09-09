<?php
declare(strict_types=1);

/*
 * Účel souboru: Promítne účinné ukončení pracovního poměru z HR do účtu.
 * Spouští zápis jen po instalaci schválené migrace a pouze při nalezené změně.
 */

function cb_hr_user_sync_schema_ready(mysqli $db): bool
{
    $stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $table = 'user_email_zmena';
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $ready = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $ready;
}

function cb_hr_user_sync_ukoncene(mysqli $db): int
{
    if (!cb_hr_user_sync_schema_ready($db)) {
        return 0;
    }

    $result = $db->query('
        SELECT p.id_person, p.id_user
        FROM hr_person p
        WHERE p.id_firma <> 0
          AND EXISTS (
              SELECT 1
              FROM hr_pracovni_vztah pv
              WHERE pv.id_person = p.id_person
                AND pv.platny = 1
                AND pv.datum_ukonceni IS NOT NULL
                AND pv.datum_ukonceni < CURDATE()
          )
          AND NOT EXISTS (
              SELECT 1
              FROM hr_pracovni_vztah pv
              WHERE pv.id_person = p.id_person
                AND pv.platny = 1
                AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
          )
    ');
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    if ($rows === []) {
        return 0;
    }

    $db->begin_transaction();
    try {
        $stmtPerson = $db->prepare('UPDATE hr_person SET id_firma = 0 WHERE id_person = ? AND id_firma <> 0');
        $stmtUser = $db->prepare('UPDATE user SET id_firma = 0 WHERE id_user = ?');
        $stmtPobocky = $db->prepare('DELETE FROM user_pobocka WHERE id_user = ?');
        $stmtRole = $db->prepare('DELETE FROM user_role WHERE id_user = ? AND id_role IN (3, 5, 7, 9)');
        $stmtPracoviste = $db->prepare('UPDATE hr_pracoviste SET platny = 0, zruseno = NOW() WHERE id_person = ? AND platny = 1 AND platnost_do IS NOT NULL AND platnost_do < CURDATE()');
        $changed = 0;
        foreach ($rows as $row) {
            $idPerson = (int)$row['id_person'];
            $idUser = (int)($row['id_user'] ?? 0);
            $stmtPerson->bind_param('i', $idPerson);
            $stmtPerson->execute();
            $changed += (int)$stmtPerson->affected_rows;
            $stmtPracoviste->bind_param('i', $idPerson);
            $stmtPracoviste->execute();
            if ($idUser <= 0) {
                continue;
            }
            $stmtUser->bind_param('i', $idUser);
            $stmtUser->execute();
            $stmtPobocky->bind_param('i', $idUser);
            $stmtPobocky->execute();
            $stmtRole->bind_param('i', $idUser);
            $stmtRole->execute();
        }
        $stmtPerson->close();
        $stmtUser->close();
        $stmtPobocky->close();
        $stmtRole->close();
        $stmtPracoviste->close();
        $db->commit();
        return $changed;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
