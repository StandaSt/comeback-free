<?php
// K16
// karty/mzdy_new.php * Verze: V5 * Aktualizace: 10.06.2026
declare(strict_types=1);

/*
 * Karta "Mzdy new":
 * - pocita mesicni mzdy z reporty + reporty_osoby,
 * - sazby bere z hr_sazby podle platnosti v mesici,
 * - v max rezimu umi filtry, trideni a strankovani,
 * - nezapisuje do DB.
 */

/*
 * K16 vypocty mezd
 * Zdroj pravidel: vzorce v HR 2024.xlsx, listy Mzdy ...
 *
 * HR sešit používá:
 * - zaměstnavatel: hrubá mzda * 0,338
 * - Nec: hrubá mzda * 0,116
 * - Daň: hrubá mzda * 0,15 - sleva
 * - Čistá mzda: pokud je daň záporná, hrubá - Nec, jinak hrubá - Nec - daň
 * - Náklad / superhrubá mzda: hrubá + zaměstnavatel
 *
 * V K16 zatím počítáme jen z dat, která máme v DB:
 * - hodiny z reporty_osoby,
 * - hodinová sazba / fix z hr_sazby.
 * Ruční bonusy a ISK ze starého HR sešitu v DB zatím nejsou, proto se zde nepřičítají.
 */
$k16PayrollRules = [
    'zamestnavatel_sazba' => 0.338,
    'nec_sazba' => 0.116,
    'dan_sazba' => 0.15,
    'sleva_na_dan' => 2570.0,
];

if (!function_exists('cb_k16_payroll_calc')) {
    function cb_k16_payroll_calc(float $hours, ?int $typeId, ?float $hourRate, ?float $monthlyFix, array $rules): array
    {
        $gross = null;

        if ($typeId === 2 && $monthlyFix !== null) {
            $gross = $monthlyFix;
        } elseif ($typeId === 1 && $hourRate !== null) {
            $gross = $hours * $hourRate;
        } elseif ($typeId === 3) {
            $gross = (float)($monthlyFix ?? 0.0) + ($hours * (float)($hourRate ?? 0.0));
        } elseif ($typeId === 4) {
            $gross = 0.0;
        }

        if ($gross === null) {
            return [
                'cista_mzda' => null,
                'hruba_mzda' => null,
                'superhruba_mzda' => null,
            ];
        }

        $employer = $gross * (float)$rules['zamestnavatel_sazba'];
        $employee = $gross * (float)$rules['nec_sazba'];
        $tax = ($gross * (float)$rules['dan_sazba']) - (float)$rules['sleva_na_dan'];
        $net = $tax < 0 ? ($gross - $employee) : ($gross - $employee - $tax);

        return [
            'cista_mzda' => $net,
            'hruba_mzda' => $gross,
            'superhruba_mzda' => $gross + $employer,
        ];
    }
}

if (!function_exists('cb_k16_int')) {
    function cb_k16_int(mixed $value): string
    {
        return number_format((float)$value, 0, ',', ' ');
    }
}

if (!function_exists('cb_k16_dec')) {
    function cb_k16_dec(mixed $value): string
    {
        return number_format((float)$value, 2, ',', ' ');
    }
}

if (!function_exists('cb_k16_money')) {
    function cb_k16_money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return cb_k16_int($value) . ' Kč';
    }
}

if (!function_exists('cb_k16_month_label')) {
    function cb_k16_month_label(int $rok, int $mesic): string
    {
        return (string)$mesic . '/' . substr((string)$rok, -2);
    }
}

if (!function_exists('cb_k16_contains')) {
    function cb_k16_contains(mixed $value, string $needle): bool
    {
        return $needle === '' || mb_stripos((string)$value, $needle, 0, 'UTF-8') !== false;
    }
}

$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'default_sort' => 'mesic',
    'default_dir' => 'DESC',
    'per_options' => [20, 50, 100],
];

