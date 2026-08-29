<?php
declare(strict_types=1);

/*
 * Účel souboru: AJAXově načte a vrátí editovatelnou tabulku práv vybraného modulu.
 * Endpoint pouze propojuje DB data s jejich samostatným rendererem.
 */

require_once __DIR__ . '/admin_ajax_bootstrap.php';
require_once __DIR__ . '/../admin_db/admin_editace_prav_db.php';
require_once __DIR__ . '/../admin_includes/admin_prava_editace_tabulka.php';

cb_admin_ajax_spustit(static function (): array {
    $rights = cb_admin_editace_prav_prava_modulu((int)($_POST['id_modul'] ?? 0));

    return ['html' => cb_admin_prava_editace_tabulka_html($rights)];
});
