<?php
declare(strict_types=1);

$idZamestnanec = (int)($_GET['id'] ?? 0);
$employee = $idZamestnanec > 0 ? hr_fetch_employee($db, $idZamestnanec) : null;
?>
<?php if ($employee === null): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Karta zaměstnance</h2>
        </div>
        <p class="empty-state">Zaměstnanec nebyl nalezen.</p>
    </section>
<?php else: ?>
    <section class="employee-header panel">
        <div class="employee-main">
            <div class="employee-photo"><?= h($employee['inicialy']) ?></div>
            <div>
                <div class="employee-name-line">
                    <h2><?= h($employee['cele_jmeno']) ?></h2>
                    <span class="badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span>
                </div>
                <p><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></p>
                <div class="employee-meta">
                    <span>Osobní číslo: <?= h((string)($employee['osobni_cislo'] ?? '-')) ?></span>
                    <span>Nástup: <?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></span>
                    <span><?= h((string)($employee['vztah_kod'] ?? '-')) ?></span>
                    <?php if (!empty($employee['telefon'])): ?>
                        <span><?= h((string)$employee['telefon']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($employee['email'])): ?>
                        <span><?= h((string)$employee['email']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="employee-actions">
            <a class="secondary-button" href="?page=zamestnanci">Zpět na seznam</a>
        </div>
    </section>

    <nav class="tabs" aria-label="Karta zaměstnance">
        <a class="active" href="#">Přehled</a>
    </nav>

    <section class="employee-grid">
        <article class="panel">
            <div class="panel-header"><h2>Osobní údaje</h2></div>
            <dl class="detail-list">
                <div><dt>Jméno</dt><dd><?= h((string)($employee['jmeno'] ?? '-')) ?></dd></div>
                <div><dt>Příjmení</dt><dd><?= h((string)($employee['prijmeni'] ?? '-')) ?></dd></div>
                <div><dt>Datum narození</dt><dd><?= h(hr_format_date((string)($employee['datum_narozeni'] ?? ''))) ?></dd></div>
                <div><dt>Rodné číslo</dt><dd><?= h((string)($employee['rodne_cislo'] ?? '-')) ?></dd></div>
                <div><dt>Pohlaví</dt><dd><?= h((string)($employee['pohlavi'] ?? '-')) ?></dd></div>
                <div><dt>Telefon</dt><dd><?= h((string)($employee['telefon'] ?? '-')) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= h((string)($employee['email'] ?? '-')) ?></dd></div>
            </dl>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Aktuální pracovní vztah</h2></div>
            <dl class="detail-list">
                <div><dt>Druh vztahu</dt><dd><?= h((string)($employee['vztah_nazev'] ?? '-')) ?></dd></div>
                <div><dt>Datum nástupu</dt><dd><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></dd></div>
                <div><dt>Datum ukončení</dt><dd><?= h(hr_format_date((string)($employee['datum_ukonceni'] ?? ''))) ?></dd></div>
                <div><dt>Úvazek</dt><dd><?= h((string)($employee['uvazek'] ?? '-')) ?></dd></div>
                <div><dt>Hodin týdně</dt><dd><?= h((string)($employee['hodin_tydne'] ?? '-')) ?></dd></div>
                <div><dt>Zařazení</dt><dd><?= h((string)($employee['zarazeni'] ?? '-')) ?></dd></div>
                <div><dt>Pracoviště</dt><dd><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
            </dl>
        </article>
    </section>
<?php endif; ?>
