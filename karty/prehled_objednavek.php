<?php
// K14
// karty/prehled_objednavek.php * Verze: V6 * Aktualizace: 14.04.2026
declare(strict_types=1);

/*
 * Karta "Přehled objednávek":
 * - mini režim: jen souhrn počtu objednávek
 * - max režim: tabulka objednávek
 * - respektuje globální filtr období a poboček z hlavičky
 * - lokální filtr nahoře
 * - stránkování dole
 */

// === KONFIG TABULKY: OBJEDNAVKY ===
$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
    'default_sort' => 'vytvoreno',
    'default_dir' => 'DESC',
];

$formAction = cb_url('/');
$pocetObj = 0;
$objRows = [];
$objTotal = 0;
$objPages = 1;
$objPage = 1;
$objPer = (int)$tabKonfig['default_per'];
$objFilters = [];
$objError = '';

$periodOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

if ($periodOd === '' || $periodDo === '') {
    $today = (new DateTimeImmutable('today'))->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$periodOdDt = new DateTimeImmutable($periodOd);
$periodDoDt = new DateTimeImmutable($periodDo);
$periodOdSql = $periodOdDt->format('Y-m-d H:i:s');
$periodDoSql = $periodDoDt->format('Y-m-d H:i:s');
$periodReportOd = $periodOdDt->format('Y-m-d');
$periodReportDo = $periodDoDt->modify('-1 second')->format('Y-m-d');

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v > 0));

if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

if (!function_exists('obj_format_cz_date')) {
    function obj_format_cz_date(string $ymd): string
    {
        try {
            $dt = new DateTimeImmutable($ymd);
        } catch (Throwable $e) {
            return $ymd;
        }
        return $dt->format('j.n.Y G:i');
    }
}

if (!function_exists('obj_sklonuj_objednavka')) {
    function obj_sklonuj_objednavka(int $pocet): string
    {
        $abs = abs($pocet);
        if ($abs === 1) {
            return 'objednávka';
        }
        if (($abs % 100) >= 11 && ($abs % 100) <= 14) {
            return 'objednávek';
        }
        $last = $abs % 10;
        if ($last >= 2 && $last <= 4) {
            return 'objednávky';
        }
        return 'objednávek';
    }
}

$periodOdText = obj_format_cz_date($periodOd);
$periodDoText = obj_format_cz_date($periodDo);
$selectedPobNames = [];
$pobLabel = 'Pobočky';
$pobText = '-';

$objCols = [
    'cislo_obj' => ['label' => 'č.obj.', 'db' => 'cislo_obj', 'filter' => true],
    'stav' => ['label' => 'stav', 'db' => 'stav', 'filter' => true],
    'cena' => ['label' => 'cena', 'db' => 'cena'],
    'typ' => ['label' => 'typ', 'db' => 'typ', 'filter' => true],
    'platba' => ['label' => 'platba', 'db' => 'platba', 'filter' => true],
    'zakaznik' => ['label' => 'zákazník', 'db' => 'zakaznik', 'filter' => true],
    'vytvoreno' => ['label' => 'vytvořeno', 'db' => 'vytvoreno'],
];

$objFilterStyle = [
    'cislo_obj' => 'width:9ch;',
    'stav' => 'width:10ch;',
    'typ' => 'width:12ch;',
    'platba' => 'width:10ch;',
    'zakaznik' => 'width:16ch;',
];

$objSortMap = [
    'cislo_obj' => 'COALESCE(o.restia_order_number, "")',
    'stav' => 'COALESCE(s.nazev, "")',
    'cena' => 'COALESCE(c.cena_celk, 0)',
    'typ' => 'COALESCE(d.nazev, "")',
    'platba' => 'COALESCE(p.nazev, "")',
    'zakaznik' => 'TRIM(CONCAT(COALESCE(z.jmeno, ""), " ", COALESCE(z.prijmeni, "")))',
    'vytvoreno' => 'COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at)',
];

$objSortRaw = trim((string)($_GET['obj_sort'] ?? (string)$tabKonfig['default_sort']));
$objDirRaw = strtoupper(trim((string)($_GET['obj_dir'] ?? (string)$tabKonfig['default_dir'])));
$objSort = (string)$tabKonfig['default_sort'];
$objDir = (string)$tabKonfig['default_dir'];

