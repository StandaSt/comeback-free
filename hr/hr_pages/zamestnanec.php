<?php
declare(strict_types=1);

/*
 * Ucel souboru: Vykresli kartu zamestnance po samostatnych sekcich nejprve pouze ke cteni.
 * Citaci a editacni rezim sdileji stejny skelet, aby udaje zustaly stale na stejnem miste.
 */
$idPerson = (int)($_GET['id'] ?? 0);
$employee = isset($hrEmployeeHeader) && is_array($hrEmployeeHeader)
    ? $hrEmployeeHeader
    : ($idPerson > 0 ? hr_fetch_employee($db, $idPerson) : null);
$isEdit = isset($_GET['upravit']) && (string)$_GET['upravit'] === '1';
// Poradi sekci odpovida beznemu postupu personalisty od souhrnu pres osobni a pracovni udaje.
$employeeSections = ['prehled', 'osobni_udaje', 'pracovni_pomer', 'dochazka', 'dokumenty', 'hodnoceni', 'vybaveni', 'onboarding', 'poznamky'];
$employeeSection = (string)($_GET['sekce'] ?? 'prehled');
if (!in_array($employeeSection, $employeeSections, true)) {
    $employeeSection = 'prehled';
}
$healthInsurers = $employeeSection === 'osobni_udaje' ? hr_fetch_health_insurers($db) : [];
$titlesBefore = $employeeSection === 'osobni_udaje' ? hr_fetch_employee_titles($db, 1) : [];
$titlesAfter = $employeeSection === 'osobni_udaje' ? hr_fetch_employee_titles($db, 2) : [];
$editData = is_array($employee) && $employeeSection === 'osobni_udaje'
    ? hr_fetch_employee_edit_data($db, (int)$employee['id_person'])
    : [];
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
$missingRequiredData = is_array($employee) ? hr_fetch_employee_missing_required_data($db, (int)$employee['id_person']) : [];
$employeeFunctionData = is_array($employee) ? hr_funkce_historie($db, (int)$employee['id_person']) : ['funkce' => [], 'historie' => []];
$currentFunctionName = '';
foreach ($employeeFunctionData['historie'] as $employeeFunctionRow) {
    if ((int)$employeeFunctionRow['platny'] === 1 && (int)$employeeFunctionRow['id_funkce'] !== 1) {
        $currentFunctionName = (string)$employeeFunctionRow['nazev'] . ((int)$employeeFunctionRow['v_treninku'] === 1 ? ' – v tréninku' : '');
        break;
    }
}
$currentSlotsText = is_array($employee) ? trim((string)($employee['zarazeni'] ?? '')) : '';
$currentAssignmentText = implode(' · ', array_values(array_filter([$currentSlotsText, $currentFunctionName], static fn(string $value): bool => $value !== '' && $value !== '-')));
$employeeDocuments = is_array($employee) && cb_pravo_ma(301)
    ? hr_fetch_employee_documents($db, hr_current_user_id(), (int)$employee['id_person'], 20)
    : [];
$employeeDocumentOpenUrl = static function (array $document): string {
    return cb_root_url('hr/hr_download/hr_dokument.php?' . http_build_query([
        'id_dokument' => (int)$document['id_dokument'],
        'verze' => (int)$document['verze'],
    ]));
};
?>
<?php if ($employee === null): ?>
    <section class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Karta zaměstnance</h2></div><p class="hr_empty_state">Zaměstnanec nebyl nalezen.</p></section>
