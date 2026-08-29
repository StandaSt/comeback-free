<?php
declare(strict_types=1);

/*
 * Účel souboru: Vykreslí panel pro výběr modulu a prostor jeho editovatelné tabulky práv.
 * Samotnou tabulku vrací samostatný include volaný AJAX endpointem.
 */
?>
<section class="blok admin_rights_editor_panel" data-admin-prava-editace>
    <h2 class="blok_title">Editace práv</h2>

    <label class="admin_rights_editor_select">
        <span>Modul</span>
        <select data-admin-prava-editace-modul>
            <option value="">Vyber modul</option>
            <?php foreach ($adminEditacePravModuly as $module): ?>
                <option value="<?= h((string)$module['id_modul']) ?>"><?= h((string)$module['modul']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <div class="admin_rights_editor_table" data-admin-prava-editace-tabulka></div>
</section>
