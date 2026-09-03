<?php
// helpdesk/hl_lib/hl_prava.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/moduly.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';

function cb_helpdesk_areas(): array
{
    return [
        'provoz' => ['id' => 1, 'label' => 'Provoz'],
        'hr' => ['id' => 2, 'label' => 'HR'],
        'smeny' => ['id' => 3, 'label' => 'Směny'],
        'ukoly' => ['id' => 4, 'label' => 'Úkoly'],
    ];
}

function cb_helpdesk_allowed_areas(): array
{
    $areas = [];
    foreach (cb_helpdesk_areas() as $key => $area) {
        if (cb_modul_ma_pristup($key)) {
            $areas[$key] = $area;
        }
    }

    return $areas;
}

function cb_helpdesk_area_id(string $key): int
{
    $area = cb_helpdesk_areas()[strtolower(trim($key))] ?? null;
    return is_array($area) ? (int)$area['id'] : 0;
}

function cb_helpdesk_area_label(int $id): string
{
    foreach (cb_helpdesk_areas() as $area) {
        if ((int)$area['id'] === $id) {
            return (string)$area['label'];
        }
    }

    return 'Nezařazeno';
}

function cb_helpdesk_allowed_area_condition(string $tableAlias = 'h'): string
{
    $ids = [];
    foreach (cb_helpdesk_allowed_areas() as $area) {
        $ids[] = (int)$area['id'];
    }

    if ($ids === []) {
        return '0 = 1';
    }

    return $tableAlias . '.modul IN (' . implode(', ', $ids) . ')';
}

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

function cb_helpdesk_current_company_id(): int
{
    $idUser = cb_helpdesk_current_user_id();
    if ($idUser <= 0) {
        return 1;
    }

    $idFirma = 1;
    $stmt = db()->prepare('SELECT COALESCE(id_firma, 1) FROM `user` WHERE id_user = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($idFirmaDb);
        if ($stmt->fetch()) {
            $idFirma = max(1, (int)$idFirmaDb);
        }
        $stmt->close();
    }

    return $idFirma;
}

function cb_helpdesk_ticket_company_id(mysqli $conn, int $idHelpdesk): int
{
    if ($idHelpdesk <= 0) {
        return 1;
    }

    $idFirma = 1;
    $stmt = $conn->prepare('SELECT COALESCE(id_firma, 1) FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idHelpdesk);
        $stmt->execute();
        $stmt->bind_result($idFirmaDb);
        if ($stmt->fetch()) {
            $idFirma = max(1, (int)$idFirmaDb);
        }
        $stmt->close();
    }

    return $idFirma;
}

function cb_helpdesk_is_admin(): bool
{
    return cb_pravo_ma(604);
}

function cb_helpdesk_visibility_value(mixed $value): int
{
    $visibility = (int)$value;
    if (!in_array($visibility, [0, 1, 2], true)) {
        return 0;
    }

    return $visibility;
}

function cb_helpdesk_module_id(mixed $value): int
{
    if (is_int($value) || is_float($value) || (is_string($value) && preg_match('~^\d+$~', trim($value)) === 1)) {
        $id = (int)$value;
        return $id > 0 ? $id : 1;
    }

    return match (strtolower(trim((string)$value))) {
        'hr' => 2,
        'smeny' => 3,
        'ukoly' => 4,
        default => 1,
    };
}

function cb_helpdesk_can_view(mysqli $conn, int $idHelpdesk, int $idUser): bool
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT h.id_user_zalozil, h.verejny, h.modul, COALESCE(h.id_firma, 1) AS id_firma,
               EXISTS(
                   SELECT 1
                   FROM helpdesk_sledujici s
                   WHERE s.id_helpdesk = h.id_helpdesk
                     AND s.id_user = ?
               ) AS sleduje
        FROM helpdesk h
        WHERE id_helpdesk = ?
        LIMIT 1
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }

    $stmt->bind_param('ii', $idUser, $idHelpdesk);
    $stmt->execute();
    $stmt->bind_result($idZalozil, $verejny, $modul, $idFirma, $sleduje);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        return false;
    }

    if ((int)$idFirma !== cb_helpdesk_current_company_id()) {
        return false;
    }

    if (!in_array((int)$modul, array_column(cb_helpdesk_allowed_areas(), 'id'), true)) {
        return false;
    }

    if (cb_helpdesk_is_admin()) {
        return true;
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

    if ((string)$stav === 'vyřešeno') {
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

function cb_helpdesk_visible_scope(int $idUser): array
{
    if (cb_helpdesk_is_admin()) {
        return [
            'sql' => '1=1',
            'types' => '',
            'params' => [],
        ];
    }

    return [
        'sql' => '
            (
                h.id_user_zalozil = ?
                OR h.verejny IN (1, 2)
                OR EXISTS (
                    SELECT 1
                    FROM helpdesk_sledujici sx
                    WHERE sx.id_helpdesk = h.id_helpdesk
                      AND sx.id_user = ?
                )
            )
        ',
        'types' => 'ii',
        'params' => [$idUser, $idUser],
    ];
}

function cb_helpdesk_mark_read(mysqli $conn, int $idHelpdesk, int $idUser): void
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_read
        (id_helpdesk, id_user, precteno)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE precteno = VALUES(precteno)
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        return;
    }

    $stmt->bind_param('ii', $idHelpdesk, $idUser);
    $stmt->execute();
    $stmt->close();
}

function cb_helpdesk_admin_ids(mysqli $conn, int $idFirma): array
{
    $out = [];
    $sql = '
        SELECT u.id_user
        FROM `user` u
        INNER JOIN cis_prava cp ON cp.id_pravo = 604 AND cp.aktivni = 1
        LEFT JOIN prava_global pg ON pg.id_role = u.id_role AND pg.id_pravo = 604
        LEFT JOIN prava_vyjimky pv ON pv.id_user = u.id_user AND pv.id_pravo = 604
        WHERE u.aktivni = 1
          AND COALESCE(u.id_firma, 1) = ?
          AND CASE
              WHEN pv.povoleno IS NOT NULL THEN pv.povoleno = 1
              ELSE pg.id_pravo IS NOT NULL
          END
        ORDER BY u.id_user ASC
    ';
    $stmt = $conn->prepare($sql);
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idFirma);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
            $idUser = (int)($row['id_user'] ?? 0);
            if ($idUser > 0) {
                $out[$idUser] = $idUser;
            }
        }
        $res->free();
        }
        $stmt->close();
    }

    return array_values($out);
}

// helpdesk/hl_lib/hl_prava.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
