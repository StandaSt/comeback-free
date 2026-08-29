<?php
declare(strict_types=1);

/*
 * Nacita a meni globalni prava roli v administraci.
 * Globalni pravo je dane existenci dvojice id_role a id_pravo.
 */

function cb_admin_prava_roli_data(): array
{
    $db = db();

    $roles = [];
    $roleRes = $db->query('
        SELECT id_role, role
        FROM cis_role
        WHERE aktivni = 1
        ORDER BY id_role
    ');
    if ($roleRes instanceof mysqli_result) {
        while ($row = $roleRes->fetch_assoc()) {
            $roles[] = [
                'id_role' => (int)$row['id_role'],
                'role' => (string)$row['role'],
            ];
        }
        $roleRes->free();
    }

    $modules = [];
    $modRes = $db->query('
        SELECT id_modul, modul, poradi
        FROM cis_moduly
        ORDER BY poradi, id_modul
    ');
    if ($modRes instanceof mysqli_result) {
        while ($row = $modRes->fetch_assoc()) {
            $idModul = (int)$row['id_modul'];
            $modules[$idModul] = [
                'id_modul' => $idModul,
                'modul' => (string)$row['modul'],
                'rights' => [],
            ];
        }
        $modRes->free();
    }

    $rights = [];
    $rightRes = $db->query('
        SELECT id_pravo, id_modul, nazev, popis, poradi, aktivni
        FROM cis_prava
        ORDER BY id_modul, poradi, id_pravo
    ');
    if ($rightRes instanceof mysqli_result) {
        while ($row = $rightRes->fetch_assoc()) {
            $idModul = (int)$row['id_modul'];
            $right = [
                'id_pravo' => (int)$row['id_pravo'],
                'id_modul' => $idModul,
                'nazev' => (string)$row['nazev'],
                'popis' => (string)($row['popis'] ?? ''),
                'aktivni' => (int)$row['aktivni'] === 1,
            ];
            $rights[] = $right;
            if (isset($modules[$idModul])) {
                $modules[$idModul]['rights'][] = $right;
            }
        }
        $rightRes->free();
    }

    $allowed = [];
    $globalRes = $db->query('
        SELECT id_role, id_pravo
        FROM prava_global
    ');
    if ($globalRes instanceof mysqli_result) {
        while ($row = $globalRes->fetch_assoc()) {
            $allowed[(int)$row['id_role']][(int)$row['id_pravo']] = true;
        }
        $globalRes->free();
    }

    return [
        'roles' => $roles,
        'modules' => $modules,
        'rights' => $rights,
        'allowed' => $allowed,
    ];
}

function cb_admin_pravo_aktivni_uloz(int $idPravo, bool $aktivni): array
{
    if ($idPravo <= 0) {
        throw new RuntimeException('Neplatné ID práva.');
    }

    $db = db();
    $stmtLoad = $db->prepare('SELECT nazev, aktivni FROM cis_prava WHERE id_pravo = ? LIMIT 1');
    if ($stmtLoad === false) {
        throw new RuntimeException('Nelze připravit načtení práva.');
    }
    $stmtLoad->bind_param('i', $idPravo);
    $stmtLoad->execute();
    $row = $stmtLoad->get_result()->fetch_assoc();
    $stmtLoad->close();

    if (!is_array($row)) {
        throw new RuntimeException('Právo ID ' . $idPravo . ' neexistuje v cis_prava.');
    }

    $previous = (int)$row['aktivni'] === 1;
    if ($previous !== $aktivni) {
        $activeValue = $aktivni ? 1 : 0;
        $stmtUpdate = $db->prepare('UPDATE cis_prava SET aktivni = ? WHERE id_pravo = ?');
        if ($stmtUpdate === false) {
            throw new RuntimeException('Nelze připravit změnu aktivity práva.');
        }
        $stmtUpdate->bind_param('ii', $activeValue, $idPravo);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }

    return [
        'id_pravo' => $idPravo,
        'nazev' => (string)$row['nazev'],
        'aktivni' => $aktivni,
        'aktivni_pred' => $previous,
    ];
}

function cb_admin_prava_roli_uloz(int $idRole, int $idPravo, bool $allowed): void
{
    if ($idRole <= 0 || $idPravo <= 0) {
        throw new RuntimeException('Neplatné právo.');
    }

    $db = db();

    $check = $db->prepare('
        SELECT
            (SELECT COUNT(*) FROM cis_role WHERE id_role = ? AND aktivni = 1) AS role_ok,
            (SELECT COUNT(*) FROM cis_prava WHERE id_pravo = ? AND aktivni = 1) AS pravo_ok
    ');
    if ($check === false) {
        throw new RuntimeException('Nelze ověřit právo.');
    }

    $check->bind_param('ii', $idRole, $idPravo);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if ((int)($row['role_ok'] ?? 0) !== 1 || (int)($row['pravo_ok'] ?? 0) !== 1) {
        throw new RuntimeException('Právo nebo role neexistuje.');
    }

    $db->begin_transaction();

    try {
        if ($allowed) {
            $stmt = $db->prepare('INSERT IGNORE INTO prava_global (id_role, id_pravo) VALUES (?, ?)');
        } else {
            $stmt = $db->prepare('DELETE FROM prava_global WHERE id_role = ? AND id_pravo = ?');
        }
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit uložení práva.');
        }

        $stmt->bind_param('ii', $idRole, $idPravo);
        $stmt->execute();
        $stmt->close();

        // Vyjimka zustava jen tehdy, kdyz se lisi od noveho globalniho prava.
        $duplicateValue = $allowed ? 1 : 0;
        $stmtExceptions = $db->prepare('
            DELETE vyjimka
            FROM prava_vyjimky AS vyjimka
            INNER JOIN (
                SELECT id_user
                FROM user_role
                GROUP BY id_user
                HAVING MIN(id_role) = ?
            ) AS efektivni_role ON efektivni_role.id_user = vyjimka.id_user
            WHERE vyjimka.id_pravo = ?
              AND vyjimka.povoleno = ?
        ');
        if ($stmtExceptions === false) {
            throw new RuntimeException('Nelze pripravit uklid vyjimek prava.');
        }

        $stmtExceptions->bind_param('iii', $idRole, $idPravo, $duplicateValue);
        $stmtExceptions->execute();
        $stmtExceptions->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
