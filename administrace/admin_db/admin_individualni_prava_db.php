<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_prava_roli_db.php';

function cb_admin_individualni_prava_hledej_uzivatele(string $query): array
{
    $query = trim($query);
    if (mb_strlen($query, 'UTF-8') < 2) {
        return [];
    }

    $db = db();
    $like = '%' . $query . '%';
    $stmt = $db->prepare('
        SELECT
            u.id_user,
            u.jmeno,
            u.prijmeni,
            u.email,
            u.telefon,
            MIN(ur.id_role) AS id_role,
            GROUP_CONCAT(DISTINCT cr.role ORDER BY ur.id_role SEPARATOR ", ") AS role,
            GROUP_CONCAT(DISTINCT cs.slot ORDER BY us.id_slot SEPARATOR ", ") AS slot
        FROM user u
        LEFT JOIN user_role ur ON ur.id_user = u.id_user
        LEFT JOIN cis_role cr ON cr.id_role = ur.id_role
        LEFT JOIN user_slot us ON us.id_user = u.id_user
        LEFT JOIN cis_slot cs ON cs.id_slot = us.id_slot
        WHERE u.aktivni = 1
          AND (
              u.jmeno LIKE ?
              OR u.prijmeni LIKE ?
              OR u.email LIKE ?
              OR u.telefon LIKE ?
              OR CONCAT(u.jmeno, " ", u.prijmeni) LIKE ?
              OR CONCAT(u.prijmeni, " ", u.jmeno) LIKE ?
          )
        GROUP BY u.id_user, u.jmeno, u.prijmeni, u.email, u.telefon
        ORDER BY u.prijmeni, u.jmeno, u.id_user
        LIMIT 20
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit hledání uživatelů.');
    }

    $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $email = (string)$row['email'];
        $telefon = (string)($row['telefon'] ?? '');
        $users[] = [
            'id_user' => (int)$row['id_user'],
            'jmeno' => (string)$row['jmeno'],
            'prijmeni' => (string)$row['prijmeni'],
            'email' => $email,
            'telefon' => $telefon,
            'email_match' => mb_stripos($email, $query, 0, 'UTF-8') !== false ? $email : '',
            'telefon_match' => mb_stripos($telefon, $query, 0, 'UTF-8') !== false ? $telefon : '',
            'id_role' => (int)($row['id_role'] ?? 0),
            'role' => (string)($row['role'] ?? ''),
            'slot' => (string)($row['slot'] ?? ''),
        ];
    }
    $stmt->close();

    return $users;
}

function cb_admin_individualni_prava_data(int $idUser): array
{
    if ($idUser <= 0) {
        throw new RuntimeException('Neplatný uživatel.');
    }

    $db = db();
    $stmtUser = $db->prepare('
        SELECT
            u.id_user,
            u.jmeno,
            u.prijmeni,
            u.email,
            MIN(ur.id_role) AS id_role,
            GROUP_CONCAT(DISTINCT cr.role ORDER BY ur.id_role SEPARATOR ", ") AS role,
            GROUP_CONCAT(DISTINCT cs.slot ORDER BY us.id_slot SEPARATOR ", ") AS slot
        FROM user u
        LEFT JOIN user_role ur ON ur.id_user = u.id_user
        LEFT JOIN cis_role cr ON cr.id_role = ur.id_role
        LEFT JOIN user_slot us ON us.id_user = u.id_user
        LEFT JOIN cis_slot cs ON cs.id_slot = us.id_slot
        WHERE u.id_user = ?
        GROUP BY u.id_user, u.jmeno, u.prijmeni, u.email
        LIMIT 1
    ');
    if ($stmtUser === false) {
        throw new RuntimeException('Nelze načíst uživatele.');
    }

    $stmtUser->bind_param('i', $idUser);
    $stmtUser->execute();
    $user = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    if (!is_array($user)) {
        throw new RuntimeException('Uživatel neexistuje.');
    }

    $idRole = (int)($user['id_role'] ?? 0);
    if ($idRole <= 0) {
        throw new RuntimeException('Uživatel nemá roli.');
    }

    $base = cb_admin_prava_roli_data();

    $exceptions = [];
    $stmtExceptions = $db->prepare('
        SELECT id_pravo, povoleno
        FROM prava_vyjimky
        WHERE id_user = ?
    ');
    if ($stmtExceptions === false) {
        throw new RuntimeException('Nelze načíst výjimky.');
    }

    $stmtExceptions->bind_param('i', $idUser);
    $stmtExceptions->execute();
    $resultExceptions = $stmtExceptions->get_result();
    while ($row = $resultExceptions->fetch_assoc()) {
        $exceptions[(int)$row['id_pravo']] = (int)$row['povoleno'];
    }
    $stmtExceptions->close();

    return [
        'user' => [
            'id_user' => (int)$user['id_user'],
            'jmeno' => (string)$user['jmeno'],
            'prijmeni' => (string)$user['prijmeni'],
            'email' => (string)$user['email'],
            'id_role' => $idRole,
            'role' => (string)($user['role'] ?? ''),
            'slot' => (string)($user['slot'] ?? ''),
        ],
        'modules' => $base['modules'],
        'global' => $base['allowed'][$idRole] ?? [],
        'exceptions' => $exceptions,
    ];
}

function cb_admin_individualni_prava_uloz(int $idUser, int $idPravo, bool $vyjimka): array
{
    if ($idPravo <= 0) {
        throw new RuntimeException('Neplatné právo.');
    }

    $data = cb_admin_individualni_prava_data($idUser);
    $global = !empty($data['global'][$idPravo]);

    $db = db();
    $stmtRight = $db->prepare('SELECT COUNT(*) AS c FROM cis_prava WHERE id_pravo = ? AND aktivni = 1');
    if ($stmtRight === false) {
        throw new RuntimeException('Nelze ověřit právo.');
    }
    $stmtRight->bind_param('i', $idPravo);
    $stmtRight->execute();
    $rightRow = $stmtRight->get_result()->fetch_assoc();
    $stmtRight->close();
    if ((int)($rightRow['c'] ?? 0) !== 1) {
        throw new RuntimeException('Právo neexistuje.');
    }

    if (!$vyjimka) {
        $stmtDelete = $db->prepare('DELETE FROM prava_vyjimky WHERE id_user = ? AND id_pravo = ?');
        if ($stmtDelete === false) {
            throw new RuntimeException('Nelze smazat výjimku.');
        }
        $stmtDelete->bind_param('ii', $idUser, $idPravo);
        $stmtDelete->execute();
        $stmtDelete->close();

        return [
            'vyjimka' => false,
            'povoleno' => null,
            'global' => $global ? 1 : 0,
        ];
    }

    $povoleno = $global ? 0 : 1;
    $stmtSave = $db->prepare('
        INSERT INTO prava_vyjimky (id_user, id_pravo, povoleno)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE povoleno = VALUES(povoleno)
    ');
    if ($stmtSave === false) {
        throw new RuntimeException('Nelze uložit výjimku.');
    }

    $stmtSave->bind_param('iii', $idUser, $idPravo, $povoleno);
    $stmtSave->execute();
    $stmtSave->close();

    return [
        'vyjimka' => true,
        'povoleno' => $povoleno,
        'global' => $global ? 1 : 0,
    ];
}
