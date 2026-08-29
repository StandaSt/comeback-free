<?php
declare(strict_types=1);

/*
 * Účel souboru: AJAXově prohodí pořadí jednoho práva s jeho sousedem.
 * Po úspěchu zaloguje obě dotčená práva a původní i nové pořadí.
 */

require_once __DIR__ . '/admin_ajax_bootstrap.php';
require_once __DIR__ . '/../admin_db/admin_editace_prav_db.php';

cb_admin_ajax_spustit(static function (): array {
    $move = cb_admin_editace_prav_posunout(
        (int)($_POST['id_pravo'] ?? 0),
        (string)($_POST['smer'] ?? '')
    );

    cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'administrace',
        'objekt' => 'cis_prava',
        'id_objektu' => (int)$move['id_pravo'],
        'pole' => 'poradi',
        'hodnota_old' => (string)$move['poradi_pred'],
        'hodnota_new' => (string)$move['poradi_nove'],
        'vysledek' => 1,
        'zdroj' => 'administrace',
        'detail' => $move,
    ]);

    return ['move' => $move];
});
