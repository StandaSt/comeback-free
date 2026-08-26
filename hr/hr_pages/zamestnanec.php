<?php
declare(strict_types=1);

// Detail zamestnance se nacita podle id_person.
$idPerson = (int)($_GET['id'] ?? 0);
$employee = isset($hrEmployeeHeader) && is_array($hrEmployeeHeader)
    ? $hrEmployeeHeader
    : ($idPerson > 0 ? hr_fetch_employee($db, $idPerson) : null);
$isEdit = isset($_GET['upravit']) && (string)$_GET['upravit'] === '1';
$employeeSection = (string)($_GET['sekce'] ?? 'prehled');
$healthInsurers = $isEdit ? hr_fetch_health_insurers($db) : [];
$editData = $isEdit && is_array($employee) ? hr_fetch_employee_edit_data($db, (int)$employee['id_person']) : [];
$editInput = $_SESSION['hr_edit_input'] ?? [];
unset($_SESSION['hr_edit_input']);
if (is_array($employee) && is_array($editInput) && (int)($editInput['id_person'] ?? 0) === (int)$employee['id_person']) {
    $employee = array_replace($employee, $editInput);
    $editData['adresa'] = array_replace($editData['adresa'] ?? [], ['ulice' => $editInput['adresa_ulice'] ?? '', 'cp' => $editInput['adresa_cp'] ?? '', 'mesto' => $editInput['adresa_mesto'] ?? '', 'psc' => $editInput['adresa_psc'] ?? '', 'stat' => $editInput['adresa_stat'] ?? '']);
    $editData['nouzovy_kontakt'] = array_replace($editData['nouzovy_kontakt'] ?? [], ['jmeno' => $editInput['nouzovy_jmeno'] ?? '', 'vztah' => $editInput['nouzovy_vztah'] ?? '', 'telefon' => $editInput['nouzovy_telefon'] ?? '', 'email' => $editInput['nouzovy_email'] ?? '']);
    $editData['bankovni_ucet'] = array_replace($editData['bankovni_ucet'] ?? [], ['cislo_uctu' => $editInput['ucet_cislo'] ?? '', 'kod_banky' => $editInput['ucet_kod_banky'] ?? '', 'iban' => $editInput['ucet_iban'] ?? '']);
}
$birthDateValue = '';
if (!empty($editInput['datum_narozeni'])) {
    $birthDateValue = (string)$editInput['datum_narozeni'];
} elseif (!empty($employee['datum_narozeni'])) {
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$employee['datum_narozeni']);
    $birthDateValue = $birthDate === false ? '' : $birthDate->format('d.m.Y');
}
?>
<?php if ($employee === null): ?>
    <section class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Karta zaměstnance</h2></div><p class="hr_empty_state">Zaměstnanec nebyl nalezen.</p></section>
