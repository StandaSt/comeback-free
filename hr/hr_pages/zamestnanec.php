<?php
declare(strict_types=1);

// Detail zamestnance se nacita podle id_person.
$idPerson = (int)($_GET['id'] ?? 0);
$employee = $idPerson > 0 ? hr_fetch_employee($db, $idPerson) : null;
?>
<?php if ($employee === null): ?>
    <section class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Karta zaměstnance</h2>
        </div>
        <p class="hr_empty_state">Zaměstnanec nebyl nalezen.</p>
    </section>
<?php else: ?>
    <section class="hr_employee_profile hr_panel">
        <div class="hr_employee_profile_main">
            <div class="hr_employee_photo hr_employee_photo_large"><?= h($employee['inicialy']) ?></div>

            <div class="hr_employee_profile_info">
                <div class="hr_employee_name_line hr_employee_name_line_large">
                    <h2 class="hr_employee_name_title"><?= h($employee['cele_jmeno']) ?></h2>
                    <span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span>
                </div>
                <p class="hr_employee_profile_note"><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></p>

                <div class="hr_employee_contact_grid">
                    <span class="hr_employee_contact_item"><b class="hr_employee_contact_label">E-mail</b><?= h((string)($employee['email'] ?? '-')) ?></span>
                    <span class="hr_employee_contact_item"><b class="hr_employee_contact_label">Telefon</b><?= h(hr_format_phone((string)$employee['telefon'])) ?></span>
                    <span class="hr_employee_contact_item"><b class="hr_employee_contact_label">Nástup</b><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></span>
                    <span class="hr_employee_contact_item"><b class="hr_employee_contact_label">Typ vztahu</b><?= h((string)($employee['vztah_kod'] ?? '-')) ?></span>
                </div>
            </div>
        </div>

        <div class="hr_employee_profile_side">
            <dl class="hr_profile_facts">
                <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Osobní číslo</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['osobni_cislo'] ?? '-')) ?></dd></div>
                <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Datum narození</dt><dd class="hr_profile_fact_value"><?= h(hr_format_date((string)($employee['datum_narozeni'] ?? ''))) ?></dd></div>
                <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Pracoviště</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
                <div class="hr_profile_fact_item"><dt class="hr_profile_fact_term">Zařazení</dt><dd class="hr_profile_fact_value"><?= h((string)($employee['zarazeni'] ?? '-')) ?></dd></div>
            </dl>
        </div>

        <div class="hr_employee_profile_actions">
            <a class="hr_primary_button hr_panel_button_primary" href="#">Upravit kartu</a>
            <a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zpět</a>
        </div>
    </section>

    <section class="hr_employee_grid">
        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Osobní údaje</h2></div>
            <dl class="hr_detail_list hr_compact_detail_list">
                <div class="hr_detail_item"><dt class="hr_detail_term">Datum narození</dt><dd class="hr_detail_value"><?= h(hr_format_date((string)($employee['datum_narozeni'] ?? ''))) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Rodné číslo</dt><dd class="hr_detail_value"><?= h((string)($employee['rodne_cislo'] ?? '-')) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Pohlaví</dt><dd class="hr_detail_value"><?= h((string)($employee['pohlavi'] ?? '-')) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Telefon</dt><dd class="hr_detail_value"><?= h(hr_format_phone((string)$employee['telefon'])) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">E-mail</dt><dd class="hr_detail_value"><?= h((string)($employee['email'] ?? '-')) ?></dd></div>
            </dl>
        </article>

        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Aktuální pracovní vztah</h2></div>
            <dl class="hr_detail_list hr_compact_detail_list">
                <div class="hr_detail_item"><dt class="hr_detail_term">Druh vztahu</dt><dd class="hr_detail_value"><?= h((string)($employee['vztah_nazev'] ?? '-')) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Datum nástupu</dt><dd class="hr_detail_value"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Zařazení</dt><dd class="hr_detail_value"><?= h((string)($employee['zarazeni'] ?? '-')) ?></dd></div>
                <div class="hr_detail_item"><dt class="hr_detail_term">Pracoviště</dt><dd class="hr_detail_value"><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
            </dl>
        </article>

        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Dokumenty</h2></div>
            <p class="hr_empty_state hr_compact_empty_state">Zatím nejsou evidované žádné dokumenty.</p>
        </article>

        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Lékařské prohlídky</h2></div>
            <p class="hr_empty_state hr_compact_empty_state">Zatím nejsou evidované žádné lékařské prohlídky.</p>
        </article>

        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Dovolená</h2></div>
            <p class="hr_empty_state hr_compact_empty_state">Zatím není evidované žádné čerpání dovolené.</p>
        </article>

        <article class="hr_panel">
            <div class="hr_panel_header"><h2 class="hr_panel_title">Rychlé akce</h2></div>
            <div class="hr_quick_action_grid">
                <a class="hr_quick_action_link" href="#">Upravit údaje</a>
                <a class="hr_quick_action_link" href="#">Přidat dokument</a>
                <a class="hr_quick_action_link" href="#">Zadat prohlídku</a>
                <a class="hr_quick_action_link" href="#">Zadat dovolenou</a>
            </div>
        </article>
    </section>
<?php endif; ?>
