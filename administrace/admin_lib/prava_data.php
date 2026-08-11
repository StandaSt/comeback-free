<?php
declare(strict_types=1);

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
        WHERE aktivni = 1
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
        SELECT id_pravo, id_modul, nazev, popis, poradi
        FROM cis_prava
        WHERE aktivni = 1
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
        SELECT id_role, id_pravo, povoleno
        FROM prava_global
    ');
    if ($globalRes instanceof mysqli_result) {
        while ($row = $globalRes->fetch_assoc()) {
            $allowed[(int)$row['id_role']][(int)$row['id_pravo']] = (int)$row['povoleno'] === 1;
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

function cb_admin_prava_roli_uloz(int $idRole, int $idPravo, bool $povoleno): void
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

    $allowed = $povoleno ? 1 : 0;
    $stmt = $db->prepare('
        INSERT INTO prava_global (id_role, id_pravo, povoleno)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE povoleno = VALUES(povoleno)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit uložení práva.');
    }

    $stmt->bind_param('iii', $idRole, $idPravo, $allowed);
    $stmt->execute();
    $stmt->close();
}
