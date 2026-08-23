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
            (int)($_POST['id_vd_akce_vysledek'] ?? 0),
            trim((string)($_POST['termin_date'] ?? '')),
            trim((string)($_POST['termin_time'] ?? '')),
            (string)($_POST['poznamka'] ?? ''),
            hr_current_person_id($db),
            $_POST
        );
        $_SESSION['hr_flash'] = [
            'type' => 'hr_success',
            'text' => 'Akce byla uložena.',
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=nabor'), true, 303);
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = [
            'type' => 'hr_error',
            'text' => $e->getMessage(),
        ];
        header('Location: ' . cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$idVd)), true, 303);
    }

    exit;
}
