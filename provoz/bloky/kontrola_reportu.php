<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/kontrola_reportu_data.php';
require_once __DIR__ . '/../lib/format_datum_cas.php';

$cbKontrolaPouzitGlobalniObdobi = !empty($cbKontrolaPouzitGlobalniObdobi);
$cbKontrolaPozadovanaPobocka = (int)($cbKontrolaPozadovanaPobocka ?? 0);
$cbKontrolaPorovnatGoogle = (string)($_POST['zr_google_compare'] ?? $_GET['zr_google_compare'] ?? '') === '1';
$cbKontrolaData = null;
$cbKontrolaError = '';
try {
    $cbKontrolaData = cb_kontrola_reportu_data(
        db(),
        $cbKontrolaPouzitGlobalniObdobi,
        $cbKontrolaPozadovanaPobocka,
        $cbKontrolaPorovnatGoogle
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

$cbKontrolaPair = static function (string $type, mixed $isValue, mixed $googleValue) use ($cbKontrolaFormat): string {
    $isText = $cbKontrolaFormat($type, $isValue);
    $googleText = $cbKontrolaFormat($type, $googleValue);
    $format = match ($type) {
        'p' => 'money',
        'pr' => 'percent',
        'h' => 'hours',
        'ms', 'm' => 'minutes',
        'i' => 'integer',
        default => 'number',
    };
    $same = cb_kontrola_reportu_values_same($isValue, $googleValue, $format);
    $googleClass = $same
        ? 'kontrola_reportu_google_value'
        : 'kontrola_reportu_google_value kontrola_reportu_google_value--different';

    return '<span class="kontrola_reportu_compare_value"><span>' . h($isText) . '</span><span class="'
        . $googleClass . '">(' . h($googleText) . ')</span></span>';
};

$cbKontrolaDifferenceFormat = static function (mixed $value, string $format) use ($cbKontrolaFormat): string {
    if ($format === 'text') {
        return $value === null || $value === '' ? '—' : (string)$value;
    }
    $types = [
        'money' => 'p', 'percent' => 'pr', 'minutes' => 'ms',
        'hours' => 'h', 'integer' => 'i', 'number' => 'n',
    ];

    return $cbKontrolaFormat($types[$format] ?? 'n', $value);
};

$cbKontrolaDifferenceDelta = static function (mixed $isValue, mixed $googleValue, string $format) use ($cbKontrolaDifferenceFormat): string {
    if (!is_numeric($isValue) || !is_numeric($googleValue)) {
        return '';
    }
    $difference = (float)$isValue - (float)$googleValue;
    if (abs($difference) < 0.00005) {
        return $cbKontrolaDifferenceFormat(0, $format);
    }

    return ($difference > 0 ? '+' : '−') . $cbKontrolaDifferenceFormat(abs($difference), $format);
};
?>
<section class="blok provoz_kontrola_reportu<?= $cbKontrolaPorovnatGoogle ? ' kontrola_reportu_google_mode' : '' ?>" data-pp-block="kontrola_reportu" data-gn="1" data-google-compare="<?= $cbKontrolaPorovnatGoogle ? '1' : '0' ?>">
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
    $processedCourierNames = (array)($nameMismatches['processed'] ?? []);
    $unmatchedCourierNames = (array)($nameMismatches['unmatched'] ?? []);
    $missingDates = (array)$cbKontrolaData['missing_dates'];
    $googleComparison = $cbKontrolaPorovnatGoogle && is_array($cbKontrolaData['google_comparison'] ?? null)
        ? (array)$cbKontrolaData['google_comparison']
        : [];
    $googleTotals = (array)($googleComparison['totals'] ?? []);
    $googleCoverage = (array)($googleComparison['coverage'] ?? []);
    $dailyDifferences = (array)($googleComparison['daily_differences'] ?? []);
    $dailyDifferenceGroups = [
        'pokladna' => ['label' => 'Pokladna', 'items' => []],
        'trzba' => ['label' => 'Tržba', 'items' => []],
        'kontrola' => ['label' => 'Kontrola', 'items' => []],
        'hodiny' => ['label' => 'Hodiny (pizzař + kurýr)', 'items' => []],
    ];
    foreach ($dailyDifferences as $dailyDifference) {
        $dailyDifferenceCategory = (string)($dailyDifference['category'] ?? 'kontrola');
        if (!isset($dailyDifferenceGroups[$dailyDifferenceCategory])) {
            $dailyDifferenceCategory = 'kontrola';
        }
        $dailyDifferenceGroups[$dailyDifferenceCategory]['items'][] = $dailyDifference;
    }
    $displayPeople = $googleComparison !== [] ? (array)($googleComparison['people'] ?? $people) : $people;
    $googleUnmatchedPeople = [];
    if ($googleComparison !== []) {
        foreach (['instor', 'kuryr'] as $peopleBucket) {
            foreach ((array)($displayPeople[$peopleBucket] ?? []) as $person) {
                if ((int)($person['google_unmatched'] ?? 0) !== 1) {
                    continue;
                }
                $personName = trim((string)($person['name'] ?? ''));
                if ($personName !== '') {
                    $googleUnmatchedPeople[mb_strtolower($personName, 'UTF-8')] = $personName;
                }
            }
        }
        natcasesort($googleUnmatchedPeople);
    }
    ?>
    <?php if ($googleComparison !== []): ?>
        <div class="kontrola_reportu_google_status">
            <strong>Porovnání reportů v IS a reportů zadaných na Google</strong>
            <span>Google reporty <?= h($cbKontrolaFormat('i', $googleCoverage['google_count'] ?? 0)) ?> z <?= h($cbKontrolaFormat('i', $googleCoverage['expected_count'] ?? 0)) ?> dnů</span>
        </div>
        <?php if ((string)($googleComparison['period_truncated_to'] ?? '') !== ''): ?>
            <div class="kontrola_reportu_missing">Google data má IS pouze do <?= h(cb_format('d', (string)$googleComparison['period_truncated_to'])) ?>, proto bylo období porovnání zkráceno.</div>
        <?php endif; ?>
        <?php if ((array)($googleCoverage['missing_google'] ?? []) !== []): ?>
            <div class="kontrola_reportu_missing">V Google datech chybí: <?= h($cbKontrolaDatesLabel((array)$googleCoverage['missing_google'])) ?></div>
        <?php endif; ?>
        <?php if ($googleUnmatchedPeople !== []): ?>
            <div class="kontrola_reportu_google_unknown">
                <strong>Google reporty obsahují osoby nenalezené v IS: <?= h($cbKontrolaFormat('i', count($googleUnmatchedPeople))) ?></strong>
                <span><?= h(implode(', ', array_values($googleUnmatchedPeople))) ?></span>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="zr_layout kontrola_reportu_layout">
        <div class="zr_main">
            <section class="zr_top">
                <section class="card_section bg_bila zaobleni_10 zr_section zr_intro_section">
                    <table class="zr_table">
                        <tbody>
                            <tr><th class="zr_intro_label txt_l">Pobočka</th><td><strong><?= h((string)$branch['name']) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Od</th><td><strong><?= h($cbKontrolaPeriodDateLabel((string)($period['display_from'] ?? $period['from']))) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Do</th><td><strong><?= h($cbKontrolaPeriodDateLabel((string)($period['display_to'] ?? $period['to']))) ?></strong></td></tr>
                            <tr><th class="zr_intro_label txt_l">Reportů</th><td><strong><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['reports_count'], $googleTotals['reports_count'] ?? null) : h($cbKontrolaFormat('i', $totals['reports_count'])) ?></strong></td></tr>
                        </tbody>
                    </table>
                </section>
                <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_finance">
                    <h2 class="card_section_title">Pokladna a výdaje</h2>
                    <div class="kontrola_reportu_values">
                        <span>Hotovost <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['hotovost'], $googleTotals['hotovost'] ?? null) : h($cbKontrolaFormat('p', $totals['hotovost'])) ?></strong></span>
                        <span>Terminál <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['terminal'], $googleTotals['terminal'] ?? null) : h($cbKontrolaFormat('p', $totals['terminal'])) ?></strong></span>
                        <span>Stravenky <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['stravenky'], $googleTotals['stravenky'] ?? null) : h($cbKontrolaFormat('p', $totals['stravenky'])) ?></strong></span>
                        <span>PHM firemní auta <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['vydaje_benzin'], $googleTotals['vydaje_benzin'] ?? null) : h($cbKontrolaFormat('p', $totals['vydaje_benzin'])) ?></strong></span>
                        <span>PHM výroba <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['vydaje_auta'], $googleTotals['vydaje_auta'] ?? null) : h($cbKontrolaFormat('p', $totals['vydaje_auta'])) ?></strong></span>
                        <span>PHM soukromé <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['vydaje_phm_soukrome'], $googleTotals['vydaje_phm_soukrome'] ?? null) : h($cbKontrolaFormat('p', $totals['vydaje_phm_soukrome'])) ?></strong></span>
                        <span>Suroviny <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['vydaje_suroviny'], $googleTotals['vydaje_suroviny'] ?? null) : h($cbKontrolaFormat('p', $totals['vydaje_suroviny'])) ?></strong></span>
                        <span>Ostatní <strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['vydaje_ostatni'], $googleTotals['vydaje_ostatni'] ?? null) : h($cbKontrolaFormat('p', $totals['vydaje_ostatni'])) ?></strong></span>
                    </div>
                </section>
                <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_revenue">
                    <h2 class="card_section_title">Tržba a online platby</h2>
                    <div class="kontrola_reportu_total"><span>Tržba</span><strong><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['trzba'], $googleTotals['trzba'] ?? null) : h($cbKontrolaFormat('p', $totals['trzba'])) ?></strong></div>
                    <table class="zr_table kontrola_reportu_metric_table"><tbody>
                        <tr><td>Wolt</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['wolt'], $googleTotals['wolt'] ?? null) : h($cbKontrolaFormat('p', $totals['wolt'])) ?></td></tr>
                        <tr><td>Bolt</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['bolt'], $googleTotals['bolt'] ?? null) : h($cbKontrolaFormat('p', $totals['bolt'])) ?></td></tr>
                        <tr><td>Foodora</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['damejidlo'], $googleTotals['damejidlo'] ?? null) : h($cbKontrolaFormat('p', $totals['damejidlo'])) ?></td></tr>
                        <tr><td>Web</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['web'], $googleTotals['web'] ?? null) : h($cbKontrolaFormat('p', $totals['web'])) ?></td></tr>
                        <tr><td>Wolt drive cash</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['wolt_cash'], $googleTotals['wolt_cash'] ?? null) : h($cbKontrolaFormat('p', $totals['wolt_cash'])) ?></td></tr>
                        <tr><td>DJ cash</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['dj_cash'], $googleTotals['dj_cash'] ?? null) : h($cbKontrolaFormat('p', $totals['dj_cash'])) ?></td></tr>
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
                    <?php if ($displayPeople['instor'] === []): ?>
                        <tr><td colspan="3" class="kontrola_reportu_empty">Bez uzavřených záznamů</td></tr>
                    <?php else: ?>
                        <?php foreach ((array)$displayPeople['instor'] as $person): ?>
                            <?php
                            $googleUnmatched = (int)($person['google_unmatched'] ?? 0) === 1;
                            $isDays = $googleComparison !== [] && empty($person['is_present']) ? null : $person['days'];
                            $isHours = $googleComparison !== [] && empty($person['is_present']) ? null : $person['hours'];
                            $googleDays = $googleComparison !== [] && empty($person['google_present']) ? null : ($person['google_days'] ?? null);
                            $googleHours = $googleComparison !== [] && empty($person['google_present']) ? null : ($person['google_hours'] ?? null);
                            ?>
                            <tr<?= $googleUnmatched ? ' class="kontrola_reportu_person_unknown"' : '' ?>><td><strong><?= h((string)$person['name']) ?></strong><?= $googleUnmatched ? '<span class="kontrola_reportu_person_unknown_label">Pouze Google – nenalezen v IS</span>' : '' ?></td><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('i', $isDays, $googleDays) : h($cbKontrolaFormat('i', $person['days'])) ?></td><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('h', $isHours, $googleHours) : h($cbKontrolaFormat('h', $person['hours'])) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2" class="txt_l">Celkem</th><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('h', $displayPeople['instor_hours'], $displayPeople['google_instor_hours'] ?? null) : h($cbKontrolaFormat('h', $people['instor_hours'])) ?></td></tr>
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
                    <?php if ($displayPeople['kuryr'] === []): ?>
                        <tr><td colspan="4" class="kontrola_reportu_empty">Bez uzavřených záznamů</td></tr>
                    <?php else: ?>
                        <?php foreach ((array)$displayPeople['kuryr'] as $person): ?>
                            <?php
                            $googleUnmatched = (int)($person['google_unmatched'] ?? 0) === 1;
                            $isDays = $googleComparison !== [] && empty($person['is_present']) ? null : $person['days'];
                            $isHours = $googleComparison !== [] && empty($person['is_present']) ? null : $person['hours'];
                            $googleDays = $googleComparison !== [] && empty($person['google_present']) ? null : ($person['google_days'] ?? null);
                            $googleHours = $googleComparison !== [] && empty($person['google_present']) ? null : ($person['google_hours'] ?? null);
                            ?>
                            <tr<?= $googleUnmatched ? ' class="kontrola_reportu_person_unknown"' : '' ?>><td><strong><?= h((string)$person['name']) ?></strong><?= $googleUnmatched ? '<span class="kontrola_reportu_person_unknown_label">Pouze Google – nenalezen v IS</span>' : '' ?></td><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('i', $isDays, $googleDays) : h($cbKontrolaFormat('i', $person['days'])) ?></td><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('h', $isHours, $googleHours) : h($cbKontrolaFormat('h', $person['hours'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $person['deliveries'])) ?></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr><th colspan="2" class="txt_l">Celkem</th><td class="txt_r"><?= $googleComparison !== [] ? $cbKontrolaPair('h', $displayPeople['kuryr_hours'], $displayPeople['google_kuryr_hours'] ?? null) : h($cbKontrolaFormat('h', $people['kuryr_hours'])) ?></td><td class="txt_r"><?= h($cbKontrolaFormat('i', $displayPeople['kuryr_deliveries'])) ?></td></tr>
                    </tfoot>
                </table>
            </section>
            </div>
        </div>

        <aside class="zr_side">
            <section class="card_section bg_bila zaobleni_10 zr_section">
                <h2 class="card_section_title">Kontrola</h2>
                <div class="kontrola_reportu_total"><span>COL</span><strong><?= $googleComparison !== [] ? $cbKontrolaPair('pr', $totals['col_pomer'], $googleTotals['col_pomer'] ?? null) : h($cbKontrolaFormat('pr', $totals['col_pomer'])) ?></strong></div>
                <table class="zr_table kontrola_reportu_metric_table"><tbody>
                    <tr><td>Rozdíl pokladna</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['rozdil'], $googleTotals['rozdil'] ?? null) : h($cbKontrolaFormat('p', $totals['rozdil'])) ?></td></tr>
                    <tr><td>Výdajové doklady</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['vydaje_doklady_ks'], $googleTotals['vydaje_doklady_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['vydaje_doklady_ks'])) ?></td></tr>
                    <tr><td>Zrušené obj. ks</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['zrusene_obj_ks'], $googleTotals['zrusene_obj_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['zrusene_obj_ks'])) ?></td></tr>
                    <tr><td>Zrušené obj. Kč</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('p', $totals['zrusene_obj_kc'], $googleTotals['zrusene_obj_kc'] ?? null) : h($cbKontrolaFormat('p', $totals['zrusene_obj_kc'])) ?></td></tr>
                    <tr><td>Průměrný make time</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('ms', $totals['make_time_prumer_sec'], $googleTotals['make_time_prumer_sec'] ?? null) : h($cbKontrolaFormat('ms', $totals['make_time_prumer_sec'])) ?></td></tr>
                    <tr><td>Nezrušené celkem</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['objednavky_nezrusene_ks'], $googleTotals['objednavky_nezrusene_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['objednavky_nezrusene_ks'])) ?></td></tr>
                    <tr><td>Naše rozvozy</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['nase_rozvozy_ks'], $googleTotals['nase_rozvozy_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['nase_rozvozy_ks'])) ?></td></tr>
                    <tr><td>Zpožděné naše +5 min</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['zpozdene_rozvozy_5_min'], $googleTotals['zpozdene_rozvozy_5_min'] ?? null) : h($cbKontrolaFormat('i', $totals['zpozdene_rozvozy_5_min'])) ?></td></tr>
                    <tr><td>Zpožděné naše +5 min</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('pr', $totals['nase_rozvozy_pozde_pomer'], $googleTotals['nase_rozvozy_pozde_pomer'] ?? null) : h($cbKontrolaFormat('pr', $totals['nase_rozvozy_pozde_pomer'])) ?></td></tr>
                    <tr><td>Doručené včas</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('pr', $totals['doruceno_vcas_pomer'], $googleTotals['doruceno_vcas_pomer'] ?? null) : h($cbKontrolaFormat('pr', $totals['doruceno_vcas_pomer'])) ?></td></tr>
                    <tr><td>Wolt Drive</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['woltdrive_ks'], $googleTotals['woltdrive_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['woltdrive_ks'])) ?></td></tr>
                    <tr><td>Pozdě WoltDrive 5+</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['woltdrive_pozde_5_min'], $googleTotals['woltdrive_pozde_5_min'] ?? null) : h($cbKontrolaFormat('i', $totals['woltdrive_pozde_5_min'])) ?></td></tr>
                    <tr><td>Pozdě přiřazené naší vinou</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['woltdrive_pozde_nase_vina'], $googleTotals['woltdrive_pozde_nase_vina'] ?? null) : h($cbKontrolaFormat('i', $totals['woltdrive_pozde_nase_vina'])) ?></td></tr>
                    <tr><td>Zpožděné WoltDrivem</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('i', $totals['woltdrive_zpozdene_ks'], $googleTotals['woltdrive_zpozdene_ks'] ?? null) : h($cbKontrolaFormat('i', $totals['woltdrive_zpozdene_ks'])) ?></td></tr>
                    <tr><td>Zpožděné WoltDrivem</td><td><?= $googleComparison !== [] ? $cbKontrolaPair('pr', $totals['woltdrive_zpozdene_pomer'], $googleTotals['woltdrive_zpozdene_pomer'] ?? null) : h($cbKontrolaFormat('pr', $totals['woltdrive_zpozdene_pomer'])) ?></td></tr>
                </tbody></table>
            </section>
        </aside>
    </div>
    <?php if ($googleComparison !== []): ?>
        <section class="card_section bg_bila zaobleni_10 zr_section kontrola_reportu_daily_differences">
            <h2 class="card_section_title">Rozdíly po dnech</h2>
            <?php if ($dailyDifferences === []): ?>
                <p class="kontrola_reportu_comparison_ok">Za zvolené období nejsou mezi IS a Google reporty žádné rozdíly.</p>
            <?php else: ?>
                <div class="kontrola_reportu_daily_groups">
                <?php foreach ($dailyDifferenceGroups as $dailyDifferenceGroup): ?>
                    <?php if ((array)$dailyDifferenceGroup['items'] === []) { continue; } ?>
                    <section class="kontrola_reportu_daily_group">
                        <h3><?= h((string)$dailyDifferenceGroup['label']) ?></h3>
                        <div class="kontrola_reportu_daily_table_wrap">
                            <table class="zr_table kontrola_reportu_daily_table">
                                <thead><tr><th>Datum</th><th>Položka</th><th>IS</th><th>Google</th><th>Rozdíl</th></tr></thead>
                                <tbody>
                                <?php foreach ((array)$dailyDifferenceGroup['items'] as $difference): ?>
                                    <?php
                                    $differenceFormat = (string)($difference['format'] ?? 'number');
                                    $isDifferenceValue = $difference['is'] ?? null;
                                    $googleDifferenceValue = $difference['google'] ?? null;
                                    ?>
                                    <tr<?= (int)($difference['google_unmatched'] ?? 0) === 1 ? ' class="kontrola_reportu_person_unknown"' : '' ?>>
                                        <td><?= h($cbKontrolaPeriodDateLabel((string)($difference['date'] ?? ''))) ?></td>
                                        <td><?= h((string)($difference['item'] ?? '')) ?></td>
                                        <td><?= h($cbKontrolaDifferenceFormat($isDifferenceValue, $differenceFormat)) ?></td>
                                        <td class="kontrola_reportu_google_value--different"><?= h($cbKontrolaDifferenceFormat($googleDifferenceValue, $differenceFormat)) ?></td>
                                        <td><?= h($cbKontrolaDifferenceDelta($isDifferenceValue, $googleDifferenceValue, $differenceFormat)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php if ($processedCourierNames !== [] || $unmatchedCourierNames !== []): ?>
        <section class="zr_restia_name_mismatches kontrola_reportu_name_mismatches" aria-label="Nesrovnalosti ve jménech kurýrů">
            <div class="kontrola_reportu_name_layout">
                <div class="kontrola_reportu_name_list">
                    <strong>Nesrovnalosti ve jménech kurýrů – zpracováno: <?= h($cbKontrolaFormat('i', count($processedCourierNames))) ?>×</strong>
                    <?php if ($processedCourierNames !== []): ?>
                        <details class="kontrola_reportu_name_processed">
                            <summary><span class="kontrola_reportu_name_show">Ukázat jména</span><span class="kontrola_reportu_name_hide">Skrýt jména</span></summary>
                            <table class="zr_restia_name_table">
                                <tbody>
                                    <?php foreach ($processedCourierNames as $nameMismatch): ?>
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
                        </details>
                    <?php endif; ?>
                    <?php if ($unmatchedCourierNames !== []): ?>
                        <div class="kontrola_reportu_name_unmatched">
                            <strong>Nespárovaná jména kurýrů: <?= h($cbKontrolaFormat('i', count($unmatchedCourierNames))) ?></strong>
                            <table class="zr_restia_name_table">
                                <tbody>
                                    <?php foreach ($unmatchedCourierNames as $name): ?>
                                        <tr>
                                            <td class="kontrola_reportu_name_date"><?= h($cbKontrolaPeriodDateLabel((string)($name['date'] ?? ''))) ?></td>
                                            <td class="zr_restia_name_label">Restia:</td>
                                            <td><?= h((string)($name['restia'] ?? '')) ?></td>
                                            <td class="zr_restia_name_label"><?= h(($name['reason'] ?? '') === 'nejednoznačný' ? 'nejednoznačný' : 'nenalezen v reportu daného dne') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
</section>
