<?php
// includes/hlavicka/head_kpi.php * Verze: V3 * Aktualizace: 11.05.2026
declare(strict_types=1);

$cbHeadKpiRoleId = (int)($cbUserRoleId ?? ($_SESSION['cb_user']['id_role'] ?? 0));
$cbHeadKpiIsCurrent = ($cbHeadKpiRoleId > 0 && $cbHeadKpiRoleId <= 3);
$cbHeadKpiIsFuture = ($cbHeadKpiRoleId >= 5);

if (!$cbHeadKpiIsCurrent && !$cbHeadKpiIsFuture) {
    return;
}

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn (int $v): bool => $v > 0));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

$periodOdRaw = trim((string)($cbObdobiOd ?? ($_SESSION['cb_obdobi_od'] ?? '')));
$periodDoRaw = trim((string)($cbObdobiDo ?? ($_SESSION['cb_obdobi_do'] ?? '')));

try {
    $periodOd = $periodOdRaw !== '' ? new DateTimeImmutable($periodOdRaw) : new DateTimeImmutable('today 06:00:00');
} catch (Throwable $e) {
    $periodOd = new DateTimeImmutable('today 06:00:00');
}

try {
    $periodDo = $periodDoRaw !== '' ? new DateTimeImmutable($periodDoRaw) : new DateTimeImmutable('today 06:00:00');
} catch (Throwable $e) {
    $periodDo = new DateTimeImmutable('today 06:00:00');
}

if ($periodDo < $periodOd) {
    $periodDo = $periodOd;
}

$periodOdSql = $periodOd->format('Y-m-d H:i:s');
$periodDoSql = $periodDo->format('Y-m-d H:i:s');
$dateOd = $periodOd->format('Y-m-d');
$dateDo = $periodDo->format('Y-m-d');

$formatMoney = static function (float $value): string {
    return number_format($value, 0, ',', ' ') . ' Kč';
};

$formatHours = static function (float $value): string {
    return number_format($value, 1, ',', ' ') . ' h';
};

$formatRatio = static function (float $value): string {
    return number_format($value, 1, ',', ' ') . ' %';
};

$formatPlaceholder = static function (): string {
    return '-';
};

$normalizePercent = static function ($raw): float {
    if ($raw === null || $raw === '') {
        return 0.0;
    }

    $value = (float)$raw;
    if ($value <= 0.0) {
        return 0.0;
    }

    if ($value <= 1.5) {
        return $value * 100.0;
    }

    return $value;
};

