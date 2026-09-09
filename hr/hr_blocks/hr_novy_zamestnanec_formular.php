<?php
declare(strict_types=1);

/* Formulář alternativního založení zaměstnance mimo náborový proces VD/ND. */
?>
<section class="hr_panel">
    <div class="hr_panel_header"><div><h2 class="hr_panel_title">Manuální vytvoření zaměstnance</h2><p class="hr_muted">Alternativní založení zaměstnance mimo náborový proces VD/ND</p></div></div>

    <form class="hr_form hr_new_employee_form" method="post" enctype="multipart/form-data" action="<?= h(cb_root_url('index.php?m=hr&page=novy_zamestnanec')) ?>">
        <input type="hidden" name="cb_action" value="hr_zamestnanec_ulozit">

        <section class="hr_new_employee_section">
            <h3 class="hr_employee_edit_subtitle">Osobní údaje</h3>
            <table class="hr_new_employee_table"><tbody>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--title"><label class="hr_form_label"><span>Titul před jménem</span><select name="titul_pred"><option value=""></option><?php foreach ($titulyPred as $titul): ?><option value="<?= h($titul['label']) ?>"><?= h($titul['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--name"><label class="hr_form_label"><span>Jméno</span><input name="jmeno" required maxlength="60" autocomplete="given-name"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--second-name"><label class="hr_form_label"><span>Druhé jméno</span><input name="druhe_jmeno" maxlength="60"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--surname"><label class="hr_form_label"><span>Příjmení</span><input name="prijmeni" required maxlength="80" autocomplete="family-name"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--title"><label class="hr_form_label"><span>Titul za jménem</span><select name="titul_za"><option value=""></option><?php foreach ($titulyZa as $titul): ?><option value="<?= h($titul['label']) ?>"><?= h($titul['label']) ?></option><?php endforeach; ?></select></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--maiden"><label class="hr_form_label"><span>Rodné příjmení</span><input name="rodne_prijmeni" maxlength="80"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--date"><label class="hr_form_label"><span>Datum narození</span><input name="datum_narozeni" data-cb-date placeholder="DD.MM.RRRR"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--birthplace"><label class="hr_form_label"><span>Místo narození</span><input name="misto_narozeni" maxlength="120"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--birth-number"><label class="hr_form_label"><span>Rodné číslo</span><input name="rodne_cislo" maxlength="11" placeholder="YYMMDD/XXXX" data-birth-number></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--nationality"><label class="hr_form_label"><span>Státní občanství</span><input name="statni_obcanstvi" maxlength="100"></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--id-card"><label class="hr_form_label"><span>Číslo občanského průkazu</span><input name="cislo_obcanskeho_prukazu" maxlength="30"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--insurer"><label class="hr_form_label"><span>Zdravotní pojišťovna</span><select name="zdr_poj"><option value="">Vyberte</option><?php foreach ($healthInsurers as $healthInsurer): ?><option value="<?= h((string)$healthInsurer['kod']) ?>"><?= h($healthInsurer['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td colspan="3"><fieldset class="hr_form_label hr_gender_field"><legend>Pohlaví</legend><span class="hr_gender_choices"><label><input type="radio" name="pohlavi" value="muž" required> Muž</label><label><input type="radio" name="pohlavi" value="žena"> Žena</label><label><input type="radio" name="pohlavi" value="jiné"> Jiné</label><label><input type="radio" name="pohlavi" value="neuvedeno"> Neuvedeno</label></span></fieldset></td>
                </tr>
                <tr><td colspan="5"><div class="hr_new_employee_detail_groups">
                    <fieldset class="hr_new_employee_detail_group"><legend>Bydliště dle OP</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--address"><label class="hr_form_label"><span>Ulice</span><input name="adresa_ulice" maxlength="120" autocomplete="street-address"></label><label class="hr_form_label"><span>Číslo popisné</span><input name="adresa_cp" maxlength="20"></label><label class="hr_form_label"><span>Město</span><input name="adresa_mesto" maxlength="100" autocomplete="address-level2"></label><label class="hr_form_label"><span>PSČ</span><input name="adresa_psc" maxlength="20" autocomplete="postal-code"></label><label class="hr_form_label"><span>Stát</span><input name="adresa_stat" maxlength="100" autocomplete="country-name"></label></div></fieldset>
                    <fieldset class="hr_new_employee_detail_group"><legend>Doručovací adresa</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--address"><label class="hr_form_label"><span>Ulice</span><input name="dorucovaci_ulice" maxlength="120"></label><label class="hr_form_label"><span>Číslo popisné</span><input name="dorucovaci_cp" maxlength="20"></label><label class="hr_form_label"><span>Město</span><input name="dorucovaci_mesto" maxlength="100"></label><label class="hr_form_label"><span>PSČ</span><input name="dorucovaci_psc" maxlength="20"></label><label class="hr_form_label"><span>Stát</span><input name="dorucovaci_stat" maxlength="100"></label></div></fieldset>
                    <fieldset class="hr_new_employee_detail_group"><legend>Nouzový kontakt (nepovinný)</legend><div class="hr_new_employee_detail_grid hr_new_employee_detail_grid--emergency"><label class="hr_form_label"><span>Jméno</span><input name="nouzovy_jmeno" maxlength="150"></label><label class="hr_form_label"><span>Vztah</span><input name="nouzovy_vztah" maxlength="80"></label><label class="hr_form_label"><span>Telefon</span><input name="nouzovy_telefon" maxlength="30" autocomplete="tel"></label><label class="hr_form_label"><span>E-mail</span><input type="email" name="nouzovy_email" maxlength="150"></label></div></fieldset>
                </div></td></tr>
                <tr class="hr_new_employee_photo_row">
                    <td colspan="2"><div class="hr_new_employee_photo"><label class="hr_form_label"><span>Fotografie zaměstnance</span><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-photo-input></label><button class="hr_photo_crop_trigger" type="button" data-photo-crop-open disabled>Výřez</button></div></td>
                    <td><figure class="hr_new_employee_photo_preview" data-photo-preview hidden><img alt="Náhled fotografie zaměstnance"></figure></td>
                    <td colspan="2" class="hr_new_employee_field hr_new_employee_field--note"><label class="hr_form_label"><span>Poznámka</span><textarea name="poznamka" maxlength="1000" rows="3"></textarea></label></td>
                </tr>
            </tbody></table>
        </section>

        <section class="hr_new_employee_section">
            <h3 class="hr_employee_edit_subtitle">Pracovní a kontaktní údaje</h3>
            <table class="hr_new_employee_table"><tbody>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--personal-number"><label class="hr_form_label"><span>Osobní číslo</span><input name="osobni_cislo" maxlength="20"></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--relation"><label class="hr_form_label"><span>Typ vztahu</span><select name="id_pracovni_vztah_typ" required><option value="">Vyberte</option><?php foreach ($vztahy as $vztah): ?><option value="<?= h($vztah['id']) ?>"><?= h($vztah['label']) ?></option><?php endforeach; ?></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--start"><label class="hr_form_label"><span>Datum nástupu</span><input type="date" name="datum_nastupu" required value="<?= h(date('Y-m-d')) ?>"></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--branches"><div class="hr_form_label"><span>Pobočky</span><div class="hr_employee_branch_picker" data-hr-branch-picker><button class="hr_employee_branch_button" type="button" data-hr-branch-toggle>Vyberte pobočky</button><div class="hr_employee_branch_panel" data-hr-branch-panel hidden><?php foreach ($pobocky as $pobocka): ?><label><input type="checkbox" name="id_pob[]" value="<?= h($pobocka['id']) ?>" data-hr-branch-option data-hr-branch-name="<?= h($pobocka['label']) ?>"> <?= h($pobocka['label']) ?></label><?php endforeach; ?></div></div></div></td>
                    <td class="hr_new_employee_field hr_new_employee_field--main-branch"><label class="hr_form_label"><span>Hlavní pobočka (nepovinné)</span><select name="id_pob_hlavni" disabled data-hr-main-branch><option value="">Bez hlavní pobočky</option></select></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--slot"><label class="hr_form_label"><span>Zařazení</span><span class="hr_slot_choice"><select name="id_slot" data-slot-select required><option value="">Vyberte</option><?php foreach ($sloty as $slot): ?><option value="<?= h($slot['id']) ?>"><?= h($slot['label']) ?></option><?php endforeach; ?><option value="__jine__">Jiné</option></select><input class="hr_slot_choice_input" type="text" name="slot_jine" maxlength="80" disabled data-slot-other></span></label></td>
                    <td class="hr_new_employee_field"><label class="hr_form_label"><span>Role v IS</span><select name="id_role_hr"><option value="9">Zaměstnanec</option><option value="7">Vedoucí směny</option><option value="5">Vedoucí pobočky</option><?php if (cb_pravo_ma(316)): ?><option value="3">Manager</option><?php endif; ?></select></label></td>
                </tr>
                <tr>
                    <td class="hr_new_employee_field hr_new_employee_field--email"><label class="hr_form_label"><span>E-mail</span><input type="email" name="email" maxlength="150" autocomplete="email" required></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--phone"><label class="hr_form_label"><span>Telefon ČR</span><span class="hr_phone_field"><span class="hr_phone_prefix">+420</span><input class="hr_phone_input" name="telefon" maxlength="11" autocomplete="tel" data-phone-cz></span></label></td>
                    <td class="hr_new_employee_field hr_new_employee_field--phone"><label class="hr_form_label"><span>Zahraniční telefon</span><input name="telefon_zahranicni" maxlength="30" autocomplete="tel" placeholder="např. +966 …"></label></td>
                </tr>
            </tbody></table>
        </section>

        <div class="hr_form_actions"><a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zrušit</a><button class="hr_primary_button" type="submit">Uložit zaměstnance</button></div>
    </form>
    <dialog class="hr_photo_crop_dialog" data-photo-crop-dialog><div class="hr_photo_crop_dialog_content"><h3>Výřez fotografie</h3><p>Tažením myší označte část fotografie, která se má uložit.</p><div class="hr_photo_crop_surface" data-photo-crop-surface><img alt="Fotografie pro výřez" data-photo-crop-image><span class="hr_photo_crop_selection" data-photo-crop-selection></span></div><div class="hr_photo_crop_actions"><button class="hr_secondary_button" type="button" data-photo-crop-cancel>Zrušit</button><button class="hr_primary_button" type="button" data-photo-crop-apply>Použít výřez</button></div></div></dialog>
</section>