$mzdyRowsAll = [];
$mzdyRows = [];
$mzdyTotal = 0;
$mzdyPages = 1;
$mzdyPage = 1;
$mzdyPer = (int)$tabKonfig['default_per'];
$mzdyMode = 'all';
$mzdySlot = 'all';
$mzdyHours = 'all';
$mzdySort = (string)$tabKonfig['default_sort'];
$mzdyDir = (string)$tabKonfig['default_dir'];
$mzdyFilters = [];
$mzdyError = '';
$mzdyStats = [
    'total' => 0,
    'with_id' => 0,
    'without_id' => 0,
];
$mzdyTotals = [
    'hodiny' => 0.0,
    'cista_mzda' => 0.0,
    'hruba_mzda' => 0.0,
    'superhruba_mzda' => 0.0,
];
$formAction = cb_url('/');

$prihlasenyIdUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
$prihlasenyIdRole = (int)($_SESSION['cb_user']['id_role'] ?? 0);
$mzdyRoleLimitSql = '';

if ($prihlasenyIdRole > 3) {
    if ($prihlasenyIdRole === 9) {
        $mzdyRoleLimitSql = '
          AND m.id_user = ' . $prihlasenyIdUser;
    } elseif ($prihlasenyIdRole === 7) {
        $mzdyRoleLimitSql = '
          AND m.id_user IS NOT NULL
          AND (
              m.id_user = ' . $prihlasenyIdUser . '
              OR (
                  EXISTS (
                      SELECT 1
                      FROM user_role ur_radek
                      WHERE ur_radek.id_user = m.id_user
                        AND ur_radek.id_role = 9
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM user_pobocka up_prihlaseny
                      INNER JOIN user_pobocka up_radek ON up_radek.id_pob = up_prihlaseny.id_pob
                      WHERE up_prihlaseny.id_user = ' . $prihlasenyIdUser . '
                        AND up_radek.id_user = m.id_user
                  )
              )
          )';
    } elseif ($prihlasenyIdRole === 5) {
        $mzdyRoleLimitSql = '
          AND m.id_user IS NOT NULL
          AND (
              m.id_user = ' . $prihlasenyIdUser . '
              OR (
                  EXISTS (
                      SELECT 1
                      FROM user_role ur_radek
                      WHERE ur_radek.id_user = m.id_user
                        AND ur_radek.id_role IN (7, 9)
                  )
                  AND EXISTS (
                      SELECT 1
                      FROM user_pobocka up_prihlaseny
                      INNER JOIN user_pobocka up_radek ON up_radek.id_pob = up_prihlaseny.id_pob
                      WHERE up_prihlaseny.id_user = ' . $prihlasenyIdUser . '
                        AND up_radek.id_user = m.id_user
                  )
              )
          )';
    } else {
        $mzdyRoleLimitSql = '
          AND m.id_user = ' . $prihlasenyIdUser;
    }
}

$mzdyCols = [
    'mesic' => ['label' => 'měsíc', 'width' => '90px', 'filter' => true],
    'id_user' => ['label' => 'ID user', 'width' => '90px', 'filter' => true],
    'import_jmeno' => ['label' => 'importované jméno', 'width' => '190px', 'filter' => true],
    'prijmeni' => ['label' => 'příjmení', 'width' => '150px', 'filter' => true],
    'jmeno' => ['label' => 'jméno', 'width' => '150px', 'filter' => true],
    'mzda_typ' => ['label' => 'typ', 'width' => '110px', 'filter' => true],
    'hodiny' => ['label' => 'hodiny', 'width' => '110px'],
    'hodinova_sazba' => ['label' => 'sazba', 'width' => '110px'],
    'mesicni_fix' => ['label' => 'fix', 'width' => '110px'],
    'cista_mzda' => ['label' => 'čistá mzda', 'width' => '130px'],
    'hruba_mzda' => ['label' => 'hrubá mzda', 'width' => '130px'],
    'superhruba_mzda' => ['label' => 'náklad', 'width' => '130px'],
];

$mzdySortMap = [
    'mesic' => static fn(array $r): string => sprintf('%04d-%02d', (int)$r['rok'], (int)$r['mesic']),
    'id_user' => static fn(array $r): int => (int)($r['id_user'] ?? 0),
    'import_jmeno' => static fn(array $r): string => (string)($r['import_jmeno'] ?? ''),
    'prijmeni' => static fn(array $r): string => (string)($r['prijmeni'] ?? ''),
    'jmeno' => static fn(array $r): string => (string)($r['jmeno'] ?? ''),
    'mzda_typ' => static fn(array $r): string => (string)($r['mzda_typ'] ?? ''),
    'hodiny' => static fn(array $r): float => (float)($r['hodiny'] ?? 0),
    'hodinova_sazba' => static fn(array $r): float => (float)($r['hodinova_sazba'] ?? 0),
    'mesicni_fix' => static fn(array $r): float => (float)($r['mesicni_fix'] ?? 0),
    'cista_mzda' => static fn(array $r): float => (float)($r['cista_mzda'] ?? 0),
    'hruba_mzda' => static fn(array $r): float => (float)($r['hruba_mzda'] ?? 0),
    'superhruba_mzda' => static fn(array $r): float => (float)($r['superhruba_mzda'] ?? 0),
];

$mzdyPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($mzdyPerOptions === []) {
    $mzdyPerOptions = [20, 50, 100];
}

$mzdyPerRaw = (int)($_GET['mzdy_new_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($mzdyPerRaw, $mzdyPerOptions, true)) {
    $mzdyPer = $mzdyPerRaw;
}

$mzdyPageRaw = (int)($_GET['mzdy_new_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $mzdyPageRaw > 1) {
    $mzdyPage = $mzdyPageRaw;
}

$mzdyModeRaw = (string)($_GET['mzdy_new_mode'] ?? 'all');
if (in_array($mzdyModeRaw, ['all', 'with_id', 'without_id'], true)) {
    $mzdyMode = $mzdyModeRaw;
}

$mzdySlotRaw = (string)($_GET['mzdy_new_slot'] ?? 'all');
if (in_array($mzdySlotRaw, ['all', '1', '2', '3'], true)) {
    $mzdySlot = $mzdySlotRaw;
}

$mzdyHoursRaw = (string)($_GET['mzdy_new_hours'] ?? 'all');
if (in_array($mzdyHoursRaw, ['all', 'with_hours'], true)) {
    $mzdyHours = $mzdyHoursRaw;
}

$mzdySortRaw = trim((string)($_GET['mzdy_new_sort'] ?? (string)$tabKonfig['default_sort']));
$mzdyDirRaw = strtoupper(trim((string)($_GET['mzdy_new_dir'] ?? (string)$tabKonfig['default_dir'])));
if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($mzdySortRaw, $mzdySortMap)) {
    $mzdySort = $mzdySortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($mzdyDirRaw, ['ASC', 'DESC'], true)) {
    $mzdyDir = $mzdyDirRaw;
}

$mzdyFiltersRaw = $_GET['mzdy_new_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($mzdyFiltersRaw)) {
    foreach ($mzdyCols as $key => $cfg) {
        if (empty($cfg['filter'])) {
            continue;
        }
        $mzdyFilters[$key] = trim((string)($mzdyFiltersRaw[$key] ?? ''));
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $sql = '
        SELECT
            m.rok,
            m.mesic,
            m.mesic_od,
            m.id_user,
            m.import_jmeno,
            m.slot_id,
            COALESCE(u.prijmeni, "") AS prijmeni,
            COALESCE(u.jmeno, "") AS jmeno,
            (
                SELECT MIN(ur_select.id_role)
                FROM user_role ur_select
                WHERE ur_select.id_user = m.id_user
            ) AS id_role,
            hs.id_mzda_typ,
            COALESCE(cmt.nazev, "") AS mzda_typ,
            hs.hodinova_sazba,
            hs.mesicni_fix,
            m.hodiny
        FROM (
            SELECT
                YEAR(r.datum_reportu) AS rok,
                MONTH(r.datum_reportu) AS mesic,
                DATE_FORMAT(r.datum_reportu, "%Y-%m-01") AS mesic_od,
                ro.id_user,
                CASE
                    WHEN ro.id_user IS NULL THEN TRIM(CONCAT(COALESCE(ro.prijmeni, ""), " ", COALESCE(ro.jmeno, "")))
                    ELSE ""
                END AS import_jmeno,
                ro.slot AS slot_id,
                SUM(COALESCE(ro.odpracovano, 0)) AS hodiny
            FROM reporty r
            INNER JOIN reporty_osoby ro ON ro.id_reportu = r.id_reportu
            WHERE r.platny = 1
              AND r.stav = 1
            GROUP BY
                YEAR(r.datum_reportu),
                MONTH(r.datum_reportu),
                DATE_FORMAT(r.datum_reportu, "%Y-%m-01"),
                ro.id_user,
                ro.jmeno,
                ro.prijmeni,
                ro.slot
        ) m
        LEFT JOIN `user` u ON u.id_user = m.id_user
        LEFT JOIN hr_sazby hs
            ON (
                (m.id_user IS NOT NULL AND hs.id_user = m.id_user)
                OR (m.id_user IS NULL AND hs.id_user IS NULL AND hs.import_jmeno = m.import_jmeno)
            )
           AND hs.platnost_od <= m.mesic_od
           AND (hs.platnost_do IS NULL OR hs.platnost_do >= m.mesic_od)
        LEFT JOIN cis_mzda_typ cmt ON cmt.id_mzda_typ = hs.id_mzda_typ
        WHERE 1 = 1
        ' . $mzdyRoleLimitSql . '
    ';

    $resRows = $conn->query($sql);
    if (!$resRows) {
        throw new RuntimeException('SQL chyba mezd: ' . $conn->error);
    }

    while ($row = $resRows->fetch_assoc()) {
            $hours = (float)($row['hodiny'] ?? 0);
            $typeId = $row['id_mzda_typ'] !== null ? (int)$row['id_mzda_typ'] : null;
            $hourRate = $row['hodinova_sazba'] !== null ? (float)$row['hodinova_sazba'] : null;
            $monthlyFix = $row['mesicni_fix'] !== null ? (float)$row['mesicni_fix'] : null;
            $calc = cb_k16_payroll_calc($hours, $typeId, $hourRate, $monthlyFix, $k16PayrollRules);

            $row['hodiny'] = $hours;
            $row['id_mzda_typ'] = $typeId;
            $row['hodinova_sazba'] = $hourRate;
            $row['mesicni_fix'] = $monthlyFix;
            $row['cista_mzda'] = $calc['cista_mzda'];
            $row['hruba_mzda'] = $calc['hruba_mzda'];
            $row['superhruba_mzda'] = $calc['superhruba_mzda'];
            $mzdyRowsAll[] = $row;
        }
    $resRows->free();

    $mzdyStats['total'] = count($mzdyRowsAll);
    foreach ($mzdyRowsAll as $row) {
        if ((int)($row['id_user'] ?? 0) > 0) {
            $mzdyStats['with_id']++;
        } else {
            $mzdyStats['without_id']++;
        }
    }

    $filtered = [];
    foreach ($mzdyRowsAll as $row) {
        if ($mzdyMode === 'with_id' && (int)($row['id_user'] ?? 0) <= 0) {
            continue;
        }
        if ($mzdyMode === 'without_id' && (int)($row['id_user'] ?? 0) > 0) {
            continue;
        }
        if ($mzdySlot !== 'all' && (string)($row['slot_id'] ?? '') !== $mzdySlot) {
            continue;
        }
        if ($mzdyHours === 'with_hours' && (float)($row['hodiny'] ?? 0) <= 0) {
            continue;
        }
        if (!cb_k16_contains(cb_k16_month_label((int)$row['rok'], (int)$row['mesic']), $mzdyFilters['mesic'] ?? '')) {
            continue;
        }
        if (($mzdyFilters['id_user'] ?? '') !== '' && (string)($row['id_user'] ?? '') !== (string)$mzdyFilters['id_user']) {
            continue;
        }
        foreach (['import_jmeno', 'prijmeni', 'jmeno', 'mzda_typ'] as $filterKey) {
            if (!cb_k16_contains($row[$filterKey] ?? '', $mzdyFilters[$filterKey] ?? '')) {
                continue 2;
            }
        }
        $filtered[] = $row;
    }

    foreach ($filtered as $row) {
        $mzdyTotals['hodiny'] += (float)($row['hodiny'] ?? 0);
        $mzdyTotals['cista_mzda'] += (float)($row['cista_mzda'] ?? 0);
        $mzdyTotals['hruba_mzda'] += (float)($row['hruba_mzda'] ?? 0);
        $mzdyTotals['superhruba_mzda'] += (float)($row['superhruba_mzda'] ?? 0);
    }

    $sortFn = $mzdySortMap[$mzdySort] ?? $mzdySortMap['mesic'];
    usort($filtered, static function (array $a, array $b) use ($sortFn, $mzdyDir): int {
        $av = $sortFn($a);
        $bv = $sortFn($b);
        $cmp = is_numeric($av) && is_numeric($bv) ? ((float)$av <=> (float)$bv) : strnatcasecmp((string)$av, (string)$bv);
        if ($cmp === 0) {
            $cmp = sprintf('%04d-%02d', (int)$a['rok'], (int)$a['mesic']) <=> sprintf('%04d-%02d', (int)$b['rok'], (int)$b['mesic']);
        }
        return $mzdyDir === 'DESC' ? -$cmp : $cmp;
    });

    $mzdyTotal = count($filtered);
    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $mzdyPages = max(1, (int)ceil($mzdyTotal / $mzdyPer));
        if ($mzdyPage > $mzdyPages) {
            $mzdyPage = $mzdyPages;
        }
        $offset = ($mzdyPage - 1) * $mzdyPer;
        $mzdyRows = array_slice($filtered, $offset, $mzdyPer);
    } else {
        $mzdyRows = $filtered;
    }
} catch (Throwable $e) {
    $mzdyRows = [];
    $mzdyTotal = 0;
    $mzdyPages = 1;
    $mzdyPage = 1;
    $mzdyError = 'Načtení mezd selhalo: ' . $e->getMessage();
}

$mzdyQueryDefaults = [
    'mzdy_new_p' => '1',
    'mzdy_new_per' => (string)$tabKonfig['default_per'],
    'mzdy_new_mode' => 'all',
    'mzdy_new_slot' => 'all',
    'mzdy_new_hours' => 'all',
    'mzdy_new_sort' => (string)$tabKonfig['default_sort'],
    'mzdy_new_dir' => (string)$tabKonfig['default_dir'],
];
$mzdyBaseParams = [
    'cb_load_max' => '1',
    'mzdy_new_per' => (string)$mzdyPer,
    'mzdy_new_mode' => $mzdyMode,
    'mzdy_new_slot' => $mzdySlot,
    'mzdy_new_hours' => $mzdyHours,
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $mzdyBaseParams['mzdy_new_sort'] = $mzdySort;
    $mzdyBaseParams['mzdy_new_dir'] = $mzdyDir;
}
if ((int)$tabKonfig['enable_filters'] === 1 && $mzdyFilters !== []) {
    $mzdyBaseParams['mzdy_new_f'] = $mzdyFilters;
}
$mzdyBuildUrl = static function (array $extra = []) use ($mzdyBaseParams, $mzdyQueryDefaults): string {
    return cb_url_query('/', array_merge($mzdyBaseParams, $extra), $mzdyQueryDefaults);
};
$mzdyResetUrl = cb_url_query('/', ['cb_load_max' => '1'], $mzdyQueryDefaults);

ob_start();
?>
<div class="displ_flex jc_stred">
  <table class="table ram_normal bg_bila radek_1_35 card_table_min sirka100">
    <tbody>
      <tr>
        <td>Mzdy celkem</td>
        <td class="txt_r"><strong><?= h(cb_k16_int($mzdyStats['total'])) ?></strong></td>
      </tr>
      <tr>
        <td>Bez ID</td>
        <td class="txt_r"><strong><?= h(cb_k16_int($mzdyStats['without_id'])) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<?php $mzdyDebounceJs = "clearTimeout(this._cbDebounce);this._cbDebounce=setTimeout(function(field){field.form.mzdy_new_p.value=1;if(field.form.requestSubmit){field.form.requestSubmit();}else{field.form.submit();}},350,this);"; ?>
<?php if ($mzdyError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($mzdyError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-max-form="1">
    <input type="hidden" name="cb_load_max" value="1">
    <input type="hidden" name="mzdy_new_p" value="1">
    <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
      <input type="hidden" name="mzdy_new_sort" value="<?= h($mzdySort) ?>">
      <input type="hidden" name="mzdy_new_dir" value="<?= h($mzdyDir) ?>">
    <?php endif; ?>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="card-max-table">
        <thead>
          <tr class="card-max-filter filter-row">
            <?php foreach ($mzdyCols as $key => $cfg): ?>
              <?php if (!empty($cfg['filter'])): ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;">
                  <input class="filter-input" type="text" name="mzdy_new_f[<?= h($key) ?>]" value="<?= h($mzdyFilters[$key] ?? '') ?>" autocomplete="off" oninput="<?= h($mzdyDebounceJs) ?>">
                </th>
              <?php elseif ($key === 'hodiny'): ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;">
                  <div class="filter-actions gap_8 displ_flex">
                    <a href="<?= h($mzdyResetUrl) ?>" class="filter-reset-btn cursor_ruka ram_normal zaobleni_8 vyska_24 radek_24 displ_inline_flex">
                      <span class="filter-reset-x">&times;</span>
                      <span>Zrušit filtr</span>
                    </a>
                  </div>
                </th>
              <?php elseif ($key === 'mesicni_fix'): ?>
                <th colspan="2" style="width:240px;">
                  <select name="mzdy_new_slot" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 sirka100" onchange="this.form.mzdy_new_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                    <option value="all"<?= $mzdySlot === 'all' ? ' selected' : '' ?>>Vše</option>
                    <option value="1"<?= $mzdySlot === '1' ? ' selected' : '' ?>>Instor</option>
                    <option value="2"<?= $mzdySlot === '2' ? ' selected' : '' ?>>Kurýr</option>
                    <option value="3"<?= $mzdySlot === '3' ? ' selected' : '' ?>>Výroba</option>
                  </select>
                </th>
              <?php elseif ($key === 'hruba_mzda'): ?>
                <th colspan="2" style="width:260px;">
                  <select name="mzdy_new_hours" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 sirka100" onchange="this.form.mzdy_new_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                    <option value="all"<?= $mzdyHours === 'all' ? ' selected' : '' ?>>Vše</option>
                    <option value="with_hours"<?= $mzdyHours === 'with_hours' ? ' selected' : '' ?>>Pouze s hodinami</option>
                  </select>
                </th>
              <?php elseif (in_array($key, ['cista_mzda', 'superhruba_mzda'], true)): ?>
                <?php continue; ?>
              <?php else: ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;"></th>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
          <tr>
            <?php foreach ($mzdyCols as $key => $cfg): ?>
              <?php
              $isSortable = isset($mzdySortMap[$key]);
              $isActiveSort = ($mzdySort === $key);
              $arrow = '↕';
              if ($isActiveSort) {
                  $arrow = $mzdyDir === 'ASC' ? '↑' : '↓';
              }
              $rightCols = ['id_user', 'hodiny', 'hodinova_sazba', 'mesicni_fix', 'cista_mzda', 'hruba_mzda', 'superhruba_mzda'];
              ?>
              <th class="th-sort<?= $isActiveSort ? ' active' : '' ?><?= in_array($key, $rightCols, true) ? ' txt_r' : '' ?>" style="width:<?= h((string)$cfg['width']) ?>;">
                <?php if ((int)$tabKonfig['enable_sort'] === 1 && $isSortable): ?>
                  <?php
                  $nextDir = ($isActiveSort && $mzdyDir === 'ASC') ? 'DESC' : 'ASC';
                  $sortUrl = $mzdyBuildUrl([
                      'mzdy_new_p' => '1',
                      'mzdy_new_sort' => $key,
                      'mzdy_new_dir' => $nextDir,
                  ]);
                  ?>
                  <a class="th-sort-link gap_8 jc_mezi sirka100<?= $isActiveSort ? ' active' : '' ?>" href="<?= h($sortUrl) ?>">
                    <span class="th-sort-label"><?= h((string)$cfg['label']) ?></span>
                    <span class="th-sort-arrow txt_r"><?= h($arrow) ?></span>
                  </a>
                <?php else: ?>
                  <span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label"><?= h((string)$cfg['label']) ?></span></span>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$mzdyRows): ?>
            <tr>
              <td colspan="<?= h((string)count($mzdyCols)) ?>" style="text-align:center; padding:22px 0; color:#888;">Žádná data</td>
            </tr>
          <?php else: ?>
            <?php foreach ($mzdyRows as $row): ?>
              <?php $isUnpaired = (int)($row['id_user'] ?? 0) <= 0; ?>
              <tr<?= $isUnpaired ? ' class="k17-row-unpaired"' : '' ?>>
                <td><?= h(cb_k16_month_label((int)$row['rok'], (int)$row['mesic'])) ?></td>
                <td class="txt_r"><?= h((string)($row['id_user'] ?? '')) ?></td>
                <td><?= h((string)($row['import_jmeno'] ?? '')) ?></td>
                <td><?= h((string)($row['prijmeni'] ?? '')) ?></td>
                <td><?= h((string)($row['jmeno'] ?? '')) ?></td>
                <td><?= h((string)($row['mzda_typ'] ?? '')) ?></td>
                <td class="txt_r"><?= h(cb_k16_dec($row['hodiny'] ?? 0)) ?></td>
                <td class="txt_r"><?= h(cb_k16_money($row['hodinova_sazba'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k16_money($row['mesicni_fix'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k16_money($row['cista_mzda'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k16_money($row['hruba_mzda'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k16_money($row['superhruba_mzda'] ?? null)) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6">Součet</td>
            <td class="txt_r"><?= h(cb_k16_dec($mzdyTotals['hodiny'])) ?></td>
            <td class="txt_r"></td>
            <td class="txt_r"></td>
            <td class="txt_r"><?= h(cb_k16_money($mzdyTotals['cista_mzda'])) ?></td>
            <td class="txt_r"><?= h(cb_k16_money($mzdyTotals['hruba_mzda'])) ?></td>
            <td class="txt_r"><?= h(cb_k16_money($mzdyTotals['superhruba_mzda'])) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
      <div class="card-max-pagination list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
        <div class="per-form gap_8 displ_inline_flex">
          <span>Zobrazit</span>
          <select name="mzdy_new_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.mzdy_new_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
            <option value="20"<?= $mzdyPer === 20 ? ' selected' : '' ?>>20 řádků</option>
            <option value="50"<?= $mzdyPer === 50 ? ' selected' : '' ?>>50 řádků</option>
            <option value="100"<?= $mzdyPer === 100 ? ' selected' : '' ?>>100 řádků</option>
          </select>
          <span>celkem <?= h(cb_k16_int($mzdyTotal)) ?> řádků</span>
        </div>

        <div class="pagination-icon gap_4 displ_inline_flex">
          <?php $prevDisabled = $mzdyPage <= 1; ?>
          <?php $nextDisabled = $mzdyPage >= $mzdyPages; ?>
          <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($mzdyBuildUrl(['mzdy_new_p' => '1'])) ?>">«</a>
          <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($mzdyBuildUrl(['mzdy_new_p' => (string)max(1, $mzdyPage - 1)])) ?>">‹</a>
          <?php
          $pageItems = [];
          if ($mzdyPages <= 7) {
              for ($i = 1; $i <= $mzdyPages; $i++) {
                  $pageItems[] = $i;
              }
          } elseif ($mzdyPage <= 4) {
              $pageItems = [1, 2, 3, 4, 5, '…', $mzdyPages];
          } elseif ($mzdyPage >= $mzdyPages - 3) {
              $pageItems = [1, '…', $mzdyPages - 4, $mzdyPages - 3, $mzdyPages - 2, $mzdyPages - 1, $mzdyPages];
          } else {
              $pageItems = [1, '…', $mzdyPage - 1, $mzdyPage, $mzdyPage + 1, '…', $mzdyPages];
          }
          ?>
          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '…'): ?>
              <span class="icon-btn disabled">…</span>
            <?php elseif ((int)$item === $mzdyPage): ?>
              <span class="icon-btn page-current"><?= h((string)$item) ?></span>
            <?php else: ?>
              <a class="icon-btn" href="<?= h($mzdyBuildUrl(['mzdy_new_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($mzdyBuildUrl(['mzdy_new_p' => (string)min($mzdyPages, $mzdyPage + 1)])) ?>">›</a>
          <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($mzdyBuildUrl(['mzdy_new_p' => (string)$mzdyPages])) ?>">»</a>
        </div>

        <div class="per-form gap_8 right displ_inline_flex jc_konec">
          <select name="mzdy_new_mode" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 akt-select sirka_min_160" onchange="this.form.mzdy_new_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
            <option value="all"<?= $mzdyMode === 'all' ? ' selected' : '' ?>>Všichni</option>
            <option value="with_id"<?= $mzdyMode === 'with_id' ? ' selected' : '' ?>>Zaměstnanci s ID</option>
            <option value="without_id"<?= $mzdyMode === 'without_id' ? ' selected' : '' ?>>Zaměstnanci bez ID</option>
          </select>
        </div>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/mzdy_new.php * Verze: V4 * Aktualizace: 10.06.2026 */
?>
