<?php
// Stranka pro pridani a upravu pobocek dostupnych uzivateli s pravem 217.
declare(strict_types=1);

require_once __DIR__ . '/../lib/nastaveni_pobocky.php';
cb_provoz_nastaveni_vyzaduj_pravo();
if (!isset($cbNastaveniPobocky) || !is_array($cbNastaveniPobocky)) {
    $cbNastaveniPobocky = cb_provoz_nastaveni_pobocky_data(db(), cb_provoz_nastaveni_user_id());
}
$cbNastaveniToken = cb_pobocka_provoz_token();
$cbNastaveniFormResult = $_SESSION['cb_form_result'] ?? null;
unset($_SESSION['cb_form_result']);
$cbNastaveniAction = cb_root_url('index.php?m=provoz&page=nastaveni_pobocky');
$cbNastaveniTimes = cb_pobocka_provoz_closing_time_options();

// Vykresli spolecna pole adresy, kontaktu a zaviraci doby.
$cbNastaveniFields = static function (array $pobocka) use ($cbNastaveniTimes): void {
    ?>
    <div class="provoz_branch_fields">
        <label class="provoz_branch_field_code"><span>Kód</span><input type="text" name="kod" maxlength="20" required value="<?= h((string)($pobocka['kod'] ?? '')) ?>"></label>
        <label class="provoz_branch_field_name"><span>Název</span><input type="text" name="nazev" maxlength="100" required value="<?= h((string)($pobocka['nazev'] ?? '')) ?>"></label>
        <label class="provoz_branch_field_street"><span>Ulice</span><input type="text" name="ulice" maxlength="150" value="<?= h((string)($pobocka['ulice'] ?? '')) ?>"></label>
        <label class="provoz_branch_field_city"><span>Město</span><input type="text" name="mesto" maxlength="100" required value="<?= h((string)($pobocka['mesto'] ?? '')) ?>"></label>
        <label class="provoz_branch_field_area"><span>Oblast</span><input type="text" name="oblast" maxlength="50" value="<?= h((string)($pobocka['oblast'] ?? '')) ?>"></label>
        <label class="provoz_branch_field_postcode"><span>PSČ</span><input type="text" name="psc" inputmode="numeric" maxlength="6" required value="<?= isset($pobocka['psc']) ? h(sprintf('%05d', (int)$pobocka['psc'])) : '' ?>"></label>
        <label class="provoz_branch_field_contacts"><span>Telefony</span><span class="provoz_branch_contact_tags"><?php foreach ((array)($pobocka['telefon'] ?? []) as $telefon): ?><input class="provoz_branch_contact_tag" type="tel" name="telefon[]" maxlength="30" value="<?= h((string)$telefon) ?>"><?php endforeach; ?><input class="provoz_branch_contact_tag is-new" type="tel" name="telefon[]" maxlength="30" placeholder="+ přidat telefon"></span></label>
        <label class="provoz_branch_field_contacts"><span>E-maily</span><span class="provoz_branch_contact_tags"><?php foreach ((array)($pobocka['email'] ?? []) as $email): ?><input class="provoz_branch_contact_tag is-email" type="email" name="email[]" maxlength="150" value="<?= h((string)$email) ?>"><?php endforeach; ?><input class="provoz_branch_contact_tag is-email is-new" type="email" name="email[]" maxlength="150" placeholder="+ přidat e-mail"></span></label>
    </div>
    <fieldset class="provoz_branch_hours">
        <legend>Zavírací doba pobočky</legend>
        <?php foreach (cb_pobocka_provoz_weekdays() as $day): ?>
            <?php $key = (string)$day['key']; $selected = (string)($pobocka[$key] ?? '01:00'); ?>
            <label><span><?= h((string)$day['label']) ?></span><span class="provoz_branch_time"><b>10:00–</b><select name="<?= h($key) ?>" required><?php foreach ($cbNastaveniTimes as $time): ?><option value="<?= h($time) ?>"<?= $time === $selected ? ' selected' : '' ?>><?= h($time) ?></option><?php endforeach; ?></select></span></label>
        <?php endforeach; ?>
    </fieldset>
    <?php
};
?>
<?php if (is_array($cbNastaveniFormResult) && trim((string)($cbNastaveniFormResult['message'] ?? '')) !== ''): ?>
    <div class="provoz_settings_flash <?= !empty($cbNastaveniFormResult['success']) ? 'is-success' : 'is-error' ?>" role="status"><?= h((string)$cbNastaveniFormResult['message']) ?></div>
<?php endif; ?>

<details class="provoz_branch_add">
    <summary>Přidat pobočku</summary>
    <form method="post" action="<?= h($cbNastaveniAction) ?>">
        <input type="hidden" name="cb_action" value="provoz_pobocka_pridat">
        <input type="hidden" name="token" value="<?= h($cbNastaveniToken) ?>">
        <label class="provoz_branch_company"><span>Firma</span><select name="id_firma" required><option value="">Vyberte firmu</option><?php foreach ($cbNastaveniPobocky['firmy'] as $firma): ?><option value="<?= h((string)$firma['id_firma']) ?>"><?= h($firma['nazev']) ?></option><?php endforeach; ?></select></label>
        <?php $cbNastaveniFields([]); ?>
        <button class="provoz_settings_primary" type="submit">Přidat pobočku</button>
    </form>
</details>

<?php foreach ($cbNastaveniPobocky['pobocky'] as $pobocka): ?>
    <details class="provoz_branch_card<?= (int)$pobocka['aktivni'] === 1 ? '' : ' is-inactive' ?>">
            <summary><strong><?= h((string)$pobocka['nazev']) ?></strong><em><?= (int)$pobocka['aktivni'] === 1 ? 'Aktivní' : 'Neaktivní' ?></em></summary>
            <form method="post" action="<?= h($cbNastaveniAction) ?>">
                <input type="hidden" name="token" value="<?= h($cbNastaveniToken) ?>">
                <input type="hidden" name="id_pob" value="<?= h((string)$pobocka['id_pob']) ?>">
                <input type="hidden" name="aktivni" value="<?= (int)$pobocka['aktivni'] === 1 ? '0' : '1' ?>">
                <?php $cbNastaveniFields($pobocka); ?>
                <div class="provoz_branch_actions">
                    <button class="provoz_settings_secondary" type="submit" name="cb_action" value="provoz_pobocka_zmenit_stav" formnovalidate><?= (int)$pobocka['aktivni'] === 1 ? 'Deaktivovat pobočku' : 'Aktivovat pobočku' ?></button>
                    <button class="provoz_settings_primary" type="submit" name="cb_action" value="provoz_pobocka_ulozit">Uložit změny</button>
                </div>
            </form>
    </details>
<?php endforeach; ?>
