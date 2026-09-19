<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/kontrola_reportu_data.php';
require_once __DIR__ . '/../lib/format_datum_cas.php';

$cbKontrolaPouzitGlobalniObdobi = !empty($cbKontrolaPouzitGlobalniObdobi);
$cbKontrolaPozadovanaPobocka = (int)($cbKontrolaPozadovanaPobocka ?? 0);
$cbKontrolaData = null;
$cbKontrolaError = '';
try {
    $cbKontrolaData = cb_kontrola_reportu_data(
        db(),
        $cbKontrolaPouzitGlobalniObdobi,
        $cbKontrolaPozadovanaPobocka
    );
} catch (Throwable $e) {
    $cbKontrolaError = cb_chyba_uzivatel($e, [
        'module' => 'PROVOZ',
        'action' => 'Načtení kontroly reportů',
    ]);
    if (!cb_kontrola_reportu_ma_pravo()) {
        http_response_code(403);
    }
}

$cbKontrolaFormat = static function (string $type, mixed $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return cb_format($type, $value);
};

$cbKontrolaDatesLabel = static function (array $dates): string {
    $total = count($dates);
    $visibleDates = array_slice($dates, 0, 14);
    $labels = [];
    foreach ($visibleDates as $date) {
        $labels[] = cb_format('d', (string)$date);
    }

    $label = implode(', ', $labels);
    if ($total > count($visibleDates)) {
        $label .= ' a dalších ' . ($total - count($visibleDates)) . ' dní';
    }

    return $label;
};

$cbKontrolaPeriodDateLabel = static function (string $date): string {
    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Prague'));
    if (!$dateTime instanceof DateTimeImmutable || $dateTime->format('Y-m-d') !== $date) {
        return $date;
    }

    return cb_dt_weekday_date_label_cs($dateTime, true);
};
?>
<section class="blok provoz_kontrola_reportu" data-pp-block="kontrola_reportu" data-gn="1">
<?php if ($cbKontrolaError !== ''): ?>
    <div class="zr_missing_report_bar"><?= h($cbKontrolaError) ?></div>
