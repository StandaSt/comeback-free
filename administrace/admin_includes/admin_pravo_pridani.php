<?php
declare(strict_types=1);

/*
 * Účel souboru: Vykreslí panel pro přidání nového práva do vybraného modulu.
 * Očeká připravený seznam $adminEditacePravModuly.
 */
?>
<section class="blok admin_rights_editor_panel" data-admin-pravo-pridani>
    <h2 class="blok_title">Přidání práva</h2>

    <label class="admin_rights_editor_select">
        <span>Modul</span>
        <select data-admin-pravo-pridani-modul>
            <option value="">Vyber modul</option>
            <?php foreach ($adminEditacePravModuly as $module): ?>
                <option value="<?= h((string)$module['id_modul']) ?>"><?= h((string)$module['modul']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <form class="admin_rights_editor_add" data-admin-pravo-pridani-form hidden>
        <label>
            <span>Název</span>
            <input type="text" name="nazev" maxlength="100" required>
        </label>
        <label>
            <span>Popis</span>
            <input type="text" name="popis" maxlength="255">
        </label>
        <button type="submit">Uložit</button>
    </form>
    <p class="admin_rights_editor_status" data-admin-pravo-pridani-stav aria-live="polite"></p>
</section>
