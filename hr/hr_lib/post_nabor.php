<?php
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/hr_audit.php';

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
            hr_current_user_id(),
            $_POST
        );
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=nabor'),
            true,
            'Akce byla uložena.'
        );
    } catch (Throwable $e) {
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=nabor&id_vd=' . rawurlencode((string)$idVd)),
            false,
            cb_hr_chyba_text($e, 'Uložení náborové akce', ['table' => 'hr_vd_akce']),
            $_POST
        );
    }
}
