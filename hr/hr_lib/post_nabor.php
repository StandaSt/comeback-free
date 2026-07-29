<?php
declare(strict_types=1);

/**
 * Zpracuje POST formularu akce v naboru.
 */
function hr_post_nabor(mysqli $db): void
{
    $idVd = (int)($_POST['id_vd'] ?? 0);

    try {
        hr_uloz_vd_akci(
            $db,
            $idVd,
            (int)($_POST['id_vd_stav'] ?? 0),
            (int)($_POST['id_vd_akce_typ'] ?? 0),
            trim((string)($_POST['akce_kdy'] ?? '')),
            (string)($_POST['poznamka'] ?? ''),
            hr_current_person_id($db)
        );
        $_SESSION['hr_flash'] = [
            'type' => 'success',
            'text' => 'Akce byla uložena.',
        ];
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = [
            'type' => 'error',
            'text' => $e->getMessage(),
        ];
    }

    header('Location: ?page=nabor&id_vd=' . $idVd);
    exit;
}