<?php else: ?>
    <section class="hr_employee_overview">
        <section class="hr_employee_overview_identity hr_panel">
            <div class="hr_employee_avatar_column">
                <div class="hr_employee_photo hr_employee_photo_large hr_employee_avatar_placeholder" role="img" aria-label="Fotografie zaměstnance zatím není vložena"></div>
                <span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span>
                <?php if ((int)($employee['overen'] ?? 0) === 0 || (int)($employee['kompletni'] ?? 0) === 0): ?>
                    <div class="hr_employee_statuses">
                        <?php if ((int)($employee['overen'] ?? 0) === 0): ?>
                            <span class="hr_badge hr_neutral">Neověřený</span>
                        <?php endif; ?>
                        <?php if ((int)($employee['kompletni'] ?? 0) === 0): ?>
                            <span class="hr_badge hr_neutral">Nekompletní</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="hr_employee_profile_info">
                <div class="hr_employee_name_line hr_employee_name_line_large"><h2 class="hr_employee_name_title"><?= h($employee['cele_jmeno']) ?></h2></div>
                <p class="hr_employee_profile_note"><?= h(trim((string)($employee['zarazeni'] ?? '')) !== '' && (string)($employee['zarazeni'] ?? '') !== '-' ? (string)$employee['zarazeni'] : 'Zařazení není doplněno') ?></p>
                <dl class="hr_profile_facts">
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Pracoviště</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Typ vztahu</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></dd></div>
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Nástup</dt><dd class="hr_profile_fact_value"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></dd></div>
                </dl>
            </div>
        </section>
        <section class="hr_employee_overview_alerts hr_panel" aria-labelledby="hr-employee-alerts-title"><div class="hr_panel_header"><h3 id="hr-employee-alerts-title" class="hr_panel_title">Upozornění</h3></div><p class="hr_employee_empty_block">Zatím nejsou evidovaná žádná upozornění.</p></section>
        <section class="hr_employee_overview_summary hr_panel" aria-labelledby="hr-employee-summary-title">
            <div class="hr_panel_header"><h3 id="hr-employee-summary-title" class="hr_panel_title">Rychlý přehled</h3></div>
            <div class="hr_employee_summary_grid">
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">☀</span><div><span>Zůstatek dovolené</span><strong>18 dní</strong><small>z 25 dní</small></div></div>
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">▣</span><div><span>Absence (letos)</span><strong>3 dny</strong><small>z toho 2 PN</small></div></div>
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">☆</span><div><span>Poslední hodnocení</span><strong>15. 2. 2024</strong><small>(PDM 2023)</small></div></div>
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">¤</span><div><span>Aktuální mzda</span><strong>45 000 Kč</strong><small>Hrubá mzda</small></div></div>
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">◷</span><div><span>Úvazek</span><strong>1,0</strong><small>(40 h/týdně)</small></div></div>
            </div>
        </section>
    </section>

    <?php if ($isEdit): ?>
        <section class="hr_employee_edit_shell"><div class="hr_panel_header hr_employee_edit_header"><h2 class="hr_panel_title">Doplnění a úprava údajů zaměstnance</h2><div class="hr_employee_identity_meta"><strong>ID: <?= h((string)$employee['id_person']) ?></strong><label for="hr-employee-personal-number">Osobní číslo:</label><input id="hr-employee-personal-number" name="osobni_cislo" maxlength="10" value="<?= h((string)($employee['osobni_cislo'] ?? '')) ?>" form="hr-employee-edit-form"></div></div>
            <form id="hr-employee-edit-form" class="hr_form hr_employee_edit_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>"><input type="hidden" name="cb_action" value="hr_zamestnanec_upravit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>">
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Základní údaje</h3></div><div class="hr_form_grid">
                    <label class="hr_form_label"><span class="hr_form_label_text">Jméno</span><input name="jmeno" required maxlength="60" value="<?= h((string)($employee['jmeno'] ?? '')) ?>" autocomplete="given-name"></label><label class="hr_form_label"><span class="hr_form_label_text">Druhé jméno</span><input name="druhe_jmeno" maxlength="60" value="<?= h((string)($employee['druhe_jmeno'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Příjmení</span><input name="prijmeni" required maxlength="80" value="<?= h((string)($employee['prijmeni'] ?? '')) ?>" autocomplete="family-name"></label><label class="hr_form_label"><span class="hr_form_label_text">Číslo občanského průkazu</span><input name="cislo_obcanskeho_prukazu" maxlength="30" value="<?= h((string)($employee['cislo_obcanskeho_prukazu'] ?? '')) ?>"></label>
                    <label class="hr_form_label"><span class="hr_form_label_text">Datum narození</span><input name="datum_narozeni" data-cb-date value="<?= h($birthDateValue) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Místo narození</span><input name="misto_narozeni" maxlength="120" value="<?= h((string)($employee['misto_narozeni'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Rodné číslo</span><input name="rodne_cislo" maxlength="20" value="<?= h((string)($employee['rodne_cislo'] ?? '')) ?>"></label>
                    <fieldset class="hr_form_label hr_gender_field"><legend class="hr_form_label_text">Pohlaví</legend><span class="hr_gender_choices"><label><input type="radio" name="pohlavi" value="muž" required<?= (string)($employee['pohlavi'] ?? '') === 'muž' ? ' checked' : '' ?>> Muž</label><label><input type="radio" name="pohlavi" value="žena"<?= (string)($employee['pohlavi'] ?? '') === 'žena' ? ' checked' : '' ?>> Žena</label><label><input type="radio" name="pohlavi" value="jiné"<?= (string)($employee['pohlavi'] ?? '') === 'jiné' ? ' checked' : '' ?>> Jiné</label></span></fieldset><label class="hr_form_label"><span class="hr_form_label_text">Zdravotní pojišťovna</span><select name="zdr_poj"><option value="">Vyberte</option><?php foreach ($healthInsurers as $healthInsurer): ?><option value="<?= h((string)$healthInsurer['kod']) ?>"<?= (int)($employee['zdr_poj'] ?? 0) === (int)$healthInsurer['kod'] ? ' selected' : '' ?>><?= h($healthInsurer['label']) ?></option><?php endforeach; ?></select></label><label class="hr_form_label"><span class="hr_form_label_text">Státní občanství</span><input name="statni_obcanstvi" maxlength="100" value="<?= h((string)($employee['statni_obcanstvi'] ?? '')) ?>"></label>
                    <label class="hr_form_label"><span class="hr_form_label_text">Telefon</span><span class="hr_phone_field"><span class="hr_phone_prefix">+420</span><input class="hr_phone_input" name="telefon" maxlength="11" value="<?= h((string)($employee['telefon'] ?? '')) ?>" autocomplete="tel" data-phone-cz></span></label><label class="hr_form_label"><span class="hr_form_label_text">E-mail</span><input type="email" name="email" maxlength="150" value="<?= h((string)($employee['email'] ?? '')) ?>" autocomplete="email"></label>
                </div></section>
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Kontakty</h3></div><div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Jméno nouzového kontaktu</span><input name="nouzovy_jmeno" maxlength="150" value="<?= h((string)($editData['nouzovy_kontakt']['jmeno'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Vztah</span><input name="nouzovy_vztah" maxlength="80" value="<?= h((string)($editData['nouzovy_kontakt']['vztah'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Telefon nouzového kontaktu</span><input name="nouzovy_telefon" maxlength="30" value="<?= h((string)($editData['nouzovy_kontakt']['telefon'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">E-mail nouzového kontaktu</span><input type="email" name="nouzovy_email" maxlength="150" value="<?= h((string)($editData['nouzovy_kontakt']['email'] ?? '')) ?>"></label></div></section>
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Bydliště a bankovní účet</h3></div><h4 class="hr_employee_edit_subtitle">Adresa podle občanského průkazu</h4><div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Ulice</span><input name="adresa_ulice" maxlength="120" value="<?= h((string)($editData['adresa_op']['ulice'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Číslo popisné</span><input name="adresa_cp" maxlength="20" value="<?= h((string)($editData['adresa_op']['cp'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Město</span><input name="adresa_mesto" maxlength="100" value="<?= h((string)($editData['adresa_op']['mesto'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">PSČ</span><input name="adresa_psc" maxlength="20" value="<?= h((string)($editData['adresa_op']['psc'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Stát</span><input name="adresa_stat" maxlength="100" value="<?= h((string)($editData['adresa_op']['stat'] ?? '')) ?>"></label></div><h4 class="hr_employee_edit_subtitle">Doručovací adresa</h4><div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Ulice</span><input name="dorucovaci_ulice" maxlength="120" value="<?= h((string)($editData['adresa_dorucovaci']['ulice'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Číslo popisné</span><input name="dorucovaci_cp" maxlength="20" value="<?= h((string)($editData['adresa_dorucovaci']['cp'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Město</span><input name="dorucovaci_mesto" maxlength="100" value="<?= h((string)($editData['adresa_dorucovaci']['mesto'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">PSČ</span><input name="dorucovaci_psc" maxlength="20" value="<?= h((string)($editData['adresa_dorucovaci']['psc'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Stát</span><input name="dorucovaci_stat" maxlength="100" value="<?= h((string)($editData['adresa_dorucovaci']['stat'] ?? '')) ?>"></label></div><h4 class="hr_employee_edit_subtitle">Bankovní účet</h4><div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Číslo účtu</span><input name="ucet_cislo" maxlength="34" value="<?= h((string)($editData['bankovni_ucet']['cislo_uctu'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Kód banky</span><input name="ucet_kod_banky" maxlength="10" value="<?= h((string)($editData['bankovni_ucet']['kod_banky'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">IBAN</span><input name="ucet_iban" maxlength="34" value="<?= h((string)($editData['bankovni_ucet']['iban'] ?? '')) ?>"></label></div></section>
                <div class="hr_form_actions hr_employee_edit_actions"><a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>">Zpět</a><button class="hr_primary_button hr_panel_button_primary" type="submit">Uložit vše</button></div>
            </form>
        </section>
    <?php endif; ?>

    <nav class="hr_employee_tabs" aria-label="Sekce karty zaměstnance"><a class="hr_employee_tab<?= $employeeSection === 'prehled' ? ' hr_employee_tab_active' : '' ?>" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . $employee['id_person'])) ?>">Přehled</a><a class="hr_employee_tab<?= $employeeSection === 'pracovni_pomer' ? ' hr_employee_tab_active' : '' ?>" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . $employee['id_person'] . '&sekce=pracovni_pomer')) ?>">Pracovní poměr</a><span class="hr_employee_tab">Docházka a dovolená</span><span class="hr_employee_tab">Dokumenty</span><span class="hr_employee_tab">Hodnocení</span><span class="hr_employee_tab">Mzda a benefity</span><span class="hr_employee_tab">Vybavení</span><span class="hr_employee_tab">Osobní údaje</span><span class="hr_employee_tab">Onboarding</span><span class="hr_employee_tab">Poznámky</span></nav>
    <?php if ($employeeSection === 'pracovni_pomer'): $workRelation = hr_fetch_employee_work_relation($db, (int)$employee['id_person']); $workTypes = hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev'); ?>
        <section class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Pracovní poměr</h2></div><?php if ($workRelation === null): ?><p class="hr_empty_state">Aktuální pracovní poměr není evidován.</p><?php else: $workStart = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$workRelation['datum_nastupu']); $workStartValue = $workStart === false ? '' : $workStart->format('d.m.Y'); ?><form class="hr_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=pracovni_pomer')) ?>"><input type="hidden" name="cb_action" value="hr_pracovni_pomer_upravit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><table style="width:100%;border-collapse:collapse"><colgroup><col style="width:1%"><col style="width:1%"><col style="width:1%"><col style="width:1%"><col><col style="width:1%"></colgroup><tr><td style="padding-right:18px;white-space:nowrap"><span class="hr_form_label_text">Typ vztahu</span></td><td style="padding-right:18px;white-space:nowrap"><span class="hr_form_label_text">Datum nástupu</span></td><td style="padding-right:18px;white-space:nowrap"><span class="hr_form_label_text">Úvazek</span></td><td style="padding-right:18px;white-space:nowrap"><span class="hr_form_label_text">Hodin týdně</span></td><td></td><td rowspan="2" style="vertical-align:bottom;white-space:nowrap"><button class="hr_primary_button" type="submit">Uložit změny pracovního poměru</button></td></tr><tr><td style="padding-right:18px"><select name="id_pracovni_vztah_typ" style="width:235px"><?php foreach ($workTypes as $workType): ?><option value="<?= h((string)$workType['id']) ?>"<?= (int)$workRelation['id_pracovni_vztah_typ'] === (int)$workType['id'] ? ' selected' : '' ?>><?= h($workType['label']) ?></option><?php endforeach; ?></select></td><td style="padding-right:18px"><input name="datum_nastupu" data-cb-date style="width:135px" value="<?= h($workStartValue) ?>"></td><td style="padding-right:18px"><input type="number" name="uvazek" style="width:90px" step="0.01" min="0" value="<?= h((string)($workRelation['uvazek'] ?? '1')) ?>"></td><td style="padding-right:18px"><input type="number" name="hodin_tydne" style="width:110px" step="0.01" min="0" value="<?= h((string)($workRelation['hodin_tydne'] ?? '40')) ?>"></td><td></td></tr></table></form><?php endif; ?></section>
    <?php else: ?>
    <section class="hr_employee_dashboard">
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Časová osa</h2></div><p class="hr_employee_empty_block">Události k zaměstnanci zatím nejsou evidované.</p></article>
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Poslední dokumenty</h2></div><p class="hr_employee_empty_block">Zatím nejsou evidované žádné dokumenty.</p></article>
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Nepřítomnosti</h2></div><p class="hr_employee_empty_block">Zatím není evidovaná žádná nepřítomnost.</p></article>
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Připravované události</h2></div><p class="hr_employee_empty_block">Zatím nejsou evidované žádné události.</p></article>
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Schválené dovolené</h2></div><p class="hr_employee_empty_block">Zatím nejsou evidované žádné dovolené.</p></article>
        <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Benefity</h2></div><p class="hr_employee_empty_block">Zatím nejsou evidované žádné benefity.</p></article>
    </section>
    <?php endif; ?>
<?php endif; ?>
