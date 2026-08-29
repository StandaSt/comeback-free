<?php
declare(strict_types=1);

/*
 * Ucel souboru: Vykresluje formular pro rucni zalozeni noveho zamestnance.
 * Pouziva data pripravena datovym poskytovatelem a nevytvari PP ani layout.
 */
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div>
            <h2 class="hr_panel_title">Nový zaměstnanec</h2>
            <p class="hr_muted">Základní údaje pro první HR kartu</p>
        </div>
    </div>

    <form class="hr_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=novy_zamestnanec')) ?>">
        <input type="hidden" name="cb_action" value="hr_zamestnanec_ulozit">
        <div class="hr_form_grid">
            <label class="hr_form_label">
                <span>Jméno</span>
                <input name="jmeno" required maxlength="60" autocomplete="given-name">
            </label>

            <label class="hr_form_label">
                <span>Příjmení</span>
                <input name="prijmeni" required maxlength="80" autocomplete="family-name">
            </label>

            <label class="hr_form_label">
                <span>Osobní číslo</span>
                <input name="osobni_cislo" maxlength="20">
            </label>

            <label class="hr_form_label">
                <span>Typ vztahu</span>
                <select name="id_pracovni_vztah_typ" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($vztahy as $vztah): ?>
                        <option value="<?= h($vztah['id']) ?>"><?= h($vztah['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="hr_form_label">
                <span>Datum nástupu</span>
                <input type="date" name="datum_nastupu" required value="<?= h(date('Y-m-d')) ?>">
            </label>

            <label class="hr_form_label">
                <span>Pobočka</span>
                <select name="id_pob" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($pobocky as $pobocka): ?>
                        <option value="<?= h($pobocka['id']) ?>"><?= h($pobocka['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="hr_form_label">
                <span>Zařazení</span>
                <span class="hr_slot_choice">
                    <select name="id_slot" data-slot-select required>
                        <option value="">Vyberte</option>
                        <?php foreach ($sloty as $slot): ?>
                            <option value="<?= h($slot['id']) ?>"><?= h($slot['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="__jine__">Jiné</option>
                    </select>
                    <input class="hr_slot_choice_input" type="text" name="slot_jine" maxlength="80" disabled data-slot-other>
                </span>
            </label>

            <label class="hr_form_label">
                <span>Telefon</span>
                <span class="hr_phone_field"><span class="hr_phone_prefix">+420</span><input class="hr_phone_input" name="telefon" maxlength="11" autocomplete="tel" data-phone-cz></span>
            </label>

            <label class="hr_form_label">
                <span>E-mail</span>
                <input type="email" name="email" maxlength="150" autocomplete="email" required>
            </label>
        </div>

        <div class="hr_form_actions">
            <a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zrušit</a>
            <button class="hr_primary_button" type="submit">Uložit zaměstnance</button>
        </div>
    </form>
</section>
