<?php
// lib/helpdesk_prava.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

function cb_helpdesk_current_user_id(): int
{
    $cbUser = $_SESSION['cb_user'] ?? null;
    if (is_array($cbUser) && array_key_exists('id_user', $cbUser)) {
        return (int)$cbUser['id_user'];
    }

    return 0;
}

function cb_helpdesk_current_user_role(): int
{
    $idUser = cb_helpdesk_current_user_id();
    if ($idUser <= 0) {
        return 0;
    }

    $role = 0;
    $stmt = db()->prepare('SELECT id_role FROM `user` WHERE id_user = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($roleDb);
        if ($stmt->fetch()) {
            $role = (int)$roleDb;
        }
        $stmt->close();
    }

    return $role;
}

function cb_helpdesk_is_admin(): bool
{
    $cbUser = $_SESSION['cb_user'] ?? null;
    if (is_array($cbUser) && array_key_exists('admin', $cbUser) && (int)$cbUser['admin'] === 1) {
        return true;
    }

    $role = cb_helpdesk_current_user_role();
    if ($role > 0 && $role <= 3) {
        return true;
    }

    return false;
}

function cb_helpdesk_visibility_value(mixed $value): int
{
    $visibility = (int)$value;
    if (!in_array($visibility, [0, 1, 2], true)) {
        return 0;
    }

    return $visibility;
}

function cb_helpdesk_can_view(mysqli $conn, int $idHelpdesk, int $idUser): bool
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        return false;
    }

    if (cb_helpdesk_is_admin()) {
        return true;
    }

    $stmt = $conn->prepare('
        SELECT h.id_user_zalozil, h.verejny,
               EXISTS(
                   SELECT 1
                   FROM helpdesk_sledujici s
                   WHERE s.id_helpdesk = h.id_helpdesk
                     AND s.id_user = ?
               ) AS sleduje
        FROM helpdesk
        WHERE id_helpdesk = ?
        LIMIT 1
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }

    $stmt->bind_param('ii', $idUser, $idHelpdesk);
    $stmt->execute();
    $stmt->bind_result($idZalozil, $verejny, $sleduje);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        return false;
    }

    if ((int)$idZalozil === $idUser) {
        return true;
    }

    $visibility = cb_helpdesk_visibility_value($verejny);

    if (in_array($visibility, [1, 2], true)) {
        return true;
    }

    if ((int)$sleduje === 1) {
        return true;
    }

    return false;
}

function cb_helpdesk_can_write(mysqli $conn, int $idHelpdesk, int $idUser): bool
{
    if (!cb_helpdesk_can_view($conn, $idHelpdesk, $idUser)) {
        return false;
    }

    $stmt = $conn->prepare('SELECT stav FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }

    $stmt->bind_param('i', $idHelpdesk);
    $stmt->execute();
    $stmt->bind_result($stav);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        return false;
    }

    if (in_array((string)$stav, ['vyreseno', 'zamitnuto'], true) && !cb_helpdesk_is_admin()) {
        return false;
    }

    $stmtVisibility = $conn->prepare('SELECT id_user_zalozil, verejny FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if (!($stmtVisibility instanceof mysqli_stmt)) {
        return false;
    }

    $stmtVisibility->bind_param('i', $idHelpdesk);
    $stmtVisibility->execute();
    $stmtVisibility->bind_result($idZalozil, $verejny);
    $foundVisibility = $stmtVisibility->fetch();
    $stmtVisibility->close();

    if (!$foundVisibility) {
        return false;
    }

    if ((int)$idZalozil === $idUser || cb_helpdesk_is_admin()) {
        return true;
    }

    if (cb_helpdesk_visibility_value($verejny) === 2) {
        return false;
    }

    return true;
}

function cb_helpdesk_admin_ids(mysqli $conn): array
{
    $out = [];
    $sql = 'SELECT id_user FROM `user` WHERE aktivni = 1 AND (admin = 1 OR id_role <= 3) ORDER BY id_user ASC';
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $idUser = (int)($row['id_user'] ?? 0);
            if ($idUser > 0) {
                $out[$idUser] = $idUser;
            }
        }
        $res->free();
    }

    return array_values($out);
}

// lib/helpdesk_prava.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
