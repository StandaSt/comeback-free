<?php
declare(strict_types=1);

/*
 * Účel souboru: Sestaví kostru stránky Editovat práva z jednoúčelových panelů.
 * Příprava dat patří do admin_lib, vykreslení panelů do admin_includes.
 */

require_once __DIR__ . '/../admin_lib/admin_editace_prav_data.php';

$adminEditacePravData = cb_admin_editace_prav_data();
$adminEditacePravModuly = $adminEditacePravData['moduly'];
?>
<div
    class="admin_rights_editor"
    data-admin-editace-prav
    data-url-nacist="<?= h(cb_root_url('administrace/admin_ajax/admin_prava_modul_nacist.php')) ?>"
    data-url-pridat="<?= h(cb_root_url('administrace/admin_ajax/admin_pravo_pridat.php')) ?>"
    data-url-upravit="<?= h(cb_root_url('administrace/admin_ajax/admin_pravo_upravit.php')) ?>"
    data-url-posunout="<?= h(cb_root_url('administrace/admin_ajax/admin_pravo_posunout.php')) ?>"
>
    <?php require __DIR__ . '/../admin_includes/admin_pravo_pridani.php'; ?>
    <?php require __DIR__ . '/../admin_includes/admin_prava_editace.php'; ?>
</div>
