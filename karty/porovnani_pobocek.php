<?php
// pages/porovnani_pobocek.php
// Novy soubor vytvoreny od nuly pro test grafu nad realnymi daty z DB.

declare(strict_types=1);

if (!function_exists('cb_hp')) {
    function cb_hp(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_safe_float')) {
    function cb_safe_float(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        return (float) $value;
    }
}

if (!function_exists('cb_safe_int')) {
    function cb_safe_int(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (int) $value;
    }
}

if (!function_exists('cb_json_flags')) {
    function cb_json_flags(): int
    {
        return JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }
}

if (!function_exists('cb_interval_expr_minutes')) {
    function cb_interval_expr_minutes(string $fromCol, string $toCol): string
    {
        return "CASE WHEN {$fromCol} IS NOT NULL AND {$toCol} IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, {$fromCol}, {$toCol}) END";
    }
}

if (!function_exists('cb_detect_mode')) {
    function cb_detect_mode(): string
    {
        $candidates = [];
        if (isset($_GET['rezim'])) {
            $candidates[] = (string) $_GET['rezim'];
        }
        if (isset($_GET['mode'])) {
            $candidates[] = (string) $_GET['mode'];
        }
        if (isset($_GET['view'])) {
            $candidates[] = (string) $_GET['view'];
        }

        foreach ($candidates as $candidate) {
            $value = mb_strtolower(trim($candidate));
            if (in_array($value, ['max', 'full', 'velky'], true)) {
                return 'max';
            }
            if (in_array($value, ['mini', 'small', 'maly'], true)) {
                return 'mini';
            }
        }

        return 'max';
    }
}

if (!function_exists('cb_find_global_dates')) {
    function cb_find_global_dates(): array
    {
        $map = [
            'od' => [
                '_GET' => ['od', 'datum_od', 'date_from', 'from', 'global_od'],
                '_POST' => ['od', 'datum_od', 'date_from', 'from', 'global_od'],
            ],
            'do' => [
                '_GET' => ['do', 'datum_do', 'date_to', 'to', 'global_do'],
                '_POST' => ['do', 'datum_do', 'date_to', 'to', 'global_do'],
            ],
        ];

        $result = ['od' => null, 'do' => null];

        foreach ($map as $target => $sources) {
            foreach ($sources as $bag => $keys) {
                $data = $bag === '_GET' ? $_GET : $_POST;
                foreach ($keys as $key) {
                    if (!isset($data[$key])) {
                        continue;
                    }
                    $raw = trim((string) $data[$key]);
                    if ($raw === '') {
                        continue;
                    }
                    $date = date_create($raw);
                    if ($date === false) {
                        continue;
                    }
                    $result[$target] = $date->format('Y-m-d');
                    break 2;
                }
            }
        }

        return $result;
    }
}

if (!function_exists('cb_default_dates')) {
    function cb_default_dates(): array
    {
        $to = new DateTimeImmutable('today');
        $from = $to->modify('-29 days');
        return [
            'od' => $from->format('Y-m-d'),
            'do' => $to->format('Y-m-d'),
        ];
    }
}

if (!function_exists('cb_get_db')) {
    function cb_get_db(): ?mysqli
    {
        global $conn;
        global $mysqli;
        global $db;

        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            return $mysqli;
        }
        if (isset($db) && $db instanceof mysqli) {
            return $db;
        }

        return null;
    }
}

