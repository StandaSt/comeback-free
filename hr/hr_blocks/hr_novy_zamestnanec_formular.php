<?php
declare(strict_types=1);

/* Formulář alternativního založení zaměstnance; role v IS se odvozuje z volitelné funkce. */

$formInput = is_array($formInput ?? null) ? $formInput : [];
// Do formuláře vracíme jen skalární hodnoty; soubor fotografie prohlížeč obnovit nesmí.
$formValue = static function (string $key, string $default = '') use ($formInput): string {
    $value = $formInput[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
};
$selectedBranches = [];
foreach ((array)($formInput['id_pob'] ?? []) as $branchId) {
    if (is_scalar($branchId) && (int)$branchId > 0) {
        $selectedBranches[(string)(int)$branchId] = true;
    }
}
$mainBranchValue = $formValue('id_pob_hlavni');
?>
<section class="hr_panel">
    <div class="hr_panel_header"><div><h2 class="hr_panel_title">Manuální vytvoření zaměstnance</h2><p class="hr_muted">Alternativní založení zaměstnance mimo náborový proces VD/ND</p></div></div>

    <form class="hr_form hr_new_employee_form" method="post" enctype="multipart/form-data" action="<?= h(cb_root_url('index.php?m=hr&page=novy_zamestnanec')) ?>">
        <input type="hidden" name="cb_action" value="hr_zamestnanec_ulozit">

        <section class="hr_new_employee_section">
            <h3 class="hr_employee_edit_subtitle">Osobní údaje</h3>
            <table class="hr_new_employee_table"><tbody>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--title"><label class="hr_form_label"><span>Titul před jménem</span><select name="titul_pred"><option value=""></option><?php foreach ($titulyPred as $titul): ?><option value="<?= h($titul['label']) ?>"<?= $formValue('titul_pred') === (string)$titul['label'] ? ' selected' : '' ?>><?= h($titul['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--name"><label class="hr_form_label"><span>Jméno</span><input name="jmeno" required maxlength="60" autocomplete="given-name" value="<?= h($formValue('jmeno')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--second-name"><label class="hr_form_label"><span>Druhé jméno</span><input name="druhe_jmeno" maxlength="60" value="<?= h($formValue('druhe_jmeno')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--surname"><label class="hr_form_label"><span>Příjmení</span><input name="prijmeni" required maxlength="80" autocomplete="family-name" value="<?= h($formValue('prijmeni')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--title"><label class="hr_form_label"><span>Titul za jménem</span><select name="titul_za"><option value=""></option><?php foreach ($titulyZa as $titul): ?><option value="<?= h($titul['label']) ?>"<?= $formValue('titul_za') === (string)$titul['label'] ? ' selected' : '' ?>><?= h($titul['label']) ?></option><?php endforeach; ?></select></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--maiden"><label class="hr_form_label"><span>Rodné příjmení</span><input name="rodne_prijmeni" maxlength="80" value="<?= h($formValue('rodne_prijmeni')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--date"><label class="hr_form_label"><span>Datum narození</span><input name="datum_narozeni" data-cb-date placeholder="DD.MM.RRRR" value="<?= h($formValue('datum_narozeni')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--birthplace"><label class="hr_form_label"><span>Místo narození</span><input name="misto_narozeni" maxlength="120" value="<?= h($formValue('misto_narozeni')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--birth-number"><label class="hr_form_label"><span>Rodné číslo</span><input name="rodne_cislo" maxlength="11" placeholder="YYMMDD/XXXX" data-birth-number value="<?= h($formValue('rodne_cislo')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--nationality"><label class="hr_form_label"><span>Státní občanství</span><input name="statni_obcanstvi" maxlength="100" value="<?= h($formValue('statni_obcanstvi')) ?>"></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--id-card"><label class="hr_form_label"><span>Číslo občanského průkazu</span><input name="cislo_obcanskeho_prukazu" maxlength="30" value="<?= h($formValue('cislo_obcanskeho_prukazu')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--insurer"><label class="hr_form_label"><span>Zdravotní pojišťovna</span><select name="zdr_poj"><option value="">Vyberte</option><?php foreach ($healthInsurers as $healthInsurer): ?><option value="<?= h((string)$healthInsurer['kod']) ?>"<?= $formValue('zdr_poj') === (string)$healthInsurer['kod'] ? ' selected' : '' ?>><?= h($healthInsurer['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td colspan="3"><fieldset class="hr_form_label hr_gender_field"><legend>Pohlaví</legend><span class="hr_gender_choices"><label><input type="radio" name="pohlavi" value="muž" required<?= $formValue('pohlavi') === 'muž' ? ' checked' : '' ?>> Muž</label><label><input type="radio" name="pohlavi" value="žena"<?= $formValue('pohlavi') === 'žena' ? ' checked' : '' ?>> Žena</label><label><input type="radio" name="pohlavi" value="jiné"<?= $formValue('pohlavi') === 'jiné' ? ' checked' : '' ?>> Jiné</label><label><input type="radio" name="pohlavi" value="neuvedeno"<?= $formValue('pohlavi') === 'neuvedeno' ? ' checked' : '' ?>> Neuvedeno</label></span></fieldset></td>
                </tr>
                <tr><td colspan="5"><div class="hr_new_employee_detail_groups">
                    <fieldset class="hr_new_employee_detail_group"><legend>Bydliště dle OP</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--address"><label class="hr_form_label"><span>Ulice</span><input name="adresa_ulice" maxlength="120" autocomplete="street-address" value="<?= h($formValue('adresa_ulice')) ?>"></label><label class="hr_form_label"><span>Číslo popisné</span><input name="adresa_cp" maxlength="20" value="<?= h($formValue('adresa_cp')) ?>"></label><label class="hr_form_label"><span>Město</span><input name="adresa_mesto" maxlength="100" autocomplete="address-level2" value="<?= h($formValue('adresa_mesto')) ?>"></label><label class="hr_form_label"><span>PSČ</span><input name="adresa_psc" maxlength="20" autocomplete="postal-code" value="<?= h($formValue('adresa_psc')) ?>"></label><label class="hr_form_label"><span>Stát</span><input name="adresa_stat" maxlength="100" autocomplete="country-name" value="<?= h($formValue('adresa_stat')) ?>"></label></div></fieldset>
                    <fieldset class="hr_new_employee_detail_group"><legend>Doručovací adresa</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--address"><label class="hr_form_label"><span>Ulice</span><input name="dorucovaci_ulice" maxlength="120" value="<?= h($formValue('dorucovaci_ulice')) ?>"></label><label class="hr_form_label"><span>Číslo popisné</span><input name="dorucovaci_cp" maxlength="20" value="<?= h($formValue('dorucovaci_cp')) ?>"></label><label class="hr_form_label"><span>Město</span><input name="dorucovaci_mesto" maxlength="100" value="<?= h($formValue('dorucovaci_mesto')) ?>"></label><label class="hr_form_label"><span>PSČ</span><input name="dorucovaci_psc" maxlength="20" value="<?= h($formValue('dorucovaci_psc')) ?>"></label><label class="hr_form_label"><span>Stát</span><input name="dorucovaci_stat" maxlength="100" value="<?= h($formValue('dorucovaci_stat')) ?>"></label></div></fieldset>
                    <fieldset class="hr_new_employee_detail_group"><legend>Nouzový kontakt (nepovinný)</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--emergency"><label class="hr_form_label"><span>Jméno</span><input name="nouzovy_jmeno" maxlength="150" value="<?= h($formValue('nouzovy_jmeno')) ?>"></label><label class="hr_form_label"><span>Vztah</span><input name="nouzovy_vztah" maxlength="80" value="<?= h($formValue('nouzovy_vztah')) ?>"></label><label class="hr_form_label"><span>Telefon</span><input name="nouzovy_telefon" maxlength="30" autocomplete="tel" value="<?= h($formValue('nouzovy_telefon')) ?>"></label><label class="hr_form_label"><span>E-mail</span><input type="email" name="nouzovy_email" maxlength="150" value="<?= h($formValue('nouzovy_email')) ?>"></label></div></fieldset>
                </div></td></tr>
                <tr class="hr_new_employee_photo_row">
                    <td colspan="2"><div class="hr_new_employee_photo"><label class="hr_form_label"><span>Fotografie zaměstnance</span><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-photo-input></label><button class="hr_photo_crop_trigger" type="button" data-photo-crop-open disabled>Výřez</button></div></td>
                    <td><figure class="hr_new_employee_photo_preview" data-photo-preview hidden><img alt="Náhled fotografie zaměstnance"></figure></td>
                    <td colspan="2" class="hr_new_employee_field hr_new_employee_field--note"><label class="hr_form_label"><span>Poznámka</span><textarea name="poznamka" maxlength="1000" rows="3"><?= h($formValue('poznamka')) ?></textarea></label></td>
                </tr>
            </tbody></table>
        </section>

        <section class="hr_new_employee_section">
            <h3 class="hr_employee_edit_subtitle">Pracovní a kontaktní údaje</h3>
            <table class="hr_new_employee_table"><tbody>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--personal-number"><label class="hr_form_label"><span>Osobní číslo</span><input name="osobni_cislo" maxlength="20" value="<?= h($formValue('osobni_cislo')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--relation"><label class="hr_form_label"><span>Typ vztahu</span><select name="id_pracovni_vztah_typ" required><option value="">Vyberte</option><?php foreach ($vztahy as $vztah): ?><option value="<?= h($vztah['id']) ?>"<?= $formValue('id_pracovni_vztah_typ') === (string)$vztah['id'] ? ' selected' : '' ?>><?= h($vztah['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--start"><label class="hr_form_label"><span>Datum nástupu</span><input type="date" name="datum_nastupu" required value="<?= h($formValue('datum_nastupu', date('Y-m-d'))) ?>"></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--branches"><div class="hr_form_label"><span>Pobočky</span><div class="hr_employee_branch_picker" data-hr-branch-picker><button class="hr_employee_branch_button" type="button" data-hr-branch-toggle>Vyberte pobočky</button><div class="hr_employee_branch_panel" data-hr-branch-panel hidden><?php foreach ($pobocky as $pobocka): ?><label><input type="checkbox" name="id_pob[]" value="<?= h($pobocka['id']) ?>" data-hr-branch-option data-hr-branch-name="<?= h($pobocka['label']) ?>"<?= isset($selectedBranches[(string)(int)$pobocka['id']]) ? ' checked' : '' ?>> <?= h($pobocka['label']) ?></label><?php endforeach; ?></div></div></div></td>
                    <td class="hr_new_employee_field hr_new_employee_field--main-branch"><label class="hr_form_label"><span>Hlavní pobočka</span><select name="id_pob_hlavni" required<?= $selectedBranches === [] ? ' disabled' : '' ?> data-hr-main-branch><?php if ($selectedBranches === []): ?><option value="">Nejprve vyberte pobočky</option><?php else: ?><option value="">Vyberte</option><?php foreach ($pobocky as $pobocka): ?><?php if (isset($selectedBranches[(string)(int)$pobocka['id']])): ?><option value="<?= h($pobocka['id']) ?>"<?= $mainBranchValue === (string)$pobocka['id'] ? ' selected' : '' ?>><?= h($pobocka['label']) ?></option><?php endif; ?><?php endforeach; ?><?php endif; ?></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--slot"><label class="hr_form_label"><span>Pracovní slot</span><select name="id_slot"><option value="">Bez slotu</option><?php foreach ($sloty as $slot): ?><option value="<?= h($slot['id']) ?>"<?= $formValue('id_slot') === (string)$slot['id'] ? ' selected' : '' ?>><?= h($slot['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td class="hr_new_employee_field"><label class="hr_form_label"><span>Funkce</span><select name="id_funkce"><option value="">Bez funkce</option><?php foreach ($funkce as $polozka): ?><?php if ((int)$polozka['id_role'] !== 3 || cb_pravo_ma(316)): ?><option value="<?= h((string)$polozka['id_funkce']) ?>"<?= $formValue('id_funkce') === (string)$polozka['id_funkce'] ? ' selected' : '' ?>><?= h($polozka['nazev']) ?></option><?php endif; ?><?php endforeach; ?></select></label><label class="hr_form_label"><span><input type="checkbox" name="v_treninku" value="1"<?= $formValue('v_treninku') === '1' ? ' checked' : '' ?>> V tréninku</span></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--email"><label class="hr_form_label"><span>E-mail</span><input type="email" name="email" maxlength="150" autocomplete="email" required value="<?= h($formValue('email')) ?>"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--phone"><label class="hr_form_label"><span>Telefon ČR</span><span class="hr_phone_field"><span class="hr_phone_prefix">+420</span><input class="hr_phone_input" name="telefon" maxlength="11" autocomplete="tel" data-phone-cz value="<?= h($formValue('telefon')) ?>"></span></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--phone"><label class="hr_form_label"><span>Zahraniční telefon</span><input name="telefon_zahranicni" maxlength="30" autocomplete="tel" placeholder="např. +966 …" value="<?= h($formValue('telefon_zahranicni')) ?>"></label></td>
                </tr>
            </tbody></table>
        </section>

        <div class="hr_form_actions"><a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zrušit</a><button class="hr_primary_button" type="submit">Uložit zaměstnance</button></div>
    </form>
    <dialog class="hr_photo_crop_dialog" data-photo-crop-dialog><div class="hr_photo_crop_dialog_content"><h3>Výřez fotografie</h3><p>Tažením myší označte část fotografie, která se má uložit.</p><div class="hr_photo_crop_surface" data-photo-crop-surface><img alt="Fotografie pro výřez" data-photo-crop-image><span class="hr_photo_crop_selection" data-photo-crop-selection></span></div><div class="hr_photo_crop_actions"><button class="hr_secondary_button" type="button" data-photo-crop-cancel>Zrušit</button><button class="hr_primary_button" type="button" data-photo-crop-apply>Použít výřez</button></div></div></dialog>
</section>
