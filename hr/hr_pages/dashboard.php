<?php
declare(strict_types=1);

$dashboard = hr_fetch_dashboard($db);
$counts = $dashboard['counts'];
$latest = $dashboard['latest'];
?>
<section class="stats-grid">
    <article class="stat-card accent-blue">
        <div class="stat-icon">👥</div>
        <div>
            <span>Aktivní zaměstnanci</span>
            <strong><?= h($counts['aktivni']) ?></strong>
            <small>aktuální stav v HR</small>
        </div>
    </article>

    <article class="stat-card accent-green">
        <div class="stat-icon">＋</div>
        <div>
            <span>V přípravě</span>
            <strong><?= h($counts['priprava']) ?></strong>
            <small>rozpracované karty</small>
        </div>
    </article>

    <article class="stat-card accent-orange">
        <div class="stat-icon">▣</div>
        <div>
            <span>Přerušení</span>
            <strong><?= h($counts['preruseny']) ?></strong>
            <small>přerušený vztah</small>
        </div>
    </article>

    <article class="stat-card accent-red">
        <div class="stat-icon">▤</div>
        <div>
            <span>Ukončení</span>
            <strong><?= h($counts['ukonceny']) ?></strong>
            <small>ukončené karty</small>
        </div>
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
            <a href="?page=novy_zamestnanec">Nový zaměstnanec <span>›</span></a>
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
