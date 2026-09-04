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
        SELECT
            pravo.id_pravo,
            pravo.id_modul,
            pravo.nazev,
            pravo.popis,
            pravo.poradi,
            pravo.aktivni,
            oznaceni.id_pravo AS id_pravo_aplikovano
        FROM cis_prava AS pravo
        LEFT JOIN admin_prava_on_off AS oznaceni
            ON oznaceni.id_pravo = pravo.id_pravo
        ORDER BY pravo.id_modul, pravo.poradi, pravo.id_pravo
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
                'aplikovano' => $row['id_pravo_aplikovano'] !== null,
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

function cb_admin_pravo_aplikovano_uloz(int $idPravo, bool $aplikovano): array
{
    if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(106)) {
        throw new RuntimeException('Nemáte právo označit aplikaci práva.');
    }
    if ($idPravo <= 0) {
        throw new RuntimeException('Neplatné ID práva.');
    }

    $db = db();
    $stmtLoad = $db->prepare('
        SELECT
            pravo.nazev,
            CASE WHEN oznaceni.id_pravo IS NULL THEN 0 ELSE 1 END AS aplikovano
        FROM cis_prava AS pravo
        LEFT JOIN admin_prava_on_off AS oznaceni
            ON oznaceni.id_pravo = pravo.id_pravo
        WHERE pravo.id_pravo = ?
        LIMIT 1
    ');
    if ($stmtLoad === false) {
        throw new RuntimeException('Nelze připravit načtení označení práva.');
    }
    $stmtLoad->bind_param('i', $idPravo);
    $stmtLoad->execute();
    $row = $stmtLoad->get_result()->fetch_assoc();
    $stmtLoad->close();

    if (!is_array($row)) {
        throw new RuntimeException('Právo ID ' . $idPravo . ' neexistuje v cis_prava.');
    }

    $previous = (int)$row['aplikovano'] === 1;
    if ($previous !== $aplikovano) {
        if ($aplikovano) {
            $stmtSave = $db->prepare('INSERT IGNORE INTO admin_prava_on_off (id_pravo) VALUES (?)');
        } else {
            $stmtSave = $db->prepare('DELETE FROM admin_prava_on_off WHERE id_pravo = ?');
        }
        if ($stmtSave === false) {
            throw new RuntimeException('Nelze připravit uložení označení práva.');
        }
        $stmtSave->bind_param('i', $idPravo);
        $stmtSave->execute();
        $stmtSave->close();
    }

    return [
        'id_pravo' => $idPravo,
        'nazev' => (string)$row['nazev'],
        'aplikovano' => $aplikovano,
        'aplikovano_pred' => $previous,
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

        // Výjimka zůstává jen tehdy, když se liší od sjednoceného práva všech rolí uživatele.
        $duplicateValue = $allowed ? 1 : 0;
        $stmtExceptions = $db->prepare('
            DELETE vyjimka
            FROM prava_vyjimky AS vyjimka
            WHERE vyjimka.id_pravo = ?
              AND vyjimka.povoleno = ?
              AND (
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM user_role AS ur
                        INNER JOIN prava_global AS pg
                            ON pg.id_role = ur.id_role
                           AND pg.id_pravo = vyjimka.id_pravo
                        WHERE ur.id_user = vyjimka.id_user
                    ) THEN 1 ELSE 0 END
                  ) = ?
        ');
        if ($stmtExceptions === false) {
            throw new RuntimeException('Nelze pripravit uklid vyjimek prava.');
        }

        $stmtExceptions->bind_param('iii', $idPravo, $duplicateValue, $duplicateValue);
        $stmtExceptions->execute();
        $stmtExceptions->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
