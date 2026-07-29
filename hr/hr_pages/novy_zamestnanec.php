<?php
declare(strict_types=1);

/**
 * Stranka s formularem pro rucni zalozeni zamestnance.
 */
$vztahy = hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', 'id_pracovni_vztah_typ');
$pobocky = hr_fetch_lookup($db, 'pobocka', 'id_pob', 'nazev');
$sloty = hr_fetch_lookup($db, 'cis_slot', 'id_slot', 'slot');
?>
<section class="panel">
    <div class="panel-header">
        <div>
            <h2>Nový zaměstnanec</h2>
            <p class="muted">Základní údaje pro první HR kartu</p>
        </div>
    </div>

    <form class="hr-form" method="post" action="?page=novy_zamestnanec">
        <div class="form-grid">
            <label>
                <span>Jméno</span>
                <input name="jmeno" required maxlength="60" autocomplete="given-name">
            </label>

            <label>
                <span>Příjmení</span>
                <input name="prijmeni" required maxlength="80" autocomplete="family-name">
            </label>

            <label>
                <span>Osobní číslo</span>
                <input name="osobni_cislo" maxlength="20">
            </label>

            <label>
                <span>Typ vztahu</span>
                <select name="id_pracovni_vztah_typ" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($vztahy as $vztah): ?>
                        <option value="<?= h($vztah['id']) ?>"><?= h($vztah['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Datum nástupu</span>
                <input type="date" name="datum_nastupu" required value="<?= h(date('Y-m-d')) ?>">
            </label>

            <label>
                <span>Pobočka</span>
                <select name="id_pob" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($pobocky as $pobocka): ?>
                        <option value="<?= h($pobocka['id']) ?>"><?= h($pobocka['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Zařazení</span>
                <span class="hr-slot-choice">
                    <select name="id_slot" data-slot-select required>
                        <option value="">Vyberte</option>
                        <?php foreach ($sloty as $slot): ?>
                            <option value="<?= h($slot['id']) ?>"><?= h($slot['label']) ?></option>
                        <?php endforeach; ?>
                        <option value="__jine__">Jiné</option>
                    </select>
                    <input type="text" name="slot_jine" maxlength="80" disabled data-slot-other>
                </span>
            </label>

            <label>
                <span>Telefon</span>
                <span class="hr-phone-field"><span class="hr-phone-prefix">+420</span><input name="telefon" maxlength="11" autocomplete="tel" data-phone-cz></span>
            </label>

            <label>
                <span>E-mail</span>
                <input type="email" name="email" maxlength="150" autocomplete="email">
            </label>
        </div>

        <div class="form-actions">
            <a class="secondary-button" href="?page=zamestnanci">Zrušit</a>
            <button class="primary-button" type="submit">Uložit zaměstnance</button>
        </div>
    </form>
</section>
