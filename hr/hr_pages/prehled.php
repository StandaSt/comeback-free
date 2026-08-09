<?php
declare(strict_types=1);

$prehled = hr_fetch_prehled($db);
$nabor = $prehled['nabor'];
$zamestnanci = $prehled['zamestnanci'];
$pozadavky = $prehled['pozadavky'];
$kReseni = $prehled['k_reseni'];
$dokumenty = $prehled['dokumenty'];
$lekarskeProhlidky = $prehled['lekarske_prohlidky'];
$skoleni = $prehled['skoleni'];
$dovolene = $prehled['dovolene'];
$latest = $prehled['latest'];
?>
<section class="hr_stats_grid">
    <a class="hr_stat_box hr_accent_blue" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>" aria-label="Nábor">
        <div class="hr_stat_icon">N</div>
        <div>
            <span class="hr_stat_label">Nábor</span>
            <strong class="hr_stat_value"><?= h($nabor['novy']) ?> / <?= h($nabor['v_procesu']) ?></strong>
            <small class="hr_stat_note">noví / v procesu</small>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_green" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>" aria-label="Zaměstnanci">
        <div class="hr_stat_icon">Z</div>
        <div>
            <span class="hr_stat_label">Zaměstnanci</span>
            <strong class="hr_stat_value"><?= h($zamestnanci['HPP']) ?> / <?= h($zamestnanci['DPC']) ?> / <?= h($zamestnanci['DPP']) ?></strong>
            <small class="hr_stat_note">HPP / DPČ / DPP</small>
        </div>
    </a>

    <a class="hr_stat_box hr_accent_orange" href="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" aria-label="Požadavky">
        <div class="hr_stat_icon">P</div>
        <div>
            <span class="hr_stat_label">Požadavky</span>
            <strong class="hr_stat_value"><?= h($pozadavky['celkem']) ?> / <?= h($pozadavky['instor']) ?> / <?= h($pozadavky['kuryr']) ?></strong>
            <small class="hr_stat_note">celkem / instor / kurýr</small>
        </div>
    </a>

    <article class="hr_stat_box hr_accent_red">
        <div class="hr_stat_icon">!</div>
        <div>
            <span class="hr_stat_label">K řešení</span>
            <strong class="hr_stat_value"><?= h($kReseni['koncici_smlouvy']) ?> / <?= h($kReseni['zdravotni_prohlidky']) ?> / <?= h($kReseni['bozp']) ?></strong>
            <small class="hr_stat_note">smlouvy / prohlídky / BOZP</small>
        </div>
    </article>
</section>

<section class="hr_prehled_grid">
    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Dokumenty</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=dokumenty')) ?>">Zobrazit</a>
        </div>
        <?php if ($dokumenty === []): ?>
            <p class="hr_empty_state">Zatím nejsou evidované žádné nové dokumenty.</p>
        <?php else: ?>
            <ul class="hr_activity_list">
                <?php foreach ($dokumenty as $dokument): ?>
                    <li class="hr_activity_item">
                        <span class="hr_dot hr_blue"></span>
                        <strong class="hr_activity_name"><?= h($dokument['osoba']) ?></strong>
                        <span><?= h($dokument['typ']) ?> · <?= h($dokument['nazev']) ?></span>
                        <time class="hr_activity_time"><?= h(hr_format_date((string)$dokument['zadano'])) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Lékařské prohlídky</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=prohlidky')) ?>">Zobrazit</a>
        </div>
        <?php if ($lekarskeProhlidky === []): ?>
            <p class="hr_empty_state">Evidence lékařských prohlídek zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Školení</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=skoleni')) ?>">Zobrazit</a>
        </div>
        <?php if ($skoleni === []): ?>
            <p class="hr_empty_state">Evidence školení zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Dovolené</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=dovolene')) ?>">Zobrazit</a>
        </div>
        <?php if ($dovolene === []): ?>
            <p class="hr_empty_state">Evidence dovolených zatím není napojená.</p>
        <?php else: ?>
            <ul class="hr_activity_list"></ul>
        <?php endif; ?>
    </article>
</section>

<section class="hr_prehled_grid">
    <article class="hr_panel hr_panel_wide">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Poslední zaměstnanci</h2>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zobrazit všechny</a>
        </div>
        <?php if ($latest === []): ?>
            <p class="hr_empty_state">Zatím není vložený žádný zaměstnanec.</p>
        <?php else: ?>
            <ul class="hr_activity_list">
                <?php foreach ($latest as $employee): ?>
                    <li class="hr_activity_item">
                        <span class="hr_dot hr_blue"></span>
                        <strong class="hr_activity_name"><?= h($employee['cele_jmeno']) ?></strong>
                        <span><?= h((string)($employee['zarazeni'] ?? '-')) ?> · <?= h((string)($employee['pracoviste'] ?? '-')) ?></span>
                        <time class="hr_activity_time"><?= h(hr_format_date((string)($employee['zadano'] ?? ''))) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="hr_panel">
        <div class="hr_panel_header">
            <h2 class="hr_panel_title">Rychlé odkazy</h2>
        </div>
        <div class="hr_quick_links">
            <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Seznam zaměstnanců <span>›</span></a>
            <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=pracovni_pomery')) ?>">Pracovní poměry <span>›</span></a>
            <a class="hr_quick_link" href="<?= h(cb_root_url('index.php?m=hr&page=dokumenty')) ?>">Dokumenty <span>›</span></a>
        </div>
    </article>
</section>

<section class="hr_panel">
    <div class="hr_panel_header">
        <h2 class="hr_panel_title">Seznam posledních záznamů</h2>
        <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">Zobrazit všechny</a>
    </div>
    <?php if ($latest === []): ?>
        <p class="hr_empty_state">HR evidence je připravená, ale zatím neobsahuje žádná data.</p>
    <?php else: ?>
        <div class="hr_table_wrap">
            <table class="hr_table">
                <thead>
                    <tr>
                        <th class="hr_table_cell hr_table_head">Zaměstnanec</th>
                        <th class="hr_table_cell hr_table_head">Pracoviště</th>
                        <th class="hr_table_cell hr_table_head">Zařazení</th>
                        <th class="hr_table_cell hr_table_head">Datum nástupu</th>
                        <th class="hr_table_cell hr_table_head">Typ vztahu</th>
                        <th class="hr_table_cell hr_table_head">Stav</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest as $employee): ?>
                        <tr>
                            <td class="hr_table_cell"><a class="hr_table_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                            <td class="hr_table_cell"><?= h((string)($employee['pracoviste'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                            <td class="hr_table_cell"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                            <td class="hr_table_cell"><span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
