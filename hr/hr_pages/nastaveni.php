<?php
declare(strict_types=1);

$hrPozice = hr_nastaveni_pozice($db);
$hrPobockyData = hr_nastaveni_pobocky($db, $cbHrIdUser);
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

    <section class="hr_panel hr_panel_wide">
        <div class="hr_panel_header"><h2>Pobočky</h2></div>
        <form class="hr_settings_add hr_settings_branch_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nastaveni')) ?>">
            <input type="hidden" name="cb_action" value="hr_pobocka_pridat">
            <label class="hr_form_label hr_settings_branch_company"><span>Firma</span><select name="id_firma" required><option value="">Vyberte</option><?php foreach ($hrPobockyData['firmy'] as $firma): ?><option value="<?= h((string)$firma['id_firma']) ?>"><?= h($firma['nazev']) ?></option><?php endforeach; ?></select></label>
            <label class="hr_form_label hr_settings_branch_code"><span>Kód</span><input type="text" name="kod" maxlength="20" required></label>
            <label class="hr_form_label hr_settings_branch_name"><span>Název</span><input type="text" name="nazev" maxlength="100" required></label>
            <label class="hr_form_label hr_settings_branch_street"><span>Ulice</span><input type="text" name="ulice" maxlength="150"></label>
            <label class="hr_form_label hr_settings_branch_city"><span>Město</span><input type="text" name="mesto" maxlength="100" required></label>
            <label class="hr_form_label hr_settings_branch_area"><span>Oblast</span><input type="text" name="oblast" maxlength="50"></label>
            <label class="hr_form_label hr_settings_branch_postcode"><span>PSČ</span><input type="text" name="psc" inputmode="numeric" maxlength="6" required></label>
            <button class="hr_primary_button" type="submit">Přidat pobočku</button>
        </form>
        <div class="hr_table_wrap hr_settings_branch_table_wrap">
            <table class="hr_table hr_settings_branch_table">
                <thead><tr><th>ID</th><th>Firma</th><th>Kód</th><th>Pobočka</th><th>Adresa</th><th>Stav</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($hrPobockyData['pobocky'] as $pobocka): ?>
                    <?php $adresa = trim(implode(', ', array_filter([(string)$pobocka['ulice'], ((int)$pobocka['psc'] > 0 ? (string)$pobocka['psc'] . ' ' : '') . (string)$pobocka['mesto']]))); ?>
                    <tr><td><?= h((string)$pobocka['id_pob']) ?></td><td><?= h((string)$pobocka['firma']) ?></td><td><?= h((string)$pobocka['kod']) ?></td><td><strong><?= h((string)$pobocka['nazev']) ?></strong></td><td><?= h($adresa) ?></td><td><?= (int)$pobocka['aktivni'] === 1 ? 'Aktivní' : 'Neaktivní' ?></td><td>
                        <form class="hr_row_action_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=nastaveni')) ?>">
                            <input type="hidden" name="cb_action" value="hr_pobocka_zmenit_stav"><input type="hidden" name="id_pob" value="<?= h((string)$pobocka['id_pob']) ?>"><input type="hidden" name="aktivni" value="<?= (int)$pobocka['aktivni'] === 1 ? '0' : '1' ?>">
                            <button class="hr_secondary_button" type="submit"><?= (int)$pobocka['aktivni'] === 1 ? 'Deaktivovat' : 'Aktivovat' ?></button>
                        </form>
                    </td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
