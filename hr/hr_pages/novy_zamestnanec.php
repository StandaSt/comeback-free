<?php
declare(strict_types=1);

$vztahy = hr_fetch_lookup($db, 'hr_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', 'poradi');
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

    <form class="hr-form" method="post" action="<?= h(cb_current_module_url('hr_actions/zamestnanec_uloz.php')) ?>">
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
                <span>Stav</span>
                <select name="stav">
                    <option value="priprava">Příprava</option>
                    <option value="aktivni">Aktivní</option>
                    <option value="preruseny">Přerušený</option>
                    <option value="ukonceny">Ukončený</option>
                </select>
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
                <select name="id_slot" required>
                    <option value="">Vyberte</option>
                    <?php foreach ($sloty as $slot): ?>
                        <option value="<?= h($slot['id']) ?>"><?= h($slot['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Telefon</span>
                <input name="telefon" maxlength="30" autocomplete="tel">
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
