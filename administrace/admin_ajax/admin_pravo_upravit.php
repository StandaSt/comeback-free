<?php
declare(strict_types=1);

/*
 * Účel souboru: AJAXově uloží název a popis jednoho existujícího práva.
 * Po úspěchu zaloguje původní i nové hodnoty.
 */

require_once __DIR__ . '/admin_ajax_bootstrap.php';
require_once __DIR__ . '/../admin_db/admin_editace_prav_db.php';

cb_admin_ajax_spustit(static function (): array {
    $right = cb_admin_editace_prav_upravit(
        (int)($_POST['id_pravo'] ?? 0),
        (string)($_POST['nazev'] ?? ''),
        (string)($_POST['popis'] ?? '')
    );

    cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'administrace',
        'objekt' => 'cis_prava',
        'id_objektu' => (int)$right['id_pravo'],
        'pole' => 'nazev_popis',
        'hodnota_old' => (string)$right['nazev_pred'] . ' | ' . (string)$right['popis_pred'],
        'hodnota_new' => (string)$right['nazev'] . ' | ' . (string)$right['popis'],
        'vysledek' => 1,
        'zdroj' => 'administrace',
        'detail' => $right,
    ]);

    return ['right' => $right];
});
