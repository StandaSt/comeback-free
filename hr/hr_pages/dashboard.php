<?php
declare(strict_types=1);

$dashboard = hr_fetch_dashboard($db);
$nabor = $dashboard['nabor'];
$zamestnanci = $dashboard['zamestnanci'];
$pozadavky = $dashboard['pozadavky'];
$kReseni = $dashboard['k_reseni'];
$dokumenty = $dashboard['dokumenty'];
$lekarskeProhlidky = $dashboard['lekarske_prohlidky'];
$skoleni = $dashboard['skoleni'];
$dovolene = $dashboard['dovolene'];
$latest = $dashboard['latest'];
?>
<section class="stats-grid">
    <a class="stat-card accent-blue" href="?page=nabor" aria-label="Nábor">
        <div class="stat-icon">N</div>
        <div>
            <span>Nábor</span>
            <strong><?= h($nabor['novy']) ?> / <?= h($nabor['v_procesu']) ?></strong>
            <small>noví / v procesu</small>
        </div>
    </a>

    <a class="stat-card accent-green" href="?page=zamestnanci" aria-label="Zaměstnanci">
        <div class="stat-icon">Z</div>
        <div>
            <span>Zaměstnanci</span>
            <strong><?= h($zamestnanci['HPP']) ?> / <?= h($zamestnanci['DPC']) ?> / <?= h($zamestnanci['DPP']) ?></strong>
            <small>HPP / DPČ / DPP</small>
        </div>
    </a>

    <a class="stat-card accent-orange" href="?page=pozadavky" aria-label="Požadavky">
        <div class="stat-icon">P</div>
        <div>
            <span>Požadavky</span>
            <strong><?= h($pozadavky['celkem']) ?> / <?= h($pozadavky['instor']) ?> / <?= h($pozadavky['kuryr']) ?></strong>
            <small>celkem / instor / kurýr</small>
        </div>
    </a>

    <article class="stat-card accent-red">
        <div class="stat-icon">!</div>
        <div>
            <span>K řešení</span>
            <strong><?= h($kReseni['koncici_smlouvy']) ?> / <?= h($kReseni['zdravotni_prohlidky']) ?> / <?= h($kReseni['bozp']) ?></strong>
            <small>smlouvy / prohlídky / BOZP</small>
        </div>
    </article>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-header">
            <h2>Dokumenty</h2>
            <a href="?page=dokumenty">Zobrazit</a>
        </div>
        <?php if ($dokumenty === []): ?>
            <p class="empty-state">Zatím nejsou evidované žádné nové dokumenty.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($dokumenty as $dokument): ?>
                    <li>
                        <span class="dot blue"></span>
                        <strong><?= h($dokument['osoba']) ?></strong>
                        <span><?= h($dokument['typ']) ?> · <?= h($dokument['nazev']) ?></span>
                        <time><?= h(hr_format_date((string)$dokument['zadano'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Lékařské prohlídky</h2>
            <a href="?page=prohlidky">Zobrazit</a>
        </div>
        <?php if ($lekarskeProhlidky === []): ?>
            <p class="empty-state">Evidence lékařských prohlídek zatím není napojená.</p>
        <?php else: ?>
            <ul class="activity-list"></ul>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Školení</h2>
            <a href="?page=skoleni">Zobrazit</a>
        </div>
        <?php if ($skoleni === []): ?>
            <p class="empty-state">Evidence školení zatím není napojená.</p>
        <?php else: ?>
            <ul class="activity-list"></ul>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Dovolené</h2>
            <a href="?page=dovolene">Zobrazit</a>
        </div>
        <?php if ($dovolene === []): ?>
            <p class="empty-state">Evidence dovolených zatím není napojená.</p>
        <?php else: ?>
            <ul class="activity-list"></ul>
        <?php endif; ?>
    </article>
</section>

<section class="dashboard-grid">
    <article class="panel panel-wide">
        <div class="panel-header">
            <h2>Poslední zaměstnanci</h2>
            <a href="?page=zamestnanci">Zobrazit všechny</a>
        </div>
        <?php if ($latest === []): ?>
            <p class="empty-state">Zatím není vložený žádný zaměstnanec.</p>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($latest as $employee): ?>
                    <li>
                        <span class="dot blue"></span>
                        <strong><?= h($employee['cele_jmeno']) ?></strong>
                        <span><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></span>
                        <time><?= h(hr_format_date((string)($employee['zadano'] ?? ''))) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Rychlé odkazy</h2>
        </div>
        <div class="quick-links">
            <a href="?page=zamestnanci">Seznam zaměstnanců <span>›</span></a>
            <a href="?page=pracovni_pomery">Pracovní poměry <span>›</span></a>
            <a href="?page=dokumenty">Dokumenty <span>›</span></a>
        </div>
    </article>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Seznam posledních záznamů</h2>
        <a href="?page=zamestnanci">Zobrazit všechny</a>
    </div>
    <?php if ($latest === []): ?>
        <p class="empty-state">HR evidence je připravená, ale zatím neobsahuje žádná data.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Zaměstnanec</th>
                        <th>Pracoviště</th>
                        <th>Zařazení</th>
                        <th>Datum nástupu</th>
                        <th>Typ vztahu</th>
                        <th>Stav</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest as $employee): ?>
                        <tr>
                            <td><a href="?page=zamestnanec&id=<?= h($employee['id_zamestnanec']) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                            <td><?= h((string)($employee['pracoviste'] ?? '-')) ?></td>
                            <td><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                            <td><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                            <td><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                            <td><span class="badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