if (!function_exists('cb_fetch_selected_pobocky')) {
    function cb_fetch_selected_pobocky(?mysqli $db): array
    {
        $selectedIds = [];
        $sources = [$_GET, $_POST];
        $keys = ['id_pob', 'id_pobocka', 'pobocky', 'pobocka', 'ids_pob'];

        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (!isset($source[$key])) {
                    continue;
                }

                $raw = $source[$key];
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        $id = (int) $item;
                        if ($id > 0) {
                            $selectedIds[$id] = $id;
                        }
                    }
                    continue;
                }

                $parts = preg_split('/[^0-9]+/', (string) $raw);
                if ($parts === false) {
                    continue;
                }
                foreach ($parts as $part) {
                    $id = (int) $part;
                    if ($id > 0) {
                        $selectedIds[$id] = $id;
                    }
                }
            }
        }

        if ($selectedIds !== []) {
            return array_values($selectedIds);
        }

        if ($db instanceof mysqli === false) {
            return [];
        }

        $sql = "SELECT id_pob FROM pobocka WHERE aktivni = 1 ORDER BY id_pob ASC";
        $res = $db->query($sql);
        if (!$res) {
            return [];
        }

        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id_pob'];
        }
        $res->free();

        return $ids;
    }
}

if (!function_exists('cb_build_in_clause')) {
    function cb_build_in_clause(array $ids): string
    {
        if ($ids === []) {
            return '0';
        }
        $safe = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $safe[] = (string) $intId;
            }
        }
        if ($safe === []) {
            return '0';
        }
        return implode(',', $safe);
    }
}

