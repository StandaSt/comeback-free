<?php
// Nastaveni HR spravuje pouze pracovni pozice; pobocky patri do modulu Provoz.
declare(strict_types=1);

$hrPozice = hr_nastaveni_pozice($db);
?>
<div class="hr_settings_grid">
    <section class="hr_panel">
        <div class="hr_panel_header"><h2>Pozice</h2></div>
        <form class="hr_settings_add" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nastaveni')) ?>">
            <input type="hidden" name="cb_action" value="hr_pozice_pridat">
            <label class="hr_form_label"><span>Název pozice</span><input type="text" name="slot" maxlength="100" required></label>
            <button class="hr_primary_button" type="submit">Přidat pozici</button>
        </form>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead><tr><th>ID</th><th>Pozice</th><th>Stav</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($hrPozice as $pozice): ?>
                    <tr><td><?= h((string)$pozice['id_slot']) ?></td><td><strong><?= h($pozice['slot']) ?></strong></td><td><?= $pozice['aktivni'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td><td>
                        <form class="hr_row_action_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nastaveni')) ?>">
                            <input type="hidden" name="cb_action" value="hr_pozice_zmenit_stav"><input type="hidden" name="id_slot" value="<?= h((string)$pozice['id_slot']) ?>"><input type="hidden" name="aktivni" value="<?= $pozice['aktivni'] === 1 ? '0' : '1' ?>">
                            <button class="hr_secondary_button" type="submit"><?= $pozice['aktivni'] === 1 ? 'Deaktivovat' : 'Aktivovat' ?></button>
                        </form>
                    </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
