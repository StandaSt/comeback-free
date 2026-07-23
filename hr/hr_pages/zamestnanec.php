<?php
declare(strict_types=1);

// Detail zamestnance se nacita podle id_person.
$idPerson = (int)($_GET['id'] ?? 0);
$employee = $idPerson > 0 ? hr_fetch_employee($db, $idPerson) : null;
?>
<?php if ($employee === null): ?>
    <section class="panel">
        <div class="panel-header">
            <h2>Karta zaměstnance</h2>
        </div>
        <p class="empty-state">Zaměstnanec nebyl nalezen.</p>
    </section>
<?php else: ?>
    <section class="employee-profile panel">
        <div class="employee-profile-main">
            <div class="employee-photo employee-photo-large"><?= h($employee['inicialy']) ?></div>

            <div class="employee-profile-info">
                <div class="employee-name-line employee-name-line-large">
                    <h2><?= h($employee['cele_jmeno']) ?></h2>
                    <span class="badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span>
                </div>
                <p><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></p>

                <div class="employee-contact-grid">
                    <span><b>E-mail</b><?= h((string)($employee['email'] ?? '-')) ?></span>
                    <span><b>Telefon</b><?= h((string)($employee['telefon'] ?? '-')) ?></span>
                    <span><b>Nástup</b><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></span>
                    <span><b>Typ vztahu</b><?= h((string)($employee['vztah_kod'] ?? '-')) ?></span>
                </div>
            </div>
        </div>

        <div class="employee-profile-side">
            <dl class="profile-facts">
                <div><dt>Osobní číslo</dt><dd><?= h((string)($employee['osobni_cislo'] ?? '-')) ?></dd></div>
                <div><dt>Datum narození</dt><dd><?= h(hr_format_date((string)($employee['datum_narozeni'] ?? ''))) ?></dd></div>
                <div><dt>Pracoviště</dt><dd><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
                <div><dt>Zařazení</dt><dd><?= h((string)($employee['zarazeni'] ?? '-')) ?></dd></div>
                <div><dt>Úvazek</dt><dd><?= h((string)($employee['uvazek'] ?? '-')) ?></dd></div>
            </dl>
        </div>

        <div class="employee-profile-actions">
            <a class="primary-button" href="#">Upravit kartu</a>
            <a class="secondary-button" href="?page=zamestnanci">Zpět</a>
        </div>
    </section>

    <section class="employee-card-grid">
        <article class="panel">
            <div class="panel-header"><h2>Osobní údaje</h2></div>
            <dl class="detail-list compact-detail-list">
                <div><dt>Datum narození</dt><dd><?= h(hr_format_date((string)($employee['datum_narozeni'] ?? ''))) ?></dd></div>
                <div><dt>Rodné číslo</dt><dd><?= h((string)($employee['rodne_cislo'] ?? '-')) ?></dd></div>
                <div><dt>Pohlaví</dt><dd><?= h((string)($employee['pohlavi'] ?? '-')) ?></dd></div>
                <div><dt>Telefon</dt><dd><?= h((string)($employee['telefon'] ?? '-')) ?></dd></div>
                <div><dt>E-mail</dt><dd><?= h((string)($employee['email'] ?? '-')) ?></dd></div>
            </dl>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Aktuální pracovní vztah</h2></div>
            <dl class="detail-list compact-detail-list">
                <div><dt>Druh vztahu</dt><dd><?= h((string)($employee['vztah_nazev'] ?? '-')) ?></dd></div>
                <div><dt>Datum nástupu</dt><dd><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></dd></div>
                <div><dt>Úvazek</dt><dd><?= h((string)($employee['uvazek'] ?? '-')) ?></dd></div>
                <div><dt>Hodin týdně</dt><dd><?= h((string)($employee['hodin_tydne'] ?? '-')) ?></dd></div>
                <div><dt>Zařazení</dt><dd><?= h((string)($employee['zarazeni'] ?? '-')) ?></dd></div>
                <div><dt>Pracoviště</dt><dd><?= h((string)($employee['pracoviste'] ?? '-')) ?></dd></div>
            </dl>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Dokumenty</h2></div>
            <p class="empty-state compact-empty-state">Zatím nejsou evidované žádné dokumenty.</p>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Lékařské prohlídky</h2></div>
            <p class="empty-state compact-empty-state">Zatím nejsou evidované žádné lékařské prohlídky.</p>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Dovolená</h2></div>
            <p class="empty-state compact-empty-state">Zatím není evidované žádné čerpání dovolené.</p>
        </article>

        <article class="panel">
            <div class="panel-header"><h2>Rychlé akce</h2></div>
            <div class="quick-action-grid">
                <a href="#">Upravit údaje</a>
                <a href="#">Přidat dokument</a>
                <a href="#">Zadat prohlídku</a>
                <a href="#">Zadat dovolenou</a>
            </div>
        </article>
    </section>
<?php endif; ?>