if (!function_exists('cb_query_rows')) {
    function cb_query_rows(mysqli $db, string $sql, string $types, array $params): array
    {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        if ($types !== '' && $params !== []) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }

        $result = $stmt->get_result();
        if (!$result) {
            $stmt->close();
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('cb_load_colors')) {
    function cb_load_colors(): array
    {
        global $CB_BARVY_POBOCEK;

        if (isset($CB_BARVY_POBOCEK) && is_array($CB_BARVY_POBOCEK) && $CB_BARVY_POBOCEK !== []) {
            return array_values($CB_BARVY_POBOCEK);
        }

        return ['#86efac', '#60a5fa', '#f9a8d4', '#fde68a', '#c4b5fd', '#fca5a5'];
    }
}

if (!function_exists('cb_chart_box')) {
    function cb_chart_box(string $title, string $id, string $note = ''): string
    {
        $html = '<section class="cb-chart-card">';
        $html .= '<header class="cb-chart-head">';
        $html .= '<h3>' . cb_hp($title) . '</h3>';
        if ($note !== '') {
            $html .= '<p>' . cb_hp($note) . '</p>';
        }
        $html .= '</header>';
        $html .= '<div class="cb-chart-wrap"><canvas id="' . cb_hp($id) . '"></canvas></div>';
        $html .= '</section>';
        return $html;
    }
}

$cbDb = cb_get_db();
$cbMode = cb_detect_mode();
$cbDates = cb_find_global_dates();
$cbDefaults = cb_default_dates();
$cbDateFrom = $cbDates['od'] ?? $cbDefaults['od'];
$cbDateTo = $cbDates['do'] ?? $cbDefaults['do'];
$cbSelectedPobIds = cb_fetch_selected_pobocky($cbDb);
$cbPobIn = cb_build_in_clause($cbSelectedPobIds);
$cbColors = cb_load_colors();

$cbError = '';
$cbMiniChart = [
    'labels' => [],
    'data' => [],
    'colors' => [],
];
$cbMaxCharts = [];

if (!$cbDb instanceof mysqli) {
    $cbError = 'Nenalezena aktivni DB pripojka.';
} elseif ($cbPobIn === '0') {
    $cbError = 'Neni vybrana zadna pobocka.';
} else {
    $dtFrom = $cbDateFrom . ' 00:00:00';
    $dtTo = $cbDateTo . ' 23:59:59';

    $sqlPob = "
        SELECT
            p.id_pob,
            p.nazev,
            COUNT(o.id_obj) AS pocet_obj,
            COALESCE(SUM(c.cena_celk), 0) AS trzba,
            COALESCE(AVG(c.cena_celk), 0) AS prumer_obj,
            COALESCE(AVG(" . cb_interval_expr_minutes('oc.cas_vytvor', 'oc.cas_doruc') . "), 0) AS min_doruceni,
            COALESCE(AVG(" . cb_interval_expr_minutes('oc.cas_vytvor', 'oc.cas_dokonc') . "), 0) AS min_priprava,
            SUM(CASE WHEN o.je_vyzvednuti = 1 THEN 1 ELSE 0 END) AS vyzvednuti,
            SUM(CASE WHEN o.je_vlastni_rozvoz = 1 THEN 1 ELSE 0 END) AS vlastni_rozvoz,
            SUM(CASE WHEN o.je_v_restauraci = 1 THEN 1 ELSE 0 END) AS restaurace
        FROM pobocka p
        LEFT JOIN objednavky_restia o
            ON o.id_pob = p.id_pob
            AND o.restia_created_at BETWEEN ? AND ?
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
        LEFT JOIN obj_casy oc
            ON oc.id_obj = o.id_obj
        WHERE p.id_pob IN ({$cbPobIn})
        GROUP BY p.id_pob, p.nazev
        ORDER BY pocet_obj DESC, p.nazev ASC
    ";

    $rowsPob = cb_query_rows($cbDb, $sqlPob, 'ss', [$dtFrom, $dtTo]);

    $sqlDaily = "
        SELECT
            oc.report AS den,
            COUNT(o.id_obj) AS pocet_obj,
            COALESCE(SUM(c.cena_celk), 0) AS trzba
        FROM objednavky_restia o
        INNER JOIN obj_casy oc ON oc.id_obj = o.id_obj
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        WHERE o.id_pob IN ({$cbPobIn})
          AND oc.report BETWEEN ? AND ?
        GROUP BY oc.report
        ORDER BY oc.report ASC
    ";

    $rowsDaily = cb_query_rows($cbDb, $sqlDaily, 'ss', [$cbDateFrom, $cbDateTo]);

    $sqlWeekday = "
        SELECT
            WEEKDAY(oc.report) AS den_idx,
            COUNT(o.id_obj) AS pocet_obj,
            COALESCE(SUM(c.cena_celk), 0) AS trzba
        FROM objednavky_restia o
        INNER JOIN obj_casy oc ON oc.id_obj = o.id_obj
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        WHERE o.id_pob IN ({$cbPobIn})
          AND oc.report BETWEEN ? AND ?
        GROUP BY WEEKDAY(oc.report)
        ORDER BY WEEKDAY(oc.report) ASC
    ";

    $rowsWeekday = cb_query_rows($cbDb, $sqlWeekday, 'ss', [$cbDateFrom, $cbDateTo]);

    $labelsPob = [];
    $dataPocet = [];
    $dataTrzba = [];
    $dataPrumer = [];
    $dataDoruceni = [];
    $dataPriprava = [];
    $dataPodilRozvoz = [];
    $colorsPob = [];

    foreach ($rowsPob as $index => $row) {
        $labelsPob[] = (string) $row['nazev'];
        $dataPocet[] = cb_safe_int($row['pocet_obj']);
        $dataTrzba[] = round(cb_safe_float($row['trzba']), 2);
        $dataPrumer[] = round(cb_safe_float($row['prumer_obj']), 2);
        $dataDoruceni[] = round(cb_safe_float($row['min_doruceni']), 1);
        $dataPriprava[] = round(cb_safe_float($row['min_priprava']), 1);

        $allOrders = cb_safe_int($row['pocet_obj']);
        $rozvoz = cb_safe_int($row['vlastni_rozvoz']);
        $dataPodilRozvoz[] = $allOrders > 0 ? round(($rozvoz / $allOrders) * 100, 1) : 0.0;

        $colorsPob[] = $cbColors[$index % count($cbColors)];
    }

    $dailyLabels = [];
    $dailyOrders = [];
    $dailyRevenue = [];
    foreach ($rowsDaily as $row) {
        $dailyLabels[] = (string) $row['den'];
        $dailyOrders[] = cb_safe_int($row['pocet_obj']);
        $dailyRevenue[] = round(cb_safe_float($row['trzba']), 2);
    }

    $weekdayMapOrders = [0, 0, 0, 0, 0, 0, 0];
    $weekdayMapRevenue = [0, 0, 0, 0, 0, 0, 0];
    foreach ($rowsWeekday as $row) {
        $idx = cb_safe_int($row['den_idx']);
        if ($idx < 0 || $idx > 6) {
            continue;
        }
        $weekdayMapOrders[$idx] = cb_safe_int($row['pocet_obj']);
        $weekdayMapRevenue[$idx] = round(cb_safe_float($row['trzba']), 2);
    }

    $weekdayLabels = ['Po', 'Ut', 'St', 'Ct', 'Pa', 'So', 'Ne'];

    $cbMiniChart = [
        'labels' => $labelsPob,
        'data' => $dataPocet,
        'colors' => $colorsPob,
    ];

    $cbMaxCharts = [
        [
            'id' => 'cb_chart_orders_branch',
            'type' => 'bar',
            'title' => 'Počet objednávek podle poboček',
            'note' => 'Základní porovnání zvolených poboček.',
            'labels' => $labelsPob,
            'datasets' => [[
                'label' => 'Objednávky',
                'data' => $dataPocet,
                'backgroundColor' => $colorsPob,
                'borderWidth' => 0,
            ]],
        ],
        [
            'id' => 'cb_chart_revenue_branch',
            'type' => 'line',
            'title' => 'Tržba podle poboček',
            'note' => 'Čára (spojnice) jen pro test jiného typu grafu.',
            'labels' => $labelsPob,
            'datasets' => [[
                'label' => 'Tržba',
                'data' => $dataTrzba,
                'borderColor' => '#60a5fa',
                'backgroundColor' => 'rgba(96,165,250,0.20)',
                'fill' => true,
                'tension' => 0.25,
            ]],
        ],
        [
            'id' => 'cb_chart_avg_branch',
            'type' => 'radar',
            'title' => 'Průměrná hodnota objednávky',
            'note' => 'Radar pro rychlý vizuální test.',
            'labels' => $labelsPob,
            'datasets' => [[
                'label' => 'Průměr objednávky',
                'data' => $dataPrumer,
                'backgroundColor' => 'rgba(249,168,212,0.22)',
                'borderColor' => '#f472b6',
                'pointBackgroundColor' => '#f472b6',
            ]],
        ],
        [
            'id' => 'cb_chart_daily_orders',
            'type' => 'bar',
            'title' => 'Denní vývoj objednávek',
            'note' => 'Součet všech vybraných poboček po dnech.',
            'labels' => $dailyLabels,
            'datasets' => [[
                'label' => 'Objednávky / den',
                'data' => $dailyOrders,
                'backgroundColor' => '#86efac',
                'borderWidth' => 0,
            ]],
        ],
        [
            'id' => 'cb_chart_weekday_revenue',
            'type' => 'doughnut',
            'title' => 'Tržba podle dnů v týdnu',
            'note' => 'Kruh pro rozložení výkonu během týdne.',
            'labels' => $weekdayLabels,
            'datasets' => [[
                'label' => 'Tržba',
                'data' => $weekdayMapRevenue,
                'backgroundColor' => array_slice(array_merge($cbColors, $cbColors), 0, 7),
                'borderWidth' => 1,
            ]],
        ],
        [
            'id' => 'cb_chart_speed_mix',
            'type' => 'scatter',
            'title' => 'Rychlost vs. objem poboček',
            'note' => 'X = počet objednávek, Y = průměr doručení v minutách.',
            'labels' => $labelsPob,
            'datasets' => [[
                'label' => 'Pobočky',
                'data' => array_map(
                    static function ($label, $x, $y, $color): array {
                        return ['x' => $x, 'y' => $y, 'label' => $label, 'backgroundColor' => $color];
                    },
                    $labelsPob,
                    $dataPocet,
                    $dataDoruceni,
                    $colorsPob
                ),
                'backgroundColor' => $colorsPob,
                'pointRadius' => 6,
                'pointHoverRadius' => 8,
            ]],
        ],
    ];
}
?>
<style>
.cb-porovnani-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cb-porovnani-info {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    padding: 12px 14px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.04);
}

