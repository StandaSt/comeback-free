<?php
// lib/db_povoleni.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 79
declare(strict_types=1);

/*
 * DB: pob_povoleni + pob_povoleni_hist
 *
 * Účel:
 * - synchronizovat aktuální povolení poboček (pob_povoleni) podle seznamu id_pob
 * - při změnách zapisovat historii do pob_povoleni_hist
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Zápis do pob_povoleni_hist (aktivni 1/0)
 */
function cb_db_insert_perm_hist(mysqli $conn, int $idUser, int $idPob, int $aktivni): void
{
    $zadal = 0;

    $stmt = $conn->prepare(
        'INSERT INTO pob_povoleni_hist (id_pob,id_user,zadal,aktivni) VALUES (?,?,?,?)'
    );
    $stmt->bind_param('iiii', $idPob, $idUser, $zadal, $aktivni);
    $stmt->execute();
    $stmt->close();
}

/**
 * Synchronizace pob_povoleni podle požadovaných id_pob.
 *
 * @param int[] $desiredPobIds
 * @return array{add:int, del:int}
 */
function cb_db_sync_permissions(mysqli $conn, int $idUser, array $desiredPobIds): array
{
    $current = [];

    $stmt = $conn->prepare('SELECT id_pob FROM pob_povoleni WHERE id_user=?');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $current[] = (int)$row['id_pob'];
    }
    $stmt->close();

    $current = array_values(array_unique($current));
    sort($current);

    $desired = array_values(array_unique(array_map('intval', $desiredPobIds)));
    sort($desired);

    $toAdd = array_values(array_diff($desired, $current));
    $toDel = array_values(array_diff($current, $desired));

    foreach ($toAdd as $idPob) {
        $stmt = $conn->prepare('INSERT INTO pob_povoleni (id_user,id_pob) VALUES (?,?)');
        $stmt->bind_param('ii', $idUser, $idPob);
        $stmt->execute();
        $stmt->close();

        cb_db_insert_perm_hist($conn, $idUser, $idPob, 1);
    }

    foreach ($toDel as $idPob) {
        $stmt = $conn->prepare('DELETE FROM pob_povoleni WHERE id_user=? AND id_pob=?');
        $stmt->bind_param('ii', $idUser, $idPob);
        $stmt->execute();
        $stmt->close();

        cb_db_insert_perm_hist($conn, $idUser, $idPob, 0);
    }

    return ['add' => count($toAdd), 'del' => count($toDel)];
}

// lib/db_povoleni.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 79