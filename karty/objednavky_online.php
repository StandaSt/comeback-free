<?php
// K19
// karty/objednavky_online.php * Verze: V5 * Aktualizace: 29.04.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Data online objednavek nejsou k dispozici.</p>';
$card_max_html = '';

if (!function_exists('cb_k19_render_max_tile')) {
    function cb_k19_render_max_tile(string $code): string
    {
        return ''
            . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;">'
            . '<div class="odstup_spod_4">'
            . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) 36px; align-items:start; column-gap:8px; line-height:1.15;">'
            . '<div class="card_text text_12" style="color:var(--clr_seda_3);">' . h($code) . '</div>'
            . '<div class="card_text txt_c"><strong>Graf se připravuje</strong></div>'
            . '<div></div>'
            . '</div>'
            . '</div>'
            . '<div class="displ_flex ai_stred jc_stred sirka100" style="height:460px; min-height:0;">'
            . '<p class="card_text txt_seda txt_c odstup_vnejsi_0">Graf se připravuje</p>'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('cb_k19_render_max_chart_tile')) {
    function cb_k19_render_max_chart_tile(string $code, string $title, string $periodText, string $chartId, string $chartJson): string
    {
        return ''
            . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;">'
            . '<div class="odstup_spod_4">'
            . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) 36px; align-items:start; column-gap:8px; line-height:1.15;">'
            . '<div class="card_text text_12" style="color:var(--clr_seda_3);">' . h($code) . '</div>'
            . '<div class="card_text txt_c"><strong>' . h($title) . '</strong></div>'
            . '<div></div>'
            . '</div>'
            . '<div class="card_text txt_seda text_12 txt_c" style="line-height:1.15;">' . h($periodText) . '</div>'
            . '</div>'
            . '<div id="' . h($chartId) . '" data-cb-prehledy-grafy-chart="1" data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '" class="sirka100" style="height:460px;"></div>'
            . '</div>';
    }
}