<?php else: ?>
    <?php
    $branch = (array)$cbKontrolaData['branch'];
    $period = (array)$cbKontrolaData['period'];
    $totals = (array)$cbKontrolaData['totals'];
    $people = (array)$cbKontrolaData['people'];
    $slotLabels = (array)$cbKontrolaData['slot_labels'];
    $nameMismatches = (array)$cbKontrolaData['name_mismatches'];
    $missingDates = (array)$cbKontrolaData['missing_dates'];
    ?>
    <div class="zr_layout kontrola_reportu_layout">
        <div class="zr_main">
            <section class="zr_top">
                <section class="card_section bg_bila zaobleni_10 zr_section zr_intro_section">
                    <table class="zr_table">
                        <tbody>
                            <tr><th class="zr_intro_label txt_l">Pobočka</th><td><strong><?= h((string)$branch['name']) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Od</th><td><strong><?= h($cbKontrolaPeriodDateLabel((string)($period['display_from'] ?? $period['from']))) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Do</th><td><strong><?= h($cbKontrolaPeriodDateLabel((string)($period['display_to'] ?? $period['to']))) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Reportů</th><td><strong><?= h($cbKontrolaFormat('i', $totals['reports_count'])) ?></strong></td></tr>
                        </tbody>
                    </table>
                </section>
                <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_finance">
                    <h2 class="card_section_title">Pokladna a výdaje</h2>
                    <div class="kontrola_reportu_values">
                        <span>Hotovost <strong><?= h($cbKontrolaFormat('p', $totals['hotovost'])) ?></strong></span>
                        <span>Terminál <strong><?= h($cbKontrolaFormat('p', $totals['terminal'])) ?></strong></span>
                        <span>Stravenky <strong><?= h($cbKontrolaFormat('p', $totals['stravenky'])) ?></strong></span>
                        <span>PHM firemní auta <strong><?= h($cbKontrolaFormat('p', $totals['vydaje_benzin'])) ?></strong></span>
                        <span>PHM výroba <strong><?= h($cbKontrolaFormat('p', $totals['vydaje_auta'])) ?></strong></span>
                        <span>PHM soukromé <strong><?= h($cbKontrolaFormat('p', $totals['vydaje_phm_soukrome'])) ?></strong></span>
                        <span>Suroviny <strong><?= h($cbKontrolaFormat('p', $totals['vydaje_suroviny'])) ?></strong></span>
                        <span>Ostatní <strong><?= h($cbKontrolaFormat('p', $totals['vydaje_ostatni'])) ?></strong></span>
                    </div>
                </section>
                <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_revenue">
                    <h2 class="card_section_title">Tržba a online platby</h2>
                    <div class="kontrola_reportu_total"><span>Tržba</span><strong><?= h($cbKontrolaFormat('p', $totals['trzba'])) ?></strong></div>
                    <table class="zr_table kontrola_reportu_metric_table"><tbody>
                        <tr><td>Wolt</td><td><?= h($cbKontrolaFormat('p', $totals['wolt'])) ?></td></tr>
                        <tr><td>Bolt</td><td><?= h($cbKontrolaFormat('p', $totals['bolt'])) ?></td></tr>
                        <tr><td>Foodora</td><td><?= h($cbKontrolaFormat('p', $totals['damejidlo'])) ?></td></tr>
                        <tr><td>Web</td><td><?= h($cbKontrolaFormat('p', $totals['web'])) ?></td></tr>
                        <tr><td>Wolt drive cash</td><td><?= h($cbKontrolaFormat('p', $totals['wolt_cash'])) ?></td></tr>
                        <tr><td>DJ cash</td><td><?= h($cbKontrolaFormat('p', $totals['dj_cash'])) ?></td></tr>
                    </tbody></table>
                </section>
            </section>

            <?php if ($missingDates !== []): ?>
                <div class="kontrola_reportu_missing">Bez uzavřeného reportu: <?= h($cbKontrolaDatesLabel($missingDates)) ?></div>
            <?php endif; ?>

            <div class="kontrola_reportu_people_grid">
            <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_people">
                <div class="kontrola_reportu_people_head">
                    <h2 class="card_section_title"><?= h($slotLabels[1] ?? 'Slot 1') ?></h2>
                </div>
                <table class="zr_table kontrola_reportu_people_table">
                    <colgroup><col><col class="kontrola_reportu_col_days"><col class="kontrola_reportu_col_hours"></colgroup>
                    <thead><tr><th class="txt_l">Jméno</th><th>Dní</th><th>Hodin</th></tr></thead>
                    <tbody>
                    <?php if ($people['instor'] === []): ?>
                        <tr><td colspan="3" class="kontrola_reportu_empty">Bez uzavřených záznamů</td></tr>
                    <?php else: ?>
                        <?php foreach ((array)$people['instor'] as $person): ?>
                            <tr><td><strong><?= h((string)$person['name']) ?></strong></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $person['days'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('h', $person['hours'])) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2" class="txt_l">Celkem</th><td class="txt_r"><?= h($cbKontrolaFormat('h', $people['instor_hours'])) ?></td></tr>
                    </tfoot>
                </table>
            </section>

            <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_people">
                <div class="kontrola_reportu_people_head">
                    <h2 class="card_section_title"><?= h($slotLabels[2] ?? 'Slot 2') ?></h2>
                </div>
                <table class="zr_table kontrola_reportu_people_table">
                    <colgroup><col><col class="kontrola_reportu_col_days"><col class="kontrola_reportu_col_hours"><col class="kontrola_reportu_col_deliveries"></colgroup>
                    <thead><tr><th class="txt_l">Jméno</th><th>Dní</th><th>Hodin</th><th>Rozvozů</th></tr></thead>
                    <tbody>
                    <?php if ($people['kuryr'] === []): ?>
                        <tr><td colspan="4" class="kontrola_reportu_empty">Bez uzavřených záznamů</td></tr>
                    <?php else: ?>
                        <?php foreach ((array)$people['kuryr'] as $person): ?>
                            <tr><td><strong><?= h((string)$person['name']) ?></strong></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $person['days'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('h', $person['hours'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $person['deliveries'])) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2" class="txt_l">Celkem</th><td class="txt_r"><?= h($cbKontrolaFormat('h', $people['kuryr_hours'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $people['kuryr_deliveries'])) ?></td></tr>
                    </tfoot>
                </table>
            </section>
            </div>
        </div>

        <aside class="zr_side">
            <section class="card_section bg_bila zaobleni_10 zr_section">
                <h2 class="card_section_title">Kontrola</h2>
                <div class="kontrola_reportu_total"><span>COL</span><strong><?= h($cbKontrolaFormat('pr', $totals['col_pomer'])) ?></strong></div>
                <table class="zr_table kontrola_reportu_metric_table"><tbody>
                    <tr><td>Rozdíl pokladna</td><td><?= h($cbKontrolaFormat('p', $totals['rozdil'])) ?></td></tr>
                    <tr><td>Výdajové doklady</td><td><?= h($cbKontrolaFormat('i', $totals['vydaje_doklady_ks'])) ?></td></tr>
                    <tr><td>Zrušené obj. ks</td><td><?= h($cbKontrolaFormat('i', $totals['zrusene_obj_ks'])) ?></td></tr>
                    <tr><td>Zrušené obj. Kč</td><td><?= h($cbKontrolaFormat('p', $totals['zrusene_obj_kc'])) ?></td></tr>
                    <tr><td>Průměrný make time</td><td><?= h($cbKontrolaFormat('ms', $totals['make_time_prumer_sec'])) ?></td></tr>
                    <tr><td>Nezrušené celkem</td><td><?= h($cbKontrolaFormat('i', $totals['objednavky_nezrusene_ks'])) ?></td></tr>
                    <tr><td>Naše rozvozy</td><td><?= h($cbKontrolaFormat('i', $totals['nase_rozvozy_ks'])) ?></td></tr>
                    <tr><td>Zpožděné naše +5 min</td><td><?= h($cbKontrolaFormat('i', $totals['zpozdene_rozvozy_5_min'])) ?></td></tr>
                    <tr><td>Zpožděné naše +5 min</td><td><?= h($cbKontrolaFormat('pr', $totals['nase_rozvozy_pozde_pomer'])) ?></td></tr>
                    <tr><td>Doručené včas</td><td><?= h($cbKontrolaFormat('pr', $totals['doruceno_vcas_pomer'])) ?></td></tr>
                    <tr><td>Wolt Drive</td><td><?= h($cbKontrolaFormat('i', $totals['woltdrive_ks'])) ?></td></tr>
                    <tr><td>Pozdě WoltDrive 5+</td><td><?= h($cbKontrolaFormat('i', $totals['woltdrive_pozde_5_min'])) ?></td></tr>
                    <tr><td>Pozdě přiřazené naší vinou</td><td><?= h($cbKontrolaFormat('i', $totals['woltdrive_pozde_nase_vina'])) ?></td></tr>
                    <tr><td>Zpožděné WoltDrivem</td><td><?= h($cbKontrolaFormat('i', $totals['woltdrive_zpozdene_ks'])) ?></td></tr>
                    <tr><td>Zpožděné WoltDrivem</td><td><?= h($cbKontrolaFormat('pr', $totals['woltdrive_zpozdene_pomer'])) ?></td></tr>
                </tbody></table>
            </section>
        </aside>
    </div>
    <?php if ($nameMismatches !== []): ?>
        <section class="zr_restia_name_mismatches kontrola_reportu_name_mismatches" aria-label="Nesrovnalosti ve jménech kurýrů">
            <div class="kontrola_reportu_name_layout">
                <div class="kontrola_reportu_name_list">
                    <strong>Nesrovnalosti ve jménech kurýrů za zvolené období:</strong>
                    <table class="zr_restia_name_table">
                        <tbody>
                            <?php foreach ($nameMismatches as $nameMismatch): ?>
                                <tr>
                                    <td class="kontrola_reportu_name_date"><?= h($cbKontrolaPeriodDateLabel((string)($nameMismatch['date'] ?? ''))) ?></td>
                                    <td class="zr_restia_name_label">Restia:</td>
                                    <td><?= h((string)($nameMismatch['restia'] ?? '')) ?></td>
                                    <td class="zr_restia_name_vs">vs</td>
                                    <td class="zr_restia_name_label">IS:</td>
                                    <td><?= h((string)($nameMismatch['is'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="kontrola_reportu_name_help">
                    Pro korektní načítání rozvozů z Restie je třeba,<br>
                    aby jména kurýrů byla v Restii i v IS zadána správně.<br>
                    <strong>Česky, s diakritikou a ve správném pořadí !!</strong><br>
                    <strong>Proveďte opravy v Restii,</strong><br>
                    <strong>pokud je třeba opravit jména v IS,</strong><br>
                    zapište to do HELPDESKU a admin to opraví.
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</section>