if (!$cbHeadKpiIsCurrent) {
    ?>
<div class="head_kpi ram_hlavicka zaobleni_10 gap_6 displ_grid sirka100" data-cb-head-kpi="1" aria-label="KPI">
  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">KPI 1</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatPlaceholder()) ?>
      <span class="head_delta text_11 displ_block">Připravujeme</span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">KPI 2</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatPlaceholder()) ?>
      <span class="head_delta text_11 displ_block">Připravujeme</span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">KPI 3</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatPlaceholder()) ?>
      <span class="head_delta text_11 displ_block">Připravujeme</span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">KPI 4</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatPlaceholder()) ?>
      <span class="head_delta text_11 displ_block">Připravujeme</span>
    </div>
  </div>
</div>
    <?php
    return;
}

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$lookupIds = static function (mysqli $conn, string $table, string $idCol, string $nameCol, array $names): array {
    $names = array_values(array_filter(array_map('strval', $names), static fn (string $v): bool => trim($v) !== ''));
    if ($names === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $sql = 'SELECT `' . $idCol . '` AS id, `' . $nameCol . '` AS name FROM `' . $table . '` WHERE `' . $nameCol . '` IN (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    $types = str_repeat('s', count($names));
    $bindValues = [];
    $bindValues[] = $types;
    foreach ($names as $key => $value) {
        $bindValues[] = &$names[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $map[(string)($row['name'] ?? '')] = (int)($row['id'] ?? 0);
        }
        $result->free();
    }
    $stmt->close();

    return $map;
};

$cancelStateIds = array_values(array_filter(array_map(
    'intval',
    $lookupIds($conn, 'cis_obj_stav', 'id_stav', 'nazev', ['canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted'])
), static fn (int $v): bool => $v > 0));

$ordersWhereSql = '
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= ?
      AND o.restia_created_at < ?
';
$ordersTypes = 'ss';
$ordersParams = [$periodOdSql, $periodDoSql];

if ($cancelStateIds !== []) {
    $ordersWhereSql .= ' AND (o.id_stav IS NULL OR o.id_stav NOT IN (' . implode(',', array_map('intval', $cancelStateIds)) . '))';
}

if ($selectedPob !== []) {
    $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
    $ordersWhereSql .= ' AND o.id_pob IN (' . $placeholders . ')';
    $ordersTypes .= str_repeat('i', count($selectedPob));
    foreach ($selectedPob as $idPob) {
        $ordersParams[] = $idPob;
    }
}

$kpi = [
    'report_days' => 0,
    'branch_count' => 0,
    'trzba' => 0.0,
    'objednavky' => 0,
    'hodiny_celkem' => 0.0,
    'trzba_na_hodinu' => 0.0,
    'vcas_percent' => 0.0,
];

$ordersSql = '
    SELECT
        COUNT(*) AS objednavky,
        COUNT(DISTINCT o.id_pob) AS branch_count,
        COUNT(DISTINCT DATE(o.restia_created_at)) AS report_days,
        SUM(COALESCE(c.cena_celk, 0)) AS trzba
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c
        ON c.id_obj = o.id_obj
' . $ordersWhereSql;

$stmtOrders = $conn->prepare($ordersSql);
if ($stmtOrders !== false) {
    $bindOrders = [];
    $bindOrders[] = $ordersTypes;
    foreach ($ordersParams as $key => $value) {
        $bindOrders[] = &$ordersParams[$key];
    }
    call_user_func_array([$stmtOrders, 'bind_param'], $bindOrders);
    $stmtOrders->execute();
    $ordersResult = $stmtOrders->get_result();
    if ($ordersResult instanceof mysqli_result) {
        $row = $ordersResult->fetch_assoc();
        if (is_array($row)) {
            $kpi['report_days'] = (int)($row['report_days'] ?? 0);
            $kpi['branch_count'] = (int)($row['branch_count'] ?? 0);
            $kpi['trzba'] = (float)($row['trzba'] ?? 0);
            $kpi['objednavky'] = (int)($row['objednavky'] ?? 0);
        }
        $ordersResult->free();
    }
    $stmtOrders->close();
}

$hoursSql = '
    SELECT
        SUM(COALESCE(ro.hodiny_celkem, 0)) AS hodiny_celkem,
        SUM(COALESCE(rr.objednavky_nezrusene_ks, 0) * COALESCE(rr.doruceno_vcas_pomer, 0)) AS vcas_vazeno
    FROM reporty r
    LEFT JOIN reporty_restia rr
        ON rr.id_reportu = r.id_reportu
    LEFT JOIN (
        SELECT
            id_reportu,
            SUM(COALESCE(odpracovano, 0)) AS hodiny_celkem
        FROM reporty_osoby
        GROUP BY id_reportu
    ) ro
        ON ro.id_reportu = r.id_reportu
    WHERE r.platny = 1
      AND r.datum_reportu >= ?
      AND r.datum_reportu <= ?
';

$hoursTypes = 'ss';
$hoursParams = [$dateOd, $dateDo];
if ($selectedPob !== []) {
    $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
    $hoursSql .= ' AND r.id_pob IN (' . $placeholders . ')';
    $hoursTypes .= str_repeat('i', count($selectedPob));
    foreach ($selectedPob as $idPob) {
        $hoursParams[] = $idPob;
    }
}

$stmtHours = $conn->prepare($hoursSql);
if ($stmtHours !== false) {
    $bindHours = [];
    $bindHours[] = $hoursTypes;
    foreach ($hoursParams as $key => $value) {
        $bindHours[] = &$hoursParams[$key];
    }
    call_user_func_array([$stmtHours, 'bind_param'], $bindHours);
    $stmtHours->execute();
    $hoursResult = $stmtHours->get_result();
    if ($hoursResult instanceof mysqli_result) {
        $row = $hoursResult->fetch_assoc();
        if (is_array($row)) {
            $kpi['hodiny_celkem'] = (float)($row['hodiny_celkem'] ?? 0);
            $kpi['trzba_na_hodinu'] = $kpi['hodiny_celkem'] > 0 ? ($kpi['trzba'] / $kpi['hodiny_celkem']) : 0.0;
            $kpi['vcas_percent'] = $kpi['objednavky'] > 0
                ? $normalizePercent(((float)($row['vcas_vazeno'] ?? 0.0)) / $kpi['objednavky'])
                : 0.0;
        }
        $hoursResult->free();
    }
    $stmtHours->close();
}

$headMeta = 'Pobočky ' . number_format($kpi['branch_count'], 0, ',', ' ') . ' | Dny ' . number_format($kpi['report_days'], 0, ',', ' ');
?>
<div class="head_kpi ram_hlavicka zaobleni_10 gap_6 displ_grid sirka100" data-cb-head-kpi="1" aria-label="KPI">
  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">Tržba</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatMoney((float)$kpi['trzba'])) ?>
      <span class="head_delta text_11 displ_block"><?= h($headMeta) ?></span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">Hodiny celkem</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatHours((float)$kpi['hodiny_celkem'])) ?>
      <span class="head_delta text_11 displ_block">Objednávky <?= h(number_format($kpi['objednavky'], 0, ',', ' ')) ?></span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">Tržba / hodina</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatMoney((float)$kpi['trzba_na_hodinu'])) ?>
      <span class="head_delta text_11 displ_block">Průměr za vybrané období</span>
    </div>
  </div>

  <div class="head_kpi_item zaobleni_8 displ_flex">
    <div class="head_kpi_k text_11 radek_1_1">% včas</div>
    <div class="head_kpi_v text_12 radek_1_1">
      <?= h($formatRatio((float)$kpi['vcas_percent'])) ?>
      <span class="head_delta text_11 displ_block">Vážený průměr z reportů</span>
    </div>
  </div>
</div>