if (!function_exists('cb_k19_online_workday_range')) {
    function cb_k19_online_workday_range(): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $now = new DateTimeImmutable('now', $tz);
        $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 08:00:00', $tz);
        if (!($todayStart instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se urcit pracovni den pro K19.');
        }

        $workdayStart = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;

        return [
            'from' => $workdayStart->format('Y-m-d H:i:s'),
            'to' => $now->format('Y-m-d H:i:s'),
            'label' => $workdayStart->format('j.n.Y H:i') . ' - ' . $now->format('H:i'),
        ];
    }
}
$k19G1MaxTileHtml = cb_k19_render_max_tile('G1');
$k19G2MaxTileHtml = cb_k19_render_max_tile('G2');

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $range = cb_k19_online_workday_range();
    $aktualizaceDoText = '';
    $selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    $selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v > 0));

    $resAktualizace = $conn->query('SELECT MAX(start) AS posledni_start FROM online_restia');
    if ($resAktualizace instanceof mysqli_result) {
        $rowAktualizace = $resAktualizace->fetch_assoc();
        $posledniStart = trim((string)($rowAktualizace['posledni_start'] ?? ''));
        $resAktualizace->free();
        if ($posledniStart !== '') {
            $dtAktualizace = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $posledniStart, new DateTimeZone('Europe/Prague'));
            if ($dtAktualizace instanceof DateTimeImmutable) {
                $aktualizaceDoText = $dtAktualizace->format('H:i');
            }
        }
    }
    if ($aktualizaceDoText === '') {
        $aktualizaceDoText = (new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague')))->format('H:i');
    }

    $branchWhere = ' WHERE p.restia_activePosId IS NOT NULL AND p.restia_activePosId <> ""';
    $ordersWhereCa = ' WHERE ca.cas_vytvor >= ? AND ca.cas_vytvor <= ?';
    $ordersWhereCreated = ' WHERE ca.cas_vytvor IS NULL AND o.restia_created_at >= ? AND o.restia_created_at <= ?';
    $ordersWhereImported = ' WHERE ca.cas_vytvor IS NULL AND o.restia_created_at IS NULL AND o.restia_imported_at >= ? AND o.restia_imported_at <= ?';

    if ($selectedPob !== []) {
        $branchWhere .= ' AND p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
        $branchFilter = ' AND o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
        $ordersWhereCa .= $branchFilter;
        $ordersWhereCreated .= $branchFilter;
        $ordersWhereImported .= $branchFilter;
    }

    $branches = [];
    $branchSql = '
        SELECT p.id_pob, p.nazev, p.pob_color
        FROM pobocka p
        ' . $branchWhere . '
        ORDER BY p.id_pob ASC
    ';
    $branchRes = $conn->query($branchSql);
    if ($branchRes instanceof mysqli_result) {
        while ($row = $branchRes->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $nazev = trim((string)($row['nazev'] ?? ''));
            $barva = trim((string)($row['pob_color'] ?? ''));
            if ($idPob > 0 && $nazev !== '') {
                $branches[$idPob] = [
                    'id_pob' => $idPob,
                    'nazev' => $nazev,
                    'barva' => $barva,
                    'dokonceno' => 0,
                    'na_ceste' => 0,
                    'vyrabi_se' => 0,
                    'objednavky' => 0,
                    'trzba' => 0.0,
                ];
            }
        }
        $branchRes->free();
    }

    $countsSql = '
        SELECT
            zdroj.id_pob,
            SUM(zdroj.dokonceno) AS dokonceno,
            SUM(zdroj.na_ceste) AS na_ceste,
            SUM(zdroj.vyrabi_se) AS vyrabi_se
        FROM (
            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
            FROM objednavky_restia o
            INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
            ' . $ordersWhereCa . '

            UNION ALL

            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
            FROM objednavky_restia o
            INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
            ' . $ordersWhereCreated . '

            UNION ALL

            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
            FROM objednavky_restia o
            LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
            ' . $ordersWhereImported . '
        ) AS zdroj
        GROUP BY zdroj.id_pob
    ';

    $stmtCounts = $conn->prepare($countsSql);
    if ($stmtCounts === false) {
        throw new RuntimeException('Nepodarilo se pripravit data pro K19.');
    }
    $fromTs = (string)$range['from'];
    $toTs = (string)$range['to'];
    $stmtCounts->bind_param('ssssss', $fromTs, $toTs, $fromTs, $toTs, $fromTs, $toTs);
    $stmtCounts->execute();
    $resCounts = $stmtCounts->get_result();
    if ($resCounts instanceof mysqli_result) {
        while ($row = $resCounts->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if (!isset($branches[$idPob])) {
                continue;
            }
            $branches[$idPob]['dokonceno'] = (int)($row['dokonceno'] ?? 0);
            $branches[$idPob]['na_ceste'] = (int)($row['na_ceste'] ?? 0);
            $branches[$idPob]['vyrabi_se'] = (int)($row['vyrabi_se'] ?? 0);
        }
        $resCounts->free();
    }
    $stmtCounts->close();

    $g1Where = ' WHERE o.restia_created_at IS NOT NULL AND o.restia_created_at >= ? AND o.restia_created_at < ?';
    if ($selectedPob !== []) {
        $g1Where .= ' AND o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
    }

    $g1Sql = '
        SELECT
            o.id_pob,
            COUNT(*) AS objednavky,
            SUM(COALESCE(c.cena_celk, 0)) AS trzba
        FROM objednavky_restia o
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        ' . $g1Where . '
        GROUP BY o.id_pob
    ';

    $stmtG1 = $conn->prepare($g1Sql);
    if ($stmtG1 === false) {
        throw new RuntimeException('Nepodarilo se pripravit graf G1 pro K19.');
    }
    $g1FromTs = (string)$range['from'];
    $g1ToTsExclusive = (new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague')))
        ->modify('+1 second')
        ->format('Y-m-d H:i:s');
    $stmtG1->bind_param('ss', $g1FromTs, $g1ToTsExclusive);
    $stmtG1->execute();
    $resG1 = $stmtG1->get_result();
    if ($resG1 instanceof mysqli_result) {
        while ($row = $resG1->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if (!isset($branches[$idPob])) {
                continue;
            }
            $branches[$idPob]['objednavky'] = (int)($row['objednavky'] ?? 0);
            $branches[$idPob]['trzba'] = (float)($row['trzba'] ?? 0);
        }
        $resG1->free();
    }
    $stmtG1->close();

    $prevRangeFrom = (new DateTimeImmutable((string)$range['from'], new DateTimeZone('Europe/Prague')))
        ->modify('-7 days');
    $prevRangeToExclusive = (new DateTimeImmutable($g1ToTsExclusive, new DateTimeZone('Europe/Prague')))
        ->modify('-7 days');

    $g2Sql = '
        SELECT
            o.id_pob,
            COUNT(*) AS objednavky,
            SUM(COALESCE(c.cena_celk, 0)) AS trzba
        FROM objednavky_restia o
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        ' . $g1Where . '
        GROUP BY o.id_pob
    ';

    $stmtG2 = $conn->prepare($g2Sql);
    if ($stmtG2 === false) {
        throw new RuntimeException('Nepodarilo se pripravit graf G2 pro K19.');
    }
    $g2FromTs = $prevRangeFrom->format('Y-m-d H:i:s');
    $g2ToTsExclusive = $prevRangeToExclusive->format('Y-m-d H:i:s');
    $stmtG2->bind_param('ss', $g2FromTs, $g2ToTsExclusive);
    $stmtG2->execute();
    $resG2 = $stmtG2->get_result();
    $prevWeekCountsByPob = [];
    $prevWeekSalesByPob = [];
    if ($resG2 instanceof mysqli_result) {
        while ($row = $resG2->fetch_assoc()) {
            $prevWeekCountsByPob[(int)($row['id_pob'] ?? 0)] = (int)($row['objednavky'] ?? 0);
            $prevWeekSalesByPob[(int)($row['id_pob'] ?? 0)] = (float)($row['trzba'] ?? 0);
        }
        $resG2->free();
    }
    $stmtG2->close();

    $labels = [];
    $dokoncenoData = [];
    $naCesteData = [];
    $vyrabiSeData = [];
    $barvyPobocek = [];
    $objednavkyData = [];
    $trzbaData = [];
    $prumerCenaData = [];
    $rozdilData = [];
    $rozdilTrzbaData = [];
    $sumObjednavky = 0;
    $sumTrzba = 0.0;
    $sumDokonceno = 0;
    $sumNaCeste = 0;
    $sumVyrabiSe = 0;

    foreach ($branches as $branch) {
        $labels[] = (string)$branch['nazev'];
        $barvyPobocek[] = (string)$branch['barva'];
        $dokonceno = (int)$branch['dokonceno'];
        $naCeste = (int)$branch['na_ceste'];
        $vyrabiSe = (int)$branch['vyrabi_se'];
        $objednavky = (int)$branch['objednavky'];
        $trzba = (float)$branch['trzba'];
        $prumerCena = ($objednavky > 0) ? ($trzba / $objednavky) : 0.0;
        $rozdilObjednavek = $objednavky - (int)($prevWeekCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $rozdilTrzby = $trzba - (float)($prevWeekSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);

        $objednavkyData[] = $objednavky;
        $trzbaData[] = $trzba;
        $prumerCenaData[] = $prumerCena;
        $rozdilData[] = $rozdilObjednavek;
        $rozdilTrzbaData[] = $rozdilTrzby;
        $dokoncenoData[] = $dokonceno;
        $naCesteData[] = $naCeste;
        $vyrabiSeData[] = $vyrabiSe;

        $sumDokonceno += $dokonceno;
        $sumNaCeste += $naCeste;
        $sumVyrabiSe += $vyrabiSe;
        $sumObjednavky += $objednavky;
        $sumTrzba += $trzba;
    }

    $chartId = 'k19-online-chart';
    $payload = [
        'kind' => 'online_stavy',
        'labels' => $labels,
        'dokonceno' => $dokoncenoData,
        'na_ceste' => $naCesteData,
        'vyrabi_se' => $vyrabiSeData,
        'colors' => $barvyPobocek,
    ];
    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    if (!is_string($payloadJson) || $payloadJson === '') {
        throw new RuntimeException('Nepodarilo se pripravit graf pro K19.');
    }

    $g1Payload = [
        'kind' => 'bar_dual',
        'labels' => $labels,
        'orders' => $objednavkyData,
        'sales' => $trzbaData,
        'avg_price' => $prumerCenaData,
        'colors' => $barvyPobocek,
        'legend_orders' => 'Obj.',
        'legend_sales' => 'Tržba',
    ];
    $g1PayloadJson = json_encode(
        $g1Payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    if (!is_string($g1PayloadJson) || $g1PayloadJson === '') {
        throw new RuntimeException('Nepodarilo se pripravit graf G1 pro K19.');
    }

    $k19G1MaxTileHtml = cb_k19_render_max_chart_tile('G1', 'Objednávky a tržba dnes', (string)$range['label'], 'k19-max-g1-chart', $g1PayloadJson);

    $g2Payload = [
        'kind' => 'bar_dual', // přesně jako u G1, aby JS zpracoval graf správně
        'labels' => $labels,
        'orders' => $rozdilData,     // pouze rozdílová data, klíče zachovány
        'sales' => $rozdilTrzbaData,
        'colors' => $barvyPobocek,
        'legend_orders' => 'Objednávky',
        'legend_sales' => 'Tržba',
    ];
    $g2PayloadJson = json_encode(
        $g2Payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    if (!is_string($g2PayloadJson) || $g2PayloadJson === '') {
        throw new RuntimeException('Nepodarilo se pripravit graf G2 pro K19.');
    }

    $g2PeriodText = 'Časový interval 8:00 až ' . $aktualizaceDoText;
    $k19G2MaxTileHtml = cb_k19_render_max_chart_tile(
        'G2',
        'Porovnání dnešek vs minulý týden',
        $g2PeriodText,
        'k19-max-g2-chart',
        $g2PayloadJson
    );

    ob_start();
    ?>
    <div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">
      <script type="application/json" data-cb-prehledy-grafy-data><?= $payloadJson ?></script>

      <div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">
        <span><?= h((string)$range['label']) ?></span>
        <span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">
          <span><strong style="color:#16a34a;"><?= h((string)$sumDokonceno) ?></strong> dokončeno</span>
          <span><strong style="color:#f59e0b;"><?= h((string)$sumNaCeste) ?></strong> na cestě</span>
          <span><strong style="color:#dc2626;"><?= h((string)$sumVyrabiSe) ?></strong> vyrábí se</span>
        </span>
      </div>

      <div id="<?= h($chartId) ?>" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:180px;"></div>
    </div>
    <?php
    $card_min_html = (string)ob_get_clean();

    if (($cbDashboardRenderMode ?? '') === 'mini') {
        return;
    }
} catch (Throwable $e) {
    $card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Online objednavky se nepodarilo nacist.</p>';

    if (($cbDashboardRenderMode ?? '') === 'mini') {
        return;
    }
}


ob_start();
?>
<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">
  <script type="application/json" data-cb-prehledy-grafy-data><?= $payloadJson ?? '{}' ?></script>
  <div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:repeat(2, minmax(0, 1fr)); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:stretch;">
    <?= $k19G1MaxTileHtml ?>
    <?= $k19G2MaxTileHtml ?>
    <?= cb_k19_render_max_tile('G3') ?>
    <?= cb_k19_render_max_tile('G4') ?>
    <?= cb_k19_render_max_tile('G5') ?>
    <?= cb_k19_render_max_tile('G6') ?>
  </div>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/objednavky_online.php * Verze: V5 * Aktualizace: 29.04.2026 */
?>
