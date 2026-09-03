<?php
declare(strict_types=1);

/**
 * Vrati id_user aktualne prihlaseneho uzivatele.
 *
 * id_user identifikuje uzivatele pri pristupu, kontrole prav i zapisu akci.
 */
function hr_current_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? null;
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

/**
 * Vrati id_person navazane na aktualne prihlaseneho uzivatele, pokud existuje.
 */
function hr_current_person_id(mysqli $db): ?int
{
    $idUser = hr_current_user_id();
    if ($idUser <= 0) {
        throw new RuntimeException('Chybí přihlášený uživatel.');
    }

    $stmt = $db->prepare('
        SELECT id_person
        FROM hr_person
        WHERE id_user = ?
          AND aktivni = 1
        LIMIT 1
    ');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $idPerson = is_array($row) ? (int)($row['id_person'] ?? 0) : 0;
    return $idPerson > 0 ? $idPerson : null;
}
