<?php
declare(strict_types=1);

/*
 * Účel souboru: AJAXově přidá jedno nové právo do vybraného modulu.
 * Po úspěchu zapíše uživatelskou akci včetně nového ID a pořadí.
 */

require_once __DIR__ . '/admin_ajax_bootstrap.php';
require_once __DIR__ . '/../admin_db/admin_editace_prav_db.php';

cb_admin_ajax_spustit(static function (): array {
    $right = cb_admin_editace_prav_pridat(
        (int)($_POST['id_modul'] ?? 0),
        (string)($_POST['nazev'] ?? ''),
        (string)($_POST['popis'] ?? '')
    );

    cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'administrace',
        'objekt' => 'cis_prava',
        'id_objektu' => (int)$right['id_pravo'],
        'pole' => 'pridani',
        'hodnota_new' => (string)$right['nazev'],
        'vysledek' => 1,
        'zdroj' => 'administrace',
        'detail' => $right,
    ]);

    return ['right' => $right];
});