<?php else: ?>
    <section class="hr_employee_overview">
        <section class="hr_employee_overview_identity hr_panel">
            <span class="hr_employee_card_id hr_form_label_text">ID: <?= h((string)$employee['id_person']) ?></span>
            <div class="hr_employee_avatar_column">
                <?php if (!empty($employee['foto'])): ?>
                    <img class="hr_employee_photo hr_employee_photo_large" src="<?= h(cb_root_url((string)$employee['foto'])) ?>" alt="Fotografie zaměstnance <?= h((string)$employee['cele_jmeno']) ?>">
                <?php else: ?>
                    <div class="hr_employee_photo hr_employee_photo_large hr_employee_avatar_placeholder" role="img" aria-label="Fotografie zaměstnance zatím není vložena"></div>
                <?php endif; ?>
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
                <p class="hr_employee_profile_note"><?= h($currentAssignmentText !== '' ? $currentAssignmentText : 'Slot ani funkce nejsou doplněny') ?></p>
                <dl class="hr_profile_facts">
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Pracoviště</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Typ vztahu</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></dd></div>
                    <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Nástup</dt><dd class="hr_profile_fact_value"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></dd></div>
                </dl>
            </div>
        </section>
        <section class="hr_employee_overview_alerts hr_panel" aria-labelledby="hr-employee-alerts-title">
            <div class="hr_panel_header"><h3 id="hr-employee-alerts-title" class="hr_panel_title<?= (int)($employee['kompletni'] ?? 0) === 0 ? ' hr_employee_incomplete_status' : '' ?>"><?= (int)($employee['kompletni'] ?? 0) === 0 ? 'Karta není kompletní' : 'Upozornění na budoucí akce' ?></h3></div>
            <?php if ($missingRequiredData !== []): ?>
                <div class="hr_employee_missing"><strong>Je třeba doplnit:</strong><ul><?php foreach ($missingRequiredData as $missingItem): ?><li><?= h($missingItem) ?></li><?php endforeach; ?></ul></div>
            <?php else: ?>
                <p class="hr_employee_empty_block">Povinné údaje jsou doplněny.</p>
            <?php endif; ?>
        </section>
        <section class="hr_employee_overview_summary hr_panel" aria-labelledby="hr-employee-summary-title">
            <div class="hr_panel_header"><h3 id="hr-employee-summary-title" class="hr_panel_title">Stav evidence</h3></div>
            <div class="hr_employee_summary_grid">
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">✓</span><div><span>Kontrola údajů</span><strong><?= (int)($employee['overen'] ?? 0) === 1 ? 'Ověřeno' : 'Čeká na ověření' ?></strong><small><?= (int)($employee['kompletni'] ?? 0) === 1 ? 'Karta je kompletní' : 'Kartu je třeba doplnit' ?></small></div></div>
                <div class="hr_employee_summary_placeholder"><span class="hr_employee_summary_icon" aria-hidden="true">◇</span><div><span>Dokumenty</span><strong><?= h((string)count($employeeDocuments)) ?></strong><small>evidovaných souborů</small></div></div>
            </div>
        </section>
    </section>

    <nav class="hr_employee_tabs" aria-label="Sekce karty zaměstnance">
        <?php $employeeTabLabels = ['prehled' => 'Přehled', 'osobni_udaje' => 'Osobní údaje', 'pracovni_pomer' => 'Pracovní poměr', 'dochazka' => 'Docházka a dovolená', 'dokumenty' => 'Dokumenty', 'hodnoceni' => 'Hodnocení', 'vybaveni' => 'Vybavení', 'onboarding' => 'Onboarding', 'poznamky' => 'Poznámky']; ?>
        <?php foreach ($employeeTabLabels as $sectionKey => $sectionLabel): ?>
            <a class="hr_employee_tab<?= $employeeSection === $sectionKey ? ' hr_employee_tab_active' : '' ?>" href="<?= h(cb_root_url('index.php?' . http_build_query(['m' => 'hr', 'page' => 'zamestnanec', 'id' => (int)$employee['id_person'], 'sekce' => $sectionKey]))) ?>"><?= h($sectionLabel) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($employeeSection === 'osobni_udaje'): ?>
        <?php // Stejna struktura poli zustava v citacim i editacnim rezimu; meni se pouze jejich dostupnost. ?>
        <section class="hr_employee_edit_shell<?= $isEdit ? '' : ' hr_employee_read_mode' ?>"><div class="hr_panel_header hr_employee_edit_header"><h2 class="hr_panel_title">Osobní údaje</h2><div class="hr_employee_identity_meta"><strong>ID: <?= h((string)$employee['id_person']) ?></strong><label for="hr-employee-personal-number">Osobní číslo:</label><input id="hr-employee-personal-number" name="osobni_cislo" maxlength="10" value="<?= h((string)($employee['osobni_cislo'] ?? '')) ?>" form="hr-employee-edit-form"<?= $isEdit ? '' : ' disabled' ?>></div></div>
            <form id="hr-employee-edit-form" class="hr_form hr_employee_edit_form"<?= $isEdit ? ' method="post" action="' . h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=osobni_udaje&upravit=1')) . '"' : '' ?>>
                <?php if ($isEdit): ?><input type="hidden" name="cb_action" value="hr_zamestnanec_upravit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><?php endif; ?>
                <fieldset class="hr_employee_mode_fields"<?= $isEdit ? '' : ' disabled' ?>>
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Základní údaje</h3></div>
                    <?php // Sire poli odpovida jejich obsahu; jmeno, oba tituly a pohlavi tvori jeden logicky radek. ?>
                    <div class="hr_employee_basic_fields">
                        <div class="hr_employee_basic_row hr_employee_basic_row--identity">
                            <label class="hr_form_label"><span class="hr_form_label_text">Titul před jménem</span><select name="titul_pred"><option value=""></option><?php foreach ($titlesBefore as $title): ?><option value="<?= h((string)$title['label']) ?>"<?= (string)($employee['titul_pred'] ?? '') === (string)$title['label'] ? ' selected' : '' ?>><?= h((string)$title['label']) ?></option><?php endforeach; ?></select></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Jméno</span><input name="jmeno" required maxlength="60" value="<?= h((string)($employee['jmeno'] ?? '')) ?>" autocomplete="given-name"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Druhé jméno</span><input name="druhe_jmeno" maxlength="60" value="<?= h((string)($employee['druhe_jmeno'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Příjmení</span><input name="prijmeni" required maxlength="80" value="<?= h((string)($employee['prijmeni'] ?? '')) ?>" autocomplete="family-name"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Rodné příjmení</span><input name="rodne_prijmeni" maxlength="80" value="<?= h((string)($employee['rodne_prijmeni'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Titul za jménem</span><select name="titul_za"><option value=""></option><?php foreach ($titlesAfter as $title): ?><option value="<?= h((string)$title['label']) ?>"<?= (string)($employee['titul_za'] ?? '') === (string)$title['label'] ? ' selected' : '' ?>><?= h((string)$title['label']) ?></option><?php endforeach; ?></select></label>
                            <fieldset class="hr_form_label hr_gender_field"><legend class="hr_form_label_text">Pohlaví</legend><span class="hr_gender_choices"><label><input type="radio" name="pohlavi" value="muž" required<?= (string)($employee['pohlavi'] ?? '') === 'muž' ? ' checked' : '' ?>> Muž</label><label><input type="radio" name="pohlavi" value="žena"<?= (string)($employee['pohlavi'] ?? '') === 'žena' ? ' checked' : '' ?>> Žena</label><label><input type="radio" name="pohlavi" value="jiné"<?= (string)($employee['pohlavi'] ?? '') === 'jiné' ? ' checked' : '' ?>> Jiné</label><label><input type="radio" name="pohlavi" value="neuvedeno"<?= (string)($employee['pohlavi'] ?? '') === 'neuvedeno' ? ' checked' : '' ?>> Neuvedeno</label></span></fieldset>
                        </div>
                        <div class="hr_employee_basic_row hr_employee_basic_row--registry">
                            <label class="hr_form_label"><span class="hr_form_label_text">Číslo občanského průkazu</span><input name="cislo_obcanskeho_prukazu" maxlength="30" value="<?= h((string)($employee['cislo_obcanskeho_prukazu'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Datum narození</span><input name="datum_narozeni" data-cb-date value="<?= h($birthDateValue) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Místo narození</span><input name="misto_narozeni" maxlength="120" value="<?= h((string)($employee['misto_narozeni'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Rodné číslo</span><input name="rodne_cislo" maxlength="20" value="<?= h((string)($employee['rodne_cislo'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Zdravotní pojišťovna</span><select name="zdr_poj"><option value="">Vyberte</option><?php foreach ($healthInsurers as $healthInsurer): ?><option value="<?= h((string)$healthInsurer['kod']) ?>"<?= (int)($employee['zdr_poj'] ?? 0) === (int)$healthInsurer['kod'] ? ' selected' : '' ?>><?= h($healthInsurer['label']) ?></option><?php endforeach; ?></select></label>
                        </div>
                        <div class="hr_employee_basic_row hr_employee_basic_row--contact">
                            <label class="hr_form_label"><span class="hr_form_label_text">Státní občanství</span><input name="statni_obcanstvi" maxlength="100" value="<?= h((string)($employee['statni_obcanstvi'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Poznámka</span><input name="poznamka" maxlength="1000" value="<?= h((string)($employee['poznamka'] ?? '')) ?>"></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">Telefon</span><span class="hr_phone_field"><span class="hr_phone_prefix">+420</span><input class="hr_phone_input" name="telefon" maxlength="11" value="<?= h((string)($employee['telefon'] ?? '')) ?>" autocomplete="tel" data-phone-cz></span></label>
                            <label class="hr_form_label"><span class="hr_form_label_text">E-mail</span><input type="email" name="email" maxlength="150" value="<?= h((string)($employee['email'] ?? '')) ?>" autocomplete="email"></label>
                        </div>
                    </div>
                </section>
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Kontakty</h3></div><div class="hr_form_grid hr_employee_contact_grid"><label class="hr_form_label"><span class="hr_form_label_text">Jméno nouzového kontaktu</span><input name="nouzovy_jmeno" maxlength="150" value="<?= h((string)($editData['nouzovy_kontakt']['jmeno'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Vztah</span><input name="nouzovy_vztah" maxlength="80" value="<?= h((string)($editData['nouzovy_kontakt']['vztah'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Telefon nouzového kontaktu</span><input name="nouzovy_telefon" maxlength="30" value="<?= h((string)($editData['nouzovy_kontakt']['telefon'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">E-mail nouzového kontaktu</span><input type="email" name="nouzovy_email" maxlength="150" value="<?= h((string)($editData['nouzovy_kontakt']['email'] ?? '')) ?>"></label></div></section>
                <section class="hr_panel hr_employee_edit_panel"><div class="hr_panel_header"><h3 class="hr_panel_title">Bydliště a bankovní účet</h3></div><h4 class="hr_employee_edit_subtitle">Adresa podle občanského průkazu</h4><div class="hr_form_grid hr_employee_address_grid"><label class="hr_form_label"><span class="hr_form_label_text">Ulice</span><input name="adresa_ulice" maxlength="120" value="<?= h((string)($editData['adresa_op']['ulice'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Číslo popisné</span><input name="adresa_cp" maxlength="20" value="<?= h((string)($editData['adresa_op']['cp'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Město</span><input name="adresa_mesto" maxlength="100" value="<?= h((string)($editData['adresa_op']['mesto'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">PSČ</span><input name="adresa_psc" maxlength="20" value="<?= h((string)($editData['adresa_op']['psc'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Stát</span><input name="adresa_stat" maxlength="100" value="<?= h((string)($editData['adresa_op']['stat'] ?? '')) ?>"></label></div><h4 class="hr_employee_edit_subtitle">Doručovací adresa</h4><div class="hr_form_grid hr_employee_address_grid"><label class="hr_form_label"><span class="hr_form_label_text">Ulice</span><input name="dorucovaci_ulice" maxlength="120" value="<?= h((string)($editData['adresa_dorucovaci']['ulice'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Číslo popisné</span><input name="dorucovaci_cp" maxlength="20" value="<?= h((string)($editData['adresa_dorucovaci']['cp'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Město</span><input name="dorucovaci_mesto" maxlength="100" value="<?= h((string)($editData['adresa_dorucovaci']['mesto'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">PSČ</span><input name="dorucovaci_psc" maxlength="20" value="<?= h((string)($editData['adresa_dorucovaci']['psc'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Stát</span><input name="dorucovaci_stat" maxlength="100" value="<?= h((string)($editData['adresa_dorucovaci']['stat'] ?? '')) ?>"></label></div><h4 class="hr_employee_edit_subtitle">Bankovní účet</h4><div class="hr_form_grid hr_employee_bank_grid"><label class="hr_form_label"><span class="hr_form_label_text">Číslo účtu</span><input name="ucet_cislo" maxlength="34" value="<?= h((string)($editData['bankovni_ucet']['cislo_uctu'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">Kód banky</span><input name="ucet_kod_banky" maxlength="10" value="<?= h((string)($editData['bankovni_ucet']['kod_banky'] ?? '')) ?>"></label><label class="hr_form_label"><span class="hr_form_label_text">IBAN</span><input name="ucet_iban" maxlength="34" value="<?= h((string)($editData['bankovni_ucet']['iban'] ?? '')) ?>"></label></div></section>
                </fieldset>
                <?php if ($isEdit): ?><div class="hr_form_actions hr_employee_edit_actions"><a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=osobni_udaje')) ?>">Zpět</a><button class="hr_primary_button hr_panel_button_primary" type="submit">Uložit vše</button></div><?php endif; ?>
            </form>
        </section>
    <?php elseif ($employeeSection === 'pracovni_pomer'): ?>
        <?php
        // Data aktualniho vztahu a ciselniku pro samostatny formular pracovniho pomeru.
        $workRelation = hr_fetch_employee_work_relation($db, (int)$employee['id_person']);
        $workTypes = hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', "CASE nazev WHEN 'HPP' THEN 1 WHEN 'DPČ' THEN 2 WHEN 'DPP' THEN 3 ELSE 9 END, id_pracovni_vztah_typ");
        $salaryTypes = hr_fetch_work_salary_types($db);
        $activeBenefits = hr_fetch_active_benefits($db);
        $workHistory = hr_fetch_employee_work_timeline($db, (int)$employee['id_person']);
        $interruptions = $workRelation === null ? [] : hr_fetch_employee_work_interruptions($db, (int)$workRelation['id_pracovni_vztah']);
        $interruptionTypes = hr_fetch_employee_work_event_types($db, 'hr_cis_pracovni_preruseni_typ', 'id_pracovni_preruseni_typ');
        $terminationTypes = hr_fetch_employee_work_event_types($db, 'hr_cis_pracovni_ukonceni_typ', 'id_pracovni_ukonceni_typ');
        $positionData = hr_zarazeni_historie($db, (int)$employee['id_person']);
        $functionData = $employeeFunctionData;
        $workplaceData = hr_pracoviste_historie($db, (int)$employee['id_person'], $cbHrIdUser);
        ?>
        <section class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Pracovní poměr</h2><?php if ($isEdit && $workRelation !== null): ?><button class="hr_primary_button" type="submit" form="hr-work-relation-form">Uložit změny</button><?php endif; ?></div>
            <?php if ($workRelation === null): ?>
                <p class="hr_empty_state">Aktuální pracovní poměr není evidován.</p>
            <?php else: ?>
                <?php
                $workStart = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$workRelation['datum_nastupu']);
                $workStartValue = $workStart === false ? '' : $workStart->format('d.m.Y');
                $workTypeId = (int)$workRelation['id_pracovni_vztah_typ'];
                $workTypeIsSet = in_array($workTypeId, [1, 2, 3, 5], true);
                $workloadCode = $workTypeId === 3
                    ? 0
                    : ($workRelation['uvazek'] === null ? null : (int)$workRelation['uvazek']);
                $workHours = $workRelation['hodin_tydne'] === null ? '' : rtrim(rtrim(number_format((float)$workRelation['hodin_tydne'], 1, '.', ''), '0'), '.');
                $salaryTypeId = $workRelation['id_mzda_typ'] === null ? null : (int)$workRelation['id_mzda_typ'];
                $salaryAmount = $workRelation['mzda_castka'] === null ? '' : (string)$workRelation['mzda_castka'];
                $selectedBenefitIds = hr_fetch_employee_work_benefit_ids($db, (int)$workRelation['id_pracovni_vztah']);
                ?>
                <?php // Aktualni pracovni pomer pouziva stejna pole v obou rezimech; citaci rezim je pouze uzamkne. ?>
                <form id="hr-work-relation-form" class="hr_form<?= $isEdit ? '' : ' hr_employee_read_mode' ?>" data-hr-work-relation-form data-hr-work-type-hpp="1" data-hr-work-type-dpp="2" data-hr-work-type-dpc="3"<?= $isEdit ? ' method="post" action="' . h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=pracovni_pomer')) . '"' : '' ?>>
                    <?php if ($isEdit): ?><input type="hidden" name="cb_action" value="hr_pracovni_pomer_upravit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><?php endif; ?>
                    <fieldset class="hr_employee_mode_fields"<?= $isEdit ? '' : ' disabled' ?>>
                    <table class="hr_work_relation_table">
                        <colgroup><col><col><col><col><col><col><col><col></colgroup>
                        <tr>
                            <td style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text">Typ vztahu</span></td>
                            <td style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text">Datum nástupu</span></td>
                            <td data-hr-workload-kind-heading style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text">Úvazek</span></td>
                            <td data-hr-workload-hours-heading style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text">Hodin týdně</span></td>
                            <td data-hr-workload-dpp-heading hidden colspan="2" style="white-space:nowrap"><span class="hr_form_label_text">DPP</span></td>
                            <td style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text">Typ mzdy</span></td>
                            <td style="padding-right:10px;white-space:nowrap"><span class="hr_form_label_text" data-hr-salary-label>Částka Kč / hodinu</span></td>
                            <td style="white-space:nowrap"><span class="hr_form_label_text">Platí od</span></td>
                        </tr>
                        <tr>
                            <td style="padding-right:10px"><select name="id_pracovni_vztah_typ" data-hr-work-relation-type style="width:270px"><option value=""<?= !$workTypeIsSet ? ' selected' : '' ?>>Zvolte typ prac. vztahu</option><?php foreach ($workTypes as $workType): ?><option value="<?= h((string)$workType['id']) ?>"<?= $workTypeId === (int)$workType['id'] ? ' selected' : '' ?>><?= h($workType['label']) ?></option><?php endforeach; ?></select></td>
                            <td style="padding-right:10px"><input name="datum_nastupu" data-cb-date style="width:135px" value="<?= h($workStartValue) ?>"></td>
                            <td data-hr-workload-kind-cell style="padding-right:10px"><select name="uvazek" data-hr-workload-kind style="width:130px"><option value=""<?= $workloadCode === null ? ' selected' : '' ?>>Nezadáno</option><option value="1"<?= $workloadCode === 1 ? ' selected' : '' ?>>Plný</option><option value="2"<?= $workloadCode === 2 ? ' selected' : '' ?>>Poloviční</option><option value="4"<?= $workloadCode === 4 ? ' selected' : '' ?>>Čtvrtinový</option><option value="0"<?= $workloadCode === 0 ? ' selected' : '' ?>>Vlastní</option></select></td>
                            <td data-hr-workload-hours-cell style="padding-right:10px"><input type="text" inputmode="decimal" name="hodin_tydne" data-hr-workload-hours maxlength="4" style="width:70px" value="<?= h($workHours) ?>"></td>
                            <td data-hr-workload-dpp-cell hidden colspan="2" style="white-space:nowrap">Max. limit je 300 hod. za rok</td>
                            <td style="padding-right:10px"><select name="id_mzda_typ" data-hr-salary-type style="width:130px"><option value=""<?= $salaryTypeId === null ? ' selected' : '' ?>>Nezadáno</option><?php foreach ($salaryTypes as $salaryType): ?><option value="<?= h((string)$salaryType['id']) ?>"<?= $salaryTypeId === (int)$salaryType['id'] ? ' selected' : '' ?>><?= h($salaryType['label']) ?></option><?php endforeach; ?></select></td>
                            <td style="padding-right:10px"><input type="text" inputmode="numeric" pattern="[0-9]*" name="mzda_castka" data-hr-salary-amount required maxlength="10" style="width:100px" value="<?= h($salaryAmount) ?>"></td>
                            <td><input name="platnost_od" data-cb-date required style="width:135px" value="<?= h($isEdit ? date('d.m.Y') : '—') ?>"></td>
                        </tr>
                    </table>
                    <fieldset class="hr_work_benefits_fieldset">
                        <legend class="hr_form_label_text">Benefity</legend>
                        <div class="hr_work_benefits">
                            <?php foreach ($activeBenefits as $benefit): ?>
                                <label class="hr_form_label_text" style="white-space:nowrap"><input type="checkbox" name="benefity[]" value="<?= h((string)$benefit['id']) ?>"<?= in_array((int)$benefit['id'], $selectedBenefitIds, true) ? ' checked' : '' ?>> <?= h($benefit['label']) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    </fieldset>
                </form>
            <?php endif; ?>
        </section>

        <?php if ($workRelation !== null): ?>
            <section class="hr_panel">
                <div class="hr_panel_header"><h2 class="hr_panel_title">Přerušení pracovního poměru</h2></div>
                <?php if ($isEdit): ?><form class="hr_form hr_work_event_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=pracovni_pomer&upravit=1')) ?>">
                    <input type="hidden" name="cb_action" value="hr_pracovni_preruseni_ulozit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><input type="hidden" name="id_pracovni_vztah" value="<?= h((string)$workRelation['id_pracovni_vztah']) ?>">
                    <div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Typ přerušení</span><select name="id_pracovni_preruseni_typ" required><option value="">Vyberte</option><?php foreach ($interruptionTypes as $interruptionType): ?><option value="<?= h((string)$interruptionType['id']) ?>"><?= h((string)$interruptionType['label']) ?></option><?php endforeach; ?></select></label><label class="hr_form_label"><span class="hr_form_label_text">Od</span><input name="datum_od" data-cb-date required></label><label class="hr_form_label"><span class="hr_form_label_text">Do</span><input name="datum_do" data-cb-date></label><label class="hr_form_label hr_work_event_note"><span class="hr_form_label_text">Poznámka</span><input name="poznamka" maxlength="1000"></label><button class="hr_primary_button" type="submit">Evidovat přerušení</button></div>
                </form><?php endif; ?>
                <?php if ($interruptions === []): ?><p class="hr_employee_empty_block hr_employee_empty_block--plain">Přerušení zatím nejsou evidována.</p><?php else: ?><div class="hr_table_wrap"><table class="hr_table"><thead><tr><th class="hr_table_cell hr_table_head">Typ</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Poznámka</th><th class="hr_table_cell hr_table_head"></th></tr></thead><tbody><?php foreach ($interruptions as $interruption): ?><tr><td class="hr_table_cell"><?= h((string)$interruption['typ']) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)$interruption['datum_od'])) ?></td><td class="hr_table_cell"><?php if ($interruption['datum_do'] === null && $isEdit): ?><form method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=pracovni_pomer&upravit=1')) ?>"><input type="hidden" name="cb_action" value="hr_pracovni_preruseni_uzavrit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><input type="hidden" name="id_pracovni_preruseni" value="<?= h((string)$interruption['id_pracovni_preruseni']) ?>"><input name="datum_do" data-cb-date required placeholder="DD.MM.RRRR"><button class="hr_secondary_button" type="submit">Uzavřít</button></form><?php elseif ($interruption['datum_do'] === null): ?>Trvá<?php else: ?><?= h(hr_format_date((string)$interruption['datum_do'])) ?><?php endif; ?></td><td class="hr_table_cell"><?= h((string)($interruption['poznamka'] ?? '')) ?></td><td class="hr_table_cell"></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section>

        <?php endif; ?>

        <div class="hr_assignments_grid">
        <section class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Pracovní sloty</h2></div>
            <?php if ($isEdit && cb_pravo_ma(307)): ?>
                <form class="hr_form hr_assignment_form hr_assignment_form--position" method="post" action="<?= h(hr_pracovni_pomer_url((int)$employee['id_person'])) ?>">
                    <input type="hidden" name="cb_action" value="hr_zamestnanec_pozice_zmenit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>">
                    <label class="hr_form_label"><span class="hr_form_label_text">Přidat slot</span><select name="id_slot" required><option value="">Vyberte</option><?php foreach ($positionData['pozice'] as $pozice): ?><option value="<?= h((string)$pozice['id_slot']) ?>"><?= h($pozice['slot']) ?></option><?php endforeach; ?></select></label>
                    <label class="hr_form_label"><span class="hr_form_label_text">Platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label>
                    <button class="hr_primary_button" type="submit">Přidat slot</button>
                </form>
            <?php endif; ?>
            <?php if ($positionData['historie'] === []): ?><p class="hr_empty_state">Historie slotů není evidována.</p><?php else: ?>
                <div class="hr_table_wrap hr_compact_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Slot</th><th class="hr_table_cell hr_table_head">Hlavní</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav záznamu</th></tr></thead><tbody><?php foreach ($positionData['historie'] as $polozka): ?><tr><td class="hr_table_cell"><strong><?= h((string)$polozka['slot']) ?></strong></td><td class="hr_table_cell"><?= (int)$polozka['hlavni'] === 1 ? 'Ano' : 'Ne' ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_od'] ?? ''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_do'] ?? ''))) ?></td><td class="hr_table_cell"><?= (int)$polozka['platny'] === 1 ? 'Platný' : 'Ukončený' ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Funkce</h2></div>
            <?php if ($isEdit && cb_pravo_ma(307)): ?>
                <form class="hr_form hr_assignment_form" method="post" action="<?= h(hr_pracovni_pomer_url((int)$employee['id_person'])) ?>">
                    <input type="hidden" name="cb_action" value="hr_zamestnanec_funkce_zmenit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>">
                    <label class="hr_form_label"><span class="hr_form_label_text">Funkce</span><select name="id_funkce"><option value="">Bez funkce</option><?php foreach ($functionData['funkce'] as $funkce): ?><?php if ((int)$funkce['id_role'] !== 3 || cb_pravo_ma(316)): ?><option value="<?= h((string)$funkce['id_funkce']) ?>"><?= h($funkce['nazev']) ?></option><?php endif; ?><?php endforeach; ?></select></label>
                    <label class="hr_form_label"><span class="hr_form_label_text"><input type="checkbox" name="v_treninku" value="1"> V tréninku</span></label>
                    <label class="hr_form_label"><span class="hr_form_label_text">Platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label>
                    <button class="hr_primary_button" type="submit">Uložit funkci</button>
                </form>
            <?php endif; ?>
            <?php if ($functionData['historie'] === []): ?><p class="hr_empty_state">Funkce není evidována.</p><?php else: ?>
                <div class="hr_table_wrap hr_compact_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Funkce</th><th class="hr_table_cell hr_table_head">Trénink</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav</th></tr></thead><tbody><?php foreach ($functionData['historie'] as $polozka): ?><tr><td class="hr_table_cell"><strong><?= h((string)$polozka['nazev']) ?></strong></td><td class="hr_table_cell"><?= (int)$polozka['v_treninku'] === 1 ? 'Ano' : 'Ne' ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_od'] ?? ''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_do'] ?? ''))) ?></td><td class="hr_table_cell"><?= (int)$polozka['platny'] === 1 ? 'Platná' : 'Ukončená' ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>

        <section class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Pobočky</h2></div>
            <?php if ($isEdit && cb_pravo_ma(307)): ?>
                <form class="hr_form hr_assignment_form hr_assignment_form--branches" method="post" action="<?= h(hr_pracovni_pomer_url((int)$employee['id_person'])) ?>">
                    <input type="hidden" name="cb_action" value="hr_zamestnanec_pobocky_zmenit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>">
                    <div class="hr_form_label"><span class="hr_form_label_text">Nové pobočky</span><div class="hr_employee_branch_picker" data-hr-branch-picker><button class="hr_employee_branch_button" type="button" data-hr-branch-toggle>Vyberte pobočky</button><div class="hr_employee_branch_panel" data-hr-branch-panel hidden><?php foreach ($workplaceData['pobocky'] as $pobocka): ?><label><input type="checkbox" name="id_pob[]" value="<?= h((string)$pobocka['id_pob']) ?>" data-hr-branch-option data-hr-branch-name="<?= h($pobocka['nazev']) ?>"> <?= h($pobocka['nazev']) ?></label><?php endforeach; ?></div></div></div>
                    <label class="hr_form_label"><span class="hr_form_label_text">Hlavní pobočka</span><select name="id_pob_hlavni" required disabled data-hr-main-branch><option value="">Nejprve vyberte pobočky</option></select></label>
                    <label class="hr_form_label"><span class="hr_form_label_text">Platí od</span><input name="platnost_od" data-cb-date required value="<?= h(date('d.m.Y')) ?>"></label>
                    <button class="hr_primary_button" type="submit">Uložit změnu poboček</button>
                </form>
            <?php endif; ?>
            <?php if ($workplaceData['historie'] === []): ?><p class="hr_empty_state">Historie poboček není evidována.</p><?php else: ?>
                <div class="hr_table_wrap hr_compact_table_wrap"><table class="hr_table hr_assignment_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Pobočka</th><th class="hr_table_cell hr_table_head">Hlavní</th><th class="hr_table_cell hr_table_head">Od</th><th class="hr_table_cell hr_table_head">Do</th><th class="hr_table_cell hr_table_head">Stav záznamu</th></tr></thead><tbody><?php foreach ($workplaceData['historie'] as $polozka): ?><tr><td class="hr_table_cell"><strong><?= h((string)$polozka['nazev']) ?></strong></td><td class="hr_table_cell"><?= (int)$polozka['hlavni'] === 1 ? 'Ano' : 'Ne' ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_od'] ?? ''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($polozka['platnost_do'] ?? ''))) ?></td><td class="hr_table_cell"><?= (int)$polozka['platny'] === 1 ? 'Platný' : 'Zrušený plán' ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>
        </div>

        <?php if ($isEdit && $workRelation !== null && empty($workRelation['id_pracovni_ukonceni'])): ?>
            <section class="hr_panel">
                <div class="hr_panel_header"><h2 class="hr_panel_title">Ukončení pracovního poměru</h2></div>
                <form class="hr_form hr_work_event_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']) . '&sekce=pracovni_pomer')) ?>">
                    <input type="hidden" name="cb_action" value="hr_pracovni_pomer_ukoncit"><input type="hidden" name="id_person" value="<?= h((string)$employee['id_person']) ?>"><input type="hidden" name="id_pracovni_vztah" value="<?= h((string)$workRelation['id_pracovni_vztah']) ?>">
                    <div class="hr_form_grid"><label class="hr_form_label"><span class="hr_form_label_text">Důvod ukončení</span><select name="id_pracovni_ukonceni_typ" required><option value="">Vyberte</option><?php foreach ($terminationTypes as $terminationType): ?><option value="<?= h((string)$terminationType['id']) ?>"><?= h((string)$terminationType['label']) ?></option><?php endforeach; ?></select></label><label class="hr_form_label"><span class="hr_form_label_text">Datum oznámení</span><input name="datum_oznameni" data-cb-date></label><label class="hr_form_label"><span class="hr_form_label_text">Datum ukončení</span><input name="datum_ukonceni" data-cb-date required></label><label class="hr_form_label hr_work_event_note"><span class="hr_form_label_text">Poznámka</span><input name="poznamka" maxlength="1000"></label><button class="hr_danger_button hr_secondary_button hr_panel_button_secondary" type="submit">Uložit ukončení</button></div>
                </form>
            </section>
        <?php endif; ?>

        <section class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Historie pracovních poměrů</h2></div>
            <?php if ($workHistory === []): ?>
                <p class="hr_empty_state">Historie pracovních poměrů není evidována.</p>
            <?php else: ?>
                <div class="hr_table_wrap hr_compact_table_wrap"><table class="hr_table hr_work_history_table"><thead><tr><th class="hr_table_cell hr_table_head">Kdy</th><th class="hr_table_cell hr_table_head">Akce</th><th class="hr_table_cell hr_table_head">Platí od</th><th class="hr_table_cell hr_table_head">Platí do</th><th class="hr_table_cell hr_table_head">Zapsal</th><th class="hr_table_cell hr_table_head">Poznámka</th></tr></thead><tbody>
                <?php foreach ($workHistory as $historyItem): ?>
                    <tr><td class="hr_table_cell"><?= h(date('d. m. Y H:i', strtotime((string)$historyItem['kdy']))) ?></td><td class="hr_table_cell"><strong><?= h((string)$historyItem['akce']) ?></strong></td><td class="hr_table_cell"><?= h(hr_format_date((string)($historyItem['plati_od'] ?? ''))) ?></td><td class="hr_table_cell"><?= h(hr_format_date((string)($historyItem['plati_do'] ?? ''))) ?></td><td class="hr_table_cell"><?= h(trim((string)($historyItem['zapsal'] ?? '')) ?: '—') ?></td><td class="hr_table_cell"><?= h(trim((string)($historyItem['poznamka'] ?? '')) ?: '—') ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php elseif ($employeeSection === 'dokumenty'): ?>
        <section class="hr_panel" id="hr-employee-documents"><div class="hr_panel_header"><h2 class="hr_panel_title">Dokumenty</h2><?php if ($employeeDocuments !== []): ?><a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=dokumenty')) ?>">Zobrazit všechny</a><?php endif; ?></div><?php if ($employeeDocuments === []): ?><p class="hr_employee_empty_block">Zatím nejsou evidované žádné dokumenty.</p><?php else: ?><ul class="hr_activity_list"><?php foreach ($employeeDocuments as $document): ?><li class="hr_activity_item"><span class="hr_dot hr_blue"></span><strong class="hr_activity_name"><?= h((string)$document['typ']) ?></strong><a class="hr_table_link" href="<?= h($employeeDocumentOpenUrl($document)) ?>" target="_blank" rel="noopener"><?= h($document['ulozeny_nazev'] !== '' ? $document['ulozeny_nazev'] : $document['nazev']) ?></a><time class="hr_activity_time"><?= h(hr_format_date($document['vytvoreno'])) ?></time></li><?php endforeach; ?></ul><?php endif; ?></section>
    <?php elseif (in_array($employeeSection, ['dochazka', 'hodnoceni', 'vybaveni', 'onboarding', 'poznamky'], true)): ?>
        <?php $sectionTitles = ['dochazka' => 'Docházka a dovolená', 'hodnoceni' => 'Hodnocení', 'vybaveni' => 'Vybavení', 'onboarding' => 'Onboarding', 'poznamky' => 'Poznámky']; ?>
        <section class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title"><?= h($sectionTitles[$employeeSection]) ?></h2></div><p class="hr_employee_empty_block">Tato část karty zatím nemá vlastní evidenci.</p></section>
    <?php else: ?>
        <section class="hr_employee_dashboard">
            <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Časová osa</h2></div><p class="hr_employee_empty_block">Události k zaměstnanci zatím nejsou evidované.</p></article>
            <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Dokumenty</h2></div><p class="hr_employee_empty_block"><?= $employeeDocuments === [] ? 'Zatím nejsou evidované žádné dokumenty.' : h((string)count($employeeDocuments)) . ' evidovaných dokumentů' ?></p></article>
            <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Nepřítomnosti</h2></div><p class="hr_employee_empty_block">Přejděte na záložku Docházka a dovolená.</p></article>
            <article class="hr_panel"><div class="hr_panel_header"><h2 class="hr_panel_title">Benefity</h2></div><p class="hr_employee_empty_block">Přejděte na záložku Pracovní poměr.</p></article>
        </section>
    <?php endif; ?>
<?php endif; ?>