if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($objSortRaw, $objSortMap)) {
    $objSort = $objSortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($objDirRaw, ['ASC', 'DESC'], true)) {
    $objDir = $objDirRaw;
}

$objPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($objPerOptions === []) {
    $objPerOptions = [20, 50, 100];
}

$objPerRaw = (int)($_GET['obj_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($objPerRaw, $objPerOptions, true)) {
    $objPer = $objPerRaw;
}

$objPageRaw = (int)($_GET['obj_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $objPageRaw > 1) {
    $objPage = $objPageRaw;
}

$objFiltersRaw = $_GET['obj_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($objFiltersRaw)) {
    foreach ($objCols as $key => $cfg) {
        if (empty($cfg['filter'])) {
            continue;
        }
        $objFilters[$key] = trim((string)($objFiltersRaw[$key] ?? ''));
    }
}

if (($cbDashboardRenderMode ?? '') === 'mini') {
    $cbDbMini = db();
    $cbDbMini->set_charset('utf8mb4');

    $pocetObjMini = 0;

    $pobWhereMini = '';
    if ($selectedPob !== []) {
        $inIdsMini = [];
        foreach ($selectedPob as $idPobMini) {
            $inIdsMini[] = (string)(int)$idPobMini;
        }
        $pobWhereMini = ' AND o.id_pob IN (' . implode(',', $inIdsMini) . ')';
    }

    $qMini = $cbDbMini->query('
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT o.id_obj
            FROM obj_casy ca
            INNER JOIN objednavky_restia o ON o.id_obj = ca.id_obj
            WHERE ca.cas_vytvor IS NOT NULL
              AND ca.cas_vytvor >= ' . "'" . $cbDbMini->real_escape_string($periodOdSql) . "'" . '
              AND ca.cas_vytvor < ' . "'" . $cbDbMini->real_escape_string($periodDoSql) . "'" . '
              ' . $pobWhereMini . '

            UNION ALL

            SELECT o.id_obj
            FROM objednavky_restia o
            LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
            WHERE ca.cas_vytvor IS NULL
              AND o.restia_created_at IS NOT NULL
              AND o.restia_created_at >= ' . "'" . $cbDbMini->real_escape_string($periodOdSql) . "'" . '
              AND o.restia_created_at < ' . "'" . $cbDbMini->real_escape_string($periodDoSql) . "'" . '
              ' . $pobWhereMini . '

            UNION ALL

            SELECT o.id_obj
            FROM objednavky_restia o
            LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
            WHERE ca.cas_vytvor IS NULL
              AND o.restia_created_at IS NULL
              AND o.restia_imported_at >= ' . "'" . $cbDbMini->real_escape_string($periodOdSql) . "'" . '
              AND o.restia_imported_at < ' . "'" . $cbDbMini->real_escape_string($periodDoSql) . "'" . '
              ' . $pobWhereMini . '

            UNION ALL

            SELECT o.id_obj
            FROM obj_casy ca
            INNER JOIN objednavky_restia o ON o.id_obj = ca.id_obj
            WHERE ca.cas_vytvor IS NULL
              AND o.restia_created_at IS NULL
              AND o.restia_imported_at IS NULL
              AND ca.report IS NOT NULL
              AND ca.report >= ' . "'" . $cbDbMini->real_escape_string($periodReportOd) . "'" . '
              AND ca.report <= ' . "'" . $cbDbMini->real_escape_string($periodReportDo) . "'" . '
              ' . $pobWhereMini . '
        ) AS mini_objednavky
    ');
    if ($qMini instanceof mysqli_result) {
        if ($rMini = $qMini->fetch_assoc()) {
            $pocetObjMini = (int)($rMini['cnt'] ?? 0);
        }
        $qMini->free();
    }

    if ($selectedPob !== []) {
        $sqlPobNames = 'SELECT id_pob, nazev FROM pobocka WHERE id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
        $resPobNames = $cbDbMini->query($sqlPobNames);
        if ($resPobNames instanceof mysqli_result) {
            $pobMap = [];
            while ($rowPob = $resPobNames->fetch_assoc()) {
                $idPobMap = (int)($rowPob['id_pob'] ?? 0);
                $nazevPobMap = trim((string)($rowPob['nazev'] ?? ''));
                if ($idPobMap >= 0 && $nazevPobMap !== '') {
                    $pobMap[$idPobMap] = $nazevPobMap;
                }
            }
            $resPobNames->free();

            foreach ($selectedPob as $idPobSel) {
                if (isset($pobMap[(int)$idPobSel])) {
                    $selectedPobNames[] = $pobMap[(int)$idPobSel];
                } else {
                    $selectedPobNames[] = (string)$idPobSel;
                }
            }
        }
    }

    if ($selectedPobNames !== []) {
        $pobLabel = count($selectedPobNames) === 1 ? 'Pobočka' : 'Pobočky';
        $pobText = implode(', ', $selectedPobNames);
    } elseif ($selectedPob !== []) {
        $pobLabel = count($selectedPob) === 1 ? 'Pobočka' : 'Pobočky';
        $pobText = implode(', ', array_map(static fn(int $v): string => (string)$v, $selectedPob));
    }

    ob_start();
    ?>
    <div class="displ_flex jc_stred">
      <table class="table ram_normal bg_bila radek_1_35 card_table_min sirka100">
        <tbody>
          <tr>
            <td>Období</td>
            <td class="txt_r"><strong><?= h($periodOdText) ?> - <?= h($periodDoText) ?></strong></td>
          </tr>
          <tr>
            <td><?= h($pobLabel) ?></td>
            <td class="txt_r"><strong><?= h($pobText) ?></strong></td>
          </tr>
          <tr>
            <td>Celkem</td>
            <td class="txt_r"><strong><?= h(number_format($pocetObjMini, 0, ',', ' ')) ?></strong> <?= h(obj_sklonuj_objednavka($pocetObjMini)) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
    $card_min_html = (string)ob_get_clean();

    return;
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $where = [];
    $safePeriodOdSql = $conn->real_escape_string($periodOdSql);
    $safePeriodDoSql = $conn->real_escape_string($periodDoSql);
    $safePeriodReportOd = $conn->real_escape_string($periodReportOd);
    $safePeriodReportDo = $conn->real_escape_string($periodReportDo);
    $periodIdsSql = '
        SELECT ca.id_obj
        FROM obj_casy ca
        WHERE ca.cas_vytvor IS NOT NULL
          AND ca.cas_vytvor >= "' . $safePeriodOdSql . '"
          AND ca.cas_vytvor < "' . $safePeriodDoSql . '"

        UNION ALL

        SELECT o2.id_obj
        FROM objednavky_restia o2
        LEFT JOIN obj_casy ca2 ON ca2.id_obj = o2.id_obj
        WHERE ca2.cas_vytvor IS NULL
          AND o2.restia_created_at IS NOT NULL
          AND o2.restia_created_at >= "' . $safePeriodOdSql . '"
          AND o2.restia_created_at < "' . $safePeriodDoSql . '"

        UNION ALL

        SELECT o3.id_obj
        FROM objednavky_restia o3
        LEFT JOIN obj_casy ca3 ON ca3.id_obj = o3.id_obj
        WHERE ca3.cas_vytvor IS NULL
          AND o3.restia_created_at IS NULL
          AND o3.restia_imported_at >= "' . $safePeriodOdSql . '"
          AND o3.restia_imported_at < "' . $safePeriodDoSql . '"

        UNION ALL

        SELECT ca4.id_obj
        FROM obj_casy ca4
        INNER JOIN objednavky_restia o4 ON o4.id_obj = ca4.id_obj
        WHERE ca4.cas_vytvor IS NULL
          AND o4.restia_created_at IS NULL
          AND o4.restia_imported_at IS NULL
          AND ca4.report IS NOT NULL
          AND ca4.report >= "' . $safePeriodReportOd . '"
          AND ca4.report <= "' . $safePeriodReportDo . '"
    ';
    $periodJoinSql = '
        INNER JOIN (
            SELECT DISTINCT period_raw.id_obj
            FROM (' . $periodIdsSql . ') period_raw
        ) period_ids ON period_ids.id_obj = o.id_obj
    ';

    if ($selectedPob !== []) {
        $inIds = [];
        foreach ($selectedPob as $idPob) {
            $inIds[] = (string)(int)$idPob;
        }
        $where[] = 'o.id_pob IN (' . implode(',', $inIds) . ')';
    }

    if ((int)$tabKonfig['enable_filters'] === 1) {
        foreach ($objFilters as $key => $value) {
            if ($value === '') {
                continue;
            }

            $safe = $conn->real_escape_string($value);

            if ($key === 'cislo_obj') {
                $where[] = "COALESCE(o.restia_order_number, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'stav') {
                $where[] = "COALESCE(s.nazev, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'typ') {
                $where[] = "COALESCE(d.nazev, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'platba') {
                $where[] = "COALESCE(p.nazev, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'zakaznik') {
                $where[] = "TRIM(CONCAT(COALESCE(z.jmeno, ''), ' ', COALESCE(z.prijmeni, ''))) LIKE '%" . $safe . "%'";
            }
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countSql = '
        SELECT COUNT(*) AS cnt
        FROM objednavky_restia o
        ' . $periodJoinSql . '
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
    ' . $whereSql;


    if ($selectedPob !== []) {
        $sqlPobNames = 'SELECT id_pob, nazev FROM pobocka WHERE id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
        $resPobNames = $conn->query($sqlPobNames);
        if ($resPobNames instanceof mysqli_result) {
            $pobMap = [];
            while ($rowPob = $resPobNames->fetch_assoc()) {
                $idPobMap = (int)($rowPob['id_pob'] ?? 0);
                $nazevPobMap = trim((string)($rowPob['nazev'] ?? ''));
                if ($idPobMap >= 0 && $nazevPobMap !== '') {
                    $pobMap[$idPobMap] = $nazevPobMap;
                }
            }
            $resPobNames->free();

            foreach ($selectedPob as $idPobSel) {
                if (isset($pobMap[(int)$idPobSel])) {
                    $selectedPobNames[] = $pobMap[(int)$idPobSel];
                } else {
                    $selectedPobNames[] = (string)$idPobSel;
                }
            }
        }
    }

    $resCount = $conn->query($countSql);
    if (!($resCount instanceof mysqli_result)) {
        throw new RuntimeException('Nepodařilo se načíst počet objednávek.');
    }

    $rowCount = $resCount->fetch_assoc() ?: [];
    $resCount->free();
    $pocetObj = (int)($rowCount['cnt'] ?? 0);
    $objTotal = $pocetObj;

    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $objPages = max(1, (int)ceil($objTotal / $objPer));
        if ($objPage > $objPages) {
            $objPage = $objPages;
        }
        $offset = ($objPage - 1) * $objPer;
    } else {
        $objPages = 1;
        $objPage = 1;
        $objPer = max(1, $objTotal);
        $offset = 0;
    }

    $orderSql = 'COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) DESC, o.id_obj DESC';
    if ((int)$tabKonfig['enable_sort'] === 1) {
        $orderSql = $objSortMap[$objSort] . ' ' . $objDir . ', o.id_obj DESC';
    }

    $dataSql = '
        SELECT
            COALESCE(o.restia_order_number, "") AS cislo_obj,
            COALESCE(s.nazev, "") AS stav,
            COALESCE(c.cena_celk, "") AS cena,
            COALESCE(d.nazev, "") AS typ,
            COALESCE(p.nazev, "") AS platba,
            TRIM(CONCAT(COALESCE(z.jmeno, ""), " ", COALESCE(z.prijmeni, ""))) AS zakaznik,
            COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) AS vytvoreno
        FROM objednavky_restia o
        ' . $periodJoinSql . '
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
    ' . $whereSql . '
        ORDER BY ' . $orderSql . '
        LIMIT ' . (int)$objPer . ' OFFSET ' . (int)$offset;

    $resRows = $conn->query($dataSql);
    if (!($resRows instanceof mysqli_result)) {
        throw new RuntimeException('Nepodařilo se načíst přehled objednávek.');
    }

    while ($row = $resRows->fetch_assoc()) {
        $vytvoreno = trim((string)($row['vytvoreno'] ?? ''));
        if ($vytvoreno !== '' && $vytvoreno !== '0000-00-00 00:00:00') {
            $ts = strtotime($vytvoreno);
            if ($ts !== false) {
                $vytvoreno = date('j.n.Y G:i', $ts);
            }
        } else {
            $vytvoreno = '';
        }

        $cena = trim((string)($row['cena'] ?? ''));
        if ($cena !== '') {
            $cenaNum = (float)str_replace(',', '.', $cena);
            $cena = number_format($cenaNum, 2, ',', ' ');
        }

        $objRows[] = [
            'cislo_obj' => (string)($row['cislo_obj'] ?? ''),
            'stav' => (string)($row['stav'] ?? ''),
            'cena' => $cena,
            'typ' => (string)($row['typ'] ?? ''),
            'platba' => (string)($row['platba'] ?? ''),
            'zakaznik' => trim((string)($row['zakaznik'] ?? '')) !== '' ? trim((string)($row['zakaznik'] ?? '')) : '-',
            'vytvoreno' => $vytvoreno,
        ];
    }
    $resRows->free();
} catch (Throwable $e) {
    $pocetObj = 0;
    $objRows = [];
    $objTotal = 0;
    $objPages = 1;
    $objPage = 1;
    $objError = 'Načtení objednávek selhalo.';
}

if ($selectedPobNames !== []) {
    $pobLabel = count($selectedPobNames) === 1 ? 'Pobočka' : 'Pobočky';
    $pobText = implode(', ', $selectedPobNames);
} elseif ($selectedPob !== []) {
    $pobLabel = count($selectedPob) === 1 ? 'Pobočka' : 'Pobočky';
    $pobText = implode(', ', array_map(static fn(int $v): string => (string)$v, $selectedPob));
}

$objQueryDefaults = [
    'obj_p' => '1',
    'obj_per' => (string)$tabKonfig['default_per'],
    'obj_sort' => (string)$tabKonfig['default_sort'],
    'obj_dir' => (string)$tabKonfig['default_dir'],
];

$objBaseParams = [
    'obj_per' => (string)$objPer,
];

if ((int)$tabKonfig['enable_sort'] === 1) {
    $objBaseParams['obj_sort'] = $objSort;
    $objBaseParams['obj_dir'] = $objDir;
}
if ((int)$tabKonfig['enable_filters'] === 1 && $objFilters !== []) {
    $objBaseParams['obj_f'] = $objFilters;
}

$objBuildUrl = static function (array $extra = []) use ($objBaseParams, $objQueryDefaults): string {
    return cb_url_query('/', array_merge($objBaseParams, $extra), $objQueryDefaults);
};

$card_min_html = ''
    . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
    . '  <table class="table ram_normal bg_bila radek_1_35 card_table_min">'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Období</td>'
    . '        <td class="txt_r"><strong>' . h($periodOdText) . ' - ' . h($periodDoText) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>' . h($pobLabel) . '</td>'
    . '        <td class="txt_r"><strong>' . h($pobText) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Celkem</td>'
    . '        <td class="txt_r"><strong>' . h((string)$pocetObj) . '</strong> ' . h(obj_sklonuj_objednavka($pocetObj)) . '</td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>
<?php if ($objError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($objError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off">
    <input type="hidden" name="obj_p" value="1">
    <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
      <input type="hidden" name="obj_sort" value="<?= h($objSort) ?>">
      <input type="hidden" name="obj_dir" value="<?= h($objDir) ?>">
    <?php endif; ?>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="table ram_normal bg_bila radek_1_35 card_table_max">
        <thead>
          <tr class="filter-row">
            <th class="txt_l">
              <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($objFilterStyle['cislo_obj'] ?? 'width:9ch;')) ?>" type="text" name="obj_f[cislo_obj]" value="<?= h($objFilters['cislo_obj'] ?? '') ?>">
            </th>
            <th class="txt_l">
              <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($objFilterStyle['stav'] ?? 'width:10ch;')) ?>" type="text" name="obj_f[stav]" value="<?= h($objFilters['stav'] ?? '') ?>">
            </th>
            <th class="txt_r"></th>
            <th class="txt_l">
              <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($objFilterStyle['typ'] ?? 'width:12ch;')) ?>" type="text" name="obj_f[typ]" value="<?= h($objFilters['typ'] ?? '') ?>">
            </th>
            <th class="txt_l">
              <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($objFilterStyle['platba'] ?? 'width:10ch;')) ?>" type="text" name="obj_f[platba]" value="<?= h($objFilters['platba'] ?? '') ?>">
            </th>
            <th class="txt_l">
              <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($objFilterStyle['zakaznik'] ?? 'width:16ch;')) ?>" type="text" name="obj_f[zakaznik]" value="<?= h($objFilters['zakaznik'] ?? '') ?>">
            </th>
            <th class="txt_r">
              <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 icon-x small zaobleni_6 vyska_24 radek_24 displ_inline_flex" href="<?= h($formAction) ?>">&times;</a>
            </th>
          </tr>
          <tr>
            <th class="txt_l">č.obj.</th>
            <th class="txt_l">stav</th>
            <th class="txt_r">cena</th>
            <th class="txt_l">typ</th>
            <th class="txt_l">platba</th>
            <th class="txt_l">zákazník</th>
            <th class="txt_r">vytvořeno</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($objRows === []): ?>
            <tr>
              <td colspan="<?= h((string)count($objCols)) ?>">Žádná data</td>
            </tr>
          <?php else: ?>
            <?php foreach ($objRows as $row): ?>
              <tr>
                <td><?= h($row['cislo_obj']) ?></td>
                <td><?= h($row['stav']) ?></td>
                <td class="txt_r"><?= h($row['cena']) ?></td>
                <td><?= h($row['typ']) ?></td>
                <td><?= h($row['platba']) ?></td>
                <td><?= h($row['zakaznik']) ?></td>
                <td class="txt_r"><?= h($row['vytvoreno']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
      <div class="list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
        <div class="per-form gap_8 displ_inline_flex">
          <span>Zobrazuji</span>
          <select name="obj_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.obj_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
            <option value="20"<?= $objPer === 20 ? ' selected' : '' ?>>20 řádků</option>
            <option value="50"<?= $objPer === 50 ? ' selected' : '' ?>>50 řádků</option>
            <option value="100"<?= $objPer === 100 ? ' selected' : '' ?>>100 řádků</option>
          </select>
        </div>

        <div class="pagination-icon gap_4 displ_inline_flex">
          <?php $prevDisabled = $objPage <= 1; ?>
          <?php $nextDisabled = $objPage >= $objPages; ?>

          <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($objBuildUrl(['obj_p' => '1'])) ?>">«</a>
          <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($objBuildUrl(['obj_p' => (string)max(1, $objPage - 1)])) ?>">‹</a>

          <?php
          $pageItems = [];
          if ($objPages <= 7) {
              for ($i = 1; $i <= $objPages; $i++) {
                  $pageItems[] = $i;
              }
          } elseif ($objPage <= 4) {
              $pageItems = [1, 2, 3, 4, 5, '…', $objPages];
          } elseif ($objPage >= $objPages - 3) {
              $pageItems = [1, '…', $objPages - 4, $objPages - 3, $objPages - 2, $objPages - 1, $objPages];
          } else {
              $pageItems = [1, '…', $objPage - 1, $objPage, $objPage + 1, '…', $objPages];
          }
          ?>

          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '…'): ?>
              <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 disabled displ_inline_flex">…</span>
            <?php elseif ((int)$item === $objPage): ?>
              <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 page-current displ_inline_flex"><?= h((string)$item) ?></span>
            <?php else: ?>
              <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 displ_inline_flex" href="<?= h($objBuildUrl(['obj_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>

          <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($objBuildUrl(['obj_p' => (string)min($objPages, $objPage + 1)])) ?>">›</a>
          <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($objBuildUrl(['obj_p' => (string)$objPages])) ?>">»</a>
        </div>

        <div class="per-form gap_8 right displ_inline_flex jc_konec">
          <span>Celkem: <?= h((string)$objTotal) ?></span>
        </div>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/prehled_objednavek.php * Verze: V6 * Aktualizace: 14.04.2026 */
// Počet řádků: 501
// Předchozí počet řádků: 498
?>