.cb-porovnani-info strong {
    font-weight: 700;
}

.cb-porovnani-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.cb-porovnani-grid.cb-mini {
    grid-template-columns: minmax(0, 1fr);
}

.cb-chart-card {
    min-height: 320px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    border-radius: 14px;
    padding: 14px;
    background: rgba(15, 23, 42, 0.16);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.cb-chart-head h3 {
    margin: 0 0 6px 0;
    font-size: 16px;
}

.cb-chart-head p {
    margin: 0;
    font-size: 13px;
    opacity: 0.8;
}

.cb-chart-wrap {
    position: relative;
    width: 100%;
    height: 240px;
    margin-top: 14px;
}

.cb-chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
}

.cb-porovnani-empty {
    padding: 18px;
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.10);
    border: 1px solid rgba(239, 68, 68, 0.25);
}

@media (max-width: 1200px) {
    .cb-porovnani-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .cb-porovnani-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

<div class="cb-porovnani-wrap">
    <div class="cb-porovnani-info">
        <strong>Porovnání poboček</strong>
        <span>Režim: <?= cb_hp($cbMode) ?></span>
        <span>Období: <?= cb_hp($cbDateFrom) ?> až <?= cb_hp($cbDateTo) ?></span>
        <span>Pobočky: <?= cb_hp((string) count($cbSelectedPobIds)) ?></span>
    </div>

    <?php if ($cbError !== ''): ?>
        <div class="cb-porovnani-empty"><?= cb_hp($cbError) ?></div>
    <?php elseif ($cbMode === 'mini'): ?>
        <div class="cb-porovnani-grid cb-mini">
            <?= cb_chart_box('Počet objednávek podle poboček', 'cb_chart_mini_orders', 'Mini režim zobrazuje jen jeden graf.') ?>
        </div>
    <?php else: ?>
        <div class="cb-porovnani-grid">
            <?php foreach ($cbMaxCharts as $chart): ?>
                <?= cb_chart_box($chart['title'], $chart['id'], $chart['note']) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const hasError = <?= json_encode($cbError !== '', cb_json_flags()) ?>;
    if (hasError) {
        return;
    }

    const mode = <?= json_encode($cbMode, cb_json_flags()) ?>;
    const miniChart = <?= json_encode($cbMiniChart, cb_json_flags()) ?>;
    const maxCharts = <?= json_encode($cbMaxCharts, cb_json_flags()) ?>;

    const tooltipLabel = function (context) {
        const label = context.dataset.label ? context.dataset.label + ': ' : '';
        const raw = context.raw;

        if (raw && typeof raw === 'object' && raw.x !== undefined && raw.y !== undefined) {
            const pointLabel = raw.label ? raw.label + ' - ' : '';
            return pointLabel + 'objednávky ' + raw.x + ', doručení ' + raw.y + ' min';
        }

        if (typeof raw === 'number') {
            return label + raw.toLocaleString('cs-CZ');
        }

        return label + raw;
    };

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: tooltipLabel
                }
            }
        }
    };

    if (mode === 'mini') {
        const canvas = document.getElementById('cb_chart_mini_orders');
        if (!canvas) {
            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: miniChart.labels,
                datasets: [{
                    label: 'Objednávky',
                    data: miniChart.data,
                    backgroundColor: miniChart.colors,
                    borderWidth: 0
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    legend: {
                        display: false
                    }
                }
            }
        });

        return;
    }

    maxCharts.forEach(function (chartDef) {
        const canvas = document.getElementById(chartDef.id);
        if (!canvas) {
            return;
        }

        const config = {
            type: chartDef.type,
            data: {
                labels: chartDef.labels,
                datasets: chartDef.datasets
            },
            options: {
                ...commonOptions
            }
        };

        if (chartDef.type === 'scatter') {
            config.options.scales = {
                x: {
                    title: {
                        display: true,
                        text: 'Počet objednávek'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Průměr doručení (min)'
                    }
                }
            };
        }

        new Chart(canvas, config);
    });
})();
</script>
