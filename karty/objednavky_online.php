<?php
// K19
// karty/objednavky_online.php * Verze: V5 * Aktualizace: 29.04.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Data online objednavek nejsou k dispozici.</p>';
$card_max_html = '';
$subtitleMin = '';

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
            . '<div id="' . h($chartId) . '" data-cb-prehledy-grafy-chart="1" data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '" class="sirka100" style="height:100%; min-height:0; flex:1 1 auto;"></div>'
            . '</div>';
    }
}

if (!function_exists('cb_k19_render_chart_root')) {
    function cb_k19_render_chart_root(string $bodyHtml, string $rootJson = ''): string
    {
        $dataScript = '';
        if ($rootJson !== '') {
            $dataScript = '<script type="application/json" data-cb-prehledy-grafy-data>' . $rootJson . '</script>';
        }

        return ''
            . '<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">'
            . $dataScript
            . $bodyHtml
            . '</div>';
    }
}

if (!function_exists('cb_k19_chart_json')) {
    function cb_k19_chart_json(array $payload, string $errorMessage): string
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );
        if (!is_string($json) || $json === '') {
            throw new RuntimeException($errorMessage);
        }

        return $json;
    }
}

if (!function_exists('cb_k19_online_workday_range')) {
    function cb_k19_online_workday_range(): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $now = new DateTimeImmutable('now', $tz);
        $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 06:00:00', $tz);
        if (!($todayStart instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se urcit pracovni den pro K19.');
        }

        $workdayStart = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;

        return [
            'from' => $workdayStart->format('Y-m-d H:i:s'),
            'to' => $now->format('Y-m-d H:i:s'),
            'label' => $workdayStart->format('j.n.Y G:i') . ' - ' . $now->format('G:i'),
        ];
    }
}
$k19G1MaxTileHtml = cb_k19_render_max_tile('G1');
$k19G2MaxTileHtml = cb_k19_render_max_tile('G2');
$k19G5MaxTileHtml = cb_k19_render_max_tile('G5');
$k19G6MaxTileHtml = cb_k19_render_max_tile('G6');

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $range = cb_k19_online_workday_range();
    $aktualizaceDoText = '';
    $aktualizaceSubtitleText = '';

    $resAktualizace = $conn->query('SELECT MAX(konec) AS posledni_konec FROM online_restia');
    if ($resAktualizace instanceof mysqli_result) {
        $rowAktualizace = $resAktualizace->fetch_assoc();
        $posledniKonec = trim((string)($rowAktualizace['posledni_konec'] ?? ''));
        $resAktualizace->free();
        if ($posledniKonec !== '') {
            $dtAktualizace = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $posledniKonec, new DateTimeZone('Europe/Prague'));
            if ($dtAktualizace instanceof DateTimeImmutable) {
                $aktualizaceDoText = $dtAktualizace->format('G:i');
                $aktualizaceSubtitleText = $dtAktualizace->format('G:i:s');
            }
        }
    }
    if ($aktualizaceDoText === '') {
        $aktualizaceDoText = (new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague')))->format('G:i');
    }
    if ($aktualizaceSubtitleText !== '') {
        $subtitleMin = 'Aktualizace: ' . $aktualizaceSubtitleText;
    }

    $branchWhere = ' WHERE p.restia_activePosId IS NOT NULL AND p.restia_activePosId <> ""';
    $ordersWhereCa = ' WHERE ca.cas_vytvor >= ? AND ca.cas_vytvor <= ?';
    $ordersWhereCreated = ' WHERE ca.cas_vytvor IS NULL AND o.restia_created_at >= ? AND o.restia_created_at <= ?';
    $ordersWhereImported = ' WHERE ca.cas_vytvor IS NULL AND o.restia_created_at IS NULL AND o.restia_imported_at >= ? AND o.restia_imported_at <= ?';


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
                    'osobni_odber' => 0,
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
            SUM(zdroj.osobni_odber) AS osobni_odber,
            SUM(zdroj.vyrabi_se) AS vyrabi_se
        FROM (
            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND (
                    EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM obj_sluzba s WHERE s.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'external-delivery\')
                ) THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj) AND EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'pickup\') THEN 1 ELSE 0 END AS osobni_odber,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
            FROM objednavky_restia o
            INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
            ' . $ordersWhereCa . '

            UNION ALL

            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND (
                    EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM obj_sluzba s WHERE s.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'external-delivery\')
                ) THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj) AND EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'pickup\') THEN 1 ELSE 0 END AS osobni_odber,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
            FROM objednavky_restia o
            INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
            ' . $ordersWhereCreated . '

            UNION ALL

            SELECT
                o.id_pob,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END AS dokonceno,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND (
                    EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM obj_sluzba s WHERE s.id_obj = o.id_obj)
                    OR EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'external-delivery\')
                ) THEN 1 ELSE 0 END AS na_ceste,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL AND NOT EXISTS (SELECT 1 FROM obj_kuryr k WHERE k.id_obj = o.id_obj) AND EXISTS (SELECT 1 FROM cis_doruceni d WHERE d.id_doruceni = o.id_doruceni AND d.nazev = \'pickup\') THEN 1 ELSE 0 END AS osobni_odber,
                CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NULL THEN 1 ELSE 0 END AS vyrabi_se
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
            $branches[$idPob]['osobni_odber'] = (int)($row['osobni_odber'] ?? 0);
            $branches[$idPob]['vyrabi_se'] = (int)($row['vyrabi_se'] ?? 0);
        }
        $resCounts->free();
    }
    $stmtCounts->close();

    $g1Where = ' WHERE o.restia_created_at IS NOT NULL AND o.restia_created_at >= ? AND o.restia_created_at < ?';

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

    $labels = [];
    $dokoncenoData = [];
    $naCesteData = [];
    $osobniOdberData = [];
    $vyrabiSeData = [];
    $miniObjednavkyData = [];
    $miniTrzbaData = [];
    $barvyPobocek = [];
    $sumDokonceno = 0;
    $sumNaCeste = 0;
    $sumOsobniOdber = 0;
    $sumVyrabiSe = 0;
    $sumObjednavkyMini = 0;
    $sumTrzbaMini = 0.0;

    foreach ($branches as $branch) {
        $labels[] = (string)$branch['nazev'];
        $barvyPobocek[] = (string)$branch['barva'];
        $dokonceno = (int)$branch['dokonceno'];
        $naCeste = (int)$branch['na_ceste'];
        $osobniOdber = (int)$branch['osobni_odber'];
        $vyrabiSe = (int)$branch['vyrabi_se'];

        $dokoncenoData[] = $dokonceno;
        $naCesteData[] = $naCeste;
        $osobniOdberData[] = $osobniOdber;
        $vyrabiSeData[] = $vyrabiSe;
        $miniObjednavkyData[] = (int)$branch['objednavky'];
        $miniTrzbaData[] = (float)$branch['trzba'];

        $sumDokonceno += $dokonceno;
        $sumNaCeste += $naCeste;
        $sumOsobniOdber += $osobniOdber;
        $sumVyrabiSe += $vyrabiSe;
        $sumObjednavkyMini += (int)$branch['objednavky'];
        $sumTrzbaMini += (float)$branch['trzba'];
    }

    $chartId = 'k19-online-chart';
    $payload = [
        'kind' => 'online_stavy',
        'labels' => $labels,
        'series' => [
            ['id' => 'dokonceno', 'name' => 'Dokončeno', 'data' => $dokoncenoData, 'colors' => $barvyPobocek],
            ['id' => 'na_ceste', 'name' => 'Na cestě', 'data' => $naCesteData],
            ['id' => 'osobni_odber', 'name' => 'Osobní odběr', 'data' => $osobniOdberData],
            ['id' => 'vyrabi_se', 'name' => 'Vyrábí se', 'data' => $vyrabiSeData],
            ['id' => 'objednavky', 'name' => 'Objednávky', 'data' => $miniObjednavkyData],
            ['id' => 'trzba', 'name' => 'Tržba', 'data' => $miniTrzbaData],
        ],
    ];
    $payloadJson = cb_k19_chart_json($payload, 'Nepodařilo se připravit graf pro K19.');

    ob_start();
    ?>
    <div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;" data-cb-tooltip-boundary="1">
      <div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">

        <span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">
          <span><strong style="color:#16a34a;"><?= h((string)$sumDokonceno) ?></strong> dokončeno</span>
          <span><strong style="color:#f59e0b;"><?= h((string)$sumNaCeste) ?></strong> na cestě</span>
          <span><strong style="color:#0ea5e9;"><?= h((string)$sumOsobniOdber) ?></strong> osobní odběr</span>
          <span><strong style="color:#dc2626;"><?= h((string)$sumVyrabiSe) ?></strong> vyrábí se</span>
        </span>
        <span class="cb_tooltip" tabindex="0" aria-label="Souhrn online objednávek" data-cb-tooltip-position="1">
          <span>detail</span>
          <span class="cb_tooltip_panel cb_tooltip_card" data-cb-tooltip-panel="1">
            <span class="cb_tooltip_title">Online objednávky podle poboček</span>
            <table class="cb_tooltip_table">
              <thead>
                <tr>
                  <th>Pobočka</th>
                  <th class="cb_tooltip_num">Dok.</th>
                  <th class="cb_tooltip_num">Cesta</th>
                  <th class="cb_tooltip_num">Odběr</th>
                  <th class="cb_tooltip_num">Výroba</th>
                  <th class="cb_tooltip_num">Obj.</th>
                  <th class="cb_tooltip_num">Tržba</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($branches as $branch): ?>
                  <tr>
                    <td><?= h((string)$branch['nazev']) ?></td>
                    <td class="cb_tooltip_num"><?= h((string)(int)$branch['dokonceno']) ?></td>
                    <td class="cb_tooltip_num"><?= h((string)(int)$branch['na_ceste']) ?></td>
                    <td class="cb_tooltip_num"><?= h((string)(int)$branch['osobni_odber']) ?></td>
                    <td class="cb_tooltip_num"><?= h((string)(int)$branch['vyrabi_se']) ?></td>
                    <td class="cb_tooltip_num"><?= h((string)(int)$branch['objednavky']) ?></td>
                    <td class="cb_tooltip_num"><?= h(number_format((float)$branch['trzba'], 0, ',', ' ')) ?> Kč</td>
                  </tr>
                <?php endforeach; ?>
                <tr>
                  <th>Celkem</th>
                  <th class="cb_tooltip_num"><?= h((string)$sumDokonceno) ?></th>
                  <th class="cb_tooltip_num"><?= h((string)$sumNaCeste) ?></th>
                  <th class="cb_tooltip_num"><?= h((string)$sumOsobniOdber) ?></th>
                  <th class="cb_tooltip_num"><?= h((string)$sumVyrabiSe) ?></th>
                  <th class="cb_tooltip_num"><?= h((string)$sumObjednavkyMini) ?></th>
                  <th class="cb_tooltip_num"><?= h(number_format($sumTrzbaMini, 0, ',', ' ')) ?> Kč</th>
                </tr>
              </tbody>
            </table>
          </span>
        </span>
      </div>

      <div id="<?= h($chartId) ?>" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:180px;"></div>
    </div>
    <?php
    $card_min_html = cb_k19_render_chart_root((string)ob_get_clean(), $payloadJson);

    if (($cbDashboardRenderMode ?? '') === 'mini') {
        return;
    }

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

    $g5Now = new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague'));
    $g5CurrentWeekMonday = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $g5Now->modify('monday this week')->format('Y-m-d') . ' 06:00:00', new DateTimeZone('Europe/Prague'));
    if (!($g5CurrentWeekMonday instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se pripravit aktualni tyden pro graf G5.');
    }
    $g5PreviousWeekMonday = $g5CurrentWeekMonday->modify('-7 days');
    $g5CurrentToTsExclusive = $g5Now->modify('+1 second')->format('Y-m-d H:i:s');
    $g5PreviousToTsExclusive = $g5Now->modify('-7 days')->modify('+1 second')->format('Y-m-d H:i:s');

    $stmtG5Current = $conn->prepare($g2Sql);
    if ($stmtG5Current === false) {
        throw new RuntimeException('Nepodarilo se pripravit aktualni data grafu G5 pro K19.');
    }
    $g5CurrentFromTs = $g5CurrentWeekMonday->format('Y-m-d H:i:s');
    $stmtG5Current->bind_param('ss', $g5CurrentFromTs, $g5CurrentToTsExclusive);
    $stmtG5Current->execute();
    $resG5Current = $stmtG5Current->get_result();
    $g5CurrentWeekCountsByPob = [];
    $g5CurrentWeekSalesByPob = [];
    if ($resG5Current instanceof mysqli_result) {
        while ($row = $resG5Current->fetch_assoc()) {
            $g5CurrentWeekCountsByPob[(int)($row['id_pob'] ?? 0)] = (int)($row['objednavky'] ?? 0);
            $g5CurrentWeekSalesByPob[(int)($row['id_pob'] ?? 0)] = (float)($row['trzba'] ?? 0);
        }
        $resG5Current->free();
    }
    $stmtG5Current->close();

    $stmtG5Previous = $conn->prepare($g2Sql);
    if ($stmtG5Previous === false) {
        throw new RuntimeException('Nepodarilo se pripravit predchozi data grafu G5 pro K19.');
    }
    $g5PreviousFromTs = $g5PreviousWeekMonday->format('Y-m-d H:i:s');
    $stmtG5Previous->bind_param('ss', $g5PreviousFromTs, $g5PreviousToTsExclusive);
    $stmtG5Previous->execute();
    $resG5Previous = $stmtG5Previous->get_result();
    $g5PreviousWeekCountsByPob = [];
    $g5PreviousWeekSalesByPob = [];
    if ($resG5Previous instanceof mysqli_result) {
        while ($row = $resG5Previous->fetch_assoc()) {
            $g5PreviousWeekCountsByPob[(int)($row['id_pob'] ?? 0)] = (int)($row['objednavky'] ?? 0);
            $g5PreviousWeekSalesByPob[(int)($row['id_pob'] ?? 0)] = (float)($row['trzba'] ?? 0);
        }
        $resG5Previous->free();
    }
    $stmtG5Previous->close();

    $g6Now = new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague'));
    $g6CurrentMonthStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $g6Now->format('Y-m-01') . ' 06:00:00', new DateTimeZone('Europe/Prague'));
    if (!($g6CurrentMonthStart instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se pripravit aktualni mesic pro graf G6.');
    }
    $g6PreviousMonthStart = $g6CurrentMonthStart->modify('first day of previous month');
    $g6CurrentToTsExclusive = $g6Now->modify('+1 second')->format('Y-m-d H:i:s');
    $g6CurrentDay = (int)$g6Now->format('j');
    $g6PreviousMonthLastDay = (int)$g6PreviousMonthStart->format('t');
    $g6PreviousEndDay = min($g6CurrentDay, $g6PreviousMonthLastDay);
    $g6PreviousPeriodEnd = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $g6PreviousMonthStart->format('Y-m-') . str_pad((string)$g6PreviousEndDay, 2, '0', STR_PAD_LEFT) . ' ' . $g6Now->format('H:i:s'),
        new DateTimeZone('Europe/Prague')
    );
    if (!($g6PreviousPeriodEnd instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se pripravit predchozi mesic pro graf G6.');
    }
    $g6PreviousToTsExclusive = $g6PreviousPeriodEnd->modify('+1 second')->format('Y-m-d H:i:s');

    $stmtG6Current = $conn->prepare($g2Sql);
    if ($stmtG6Current === false) {
        throw new RuntimeException('Nepodarilo se pripravit aktualni data grafu G6 pro K19.');
    }
    $g6CurrentFromTs = $g6CurrentMonthStart->format('Y-m-d H:i:s');
    $stmtG6Current->bind_param('ss', $g6CurrentFromTs, $g6CurrentToTsExclusive);
    $stmtG6Current->execute();
    $resG6Current = $stmtG6Current->get_result();
    $g6CurrentMonthCountsByPob = [];
    $g6CurrentMonthSalesByPob = [];
    if ($resG6Current instanceof mysqli_result) {
        while ($row = $resG6Current->fetch_assoc()) {
            $g6CurrentMonthCountsByPob[(int)($row['id_pob'] ?? 0)] = (int)($row['objednavky'] ?? 0);
            $g6CurrentMonthSalesByPob[(int)($row['id_pob'] ?? 0)] = (float)($row['trzba'] ?? 0);
        }
        $resG6Current->free();
    }
    $stmtG6Current->close();

    $stmtG6Previous = $conn->prepare($g2Sql);
    if ($stmtG6Previous === false) {
        throw new RuntimeException('Nepodarilo se pripravit predchozi data grafu G6 pro K19.');
    }
    $g6PreviousFromTs = $g6PreviousMonthStart->format('Y-m-d H:i:s');
    $stmtG6Previous->bind_param('ss', $g6PreviousFromTs, $g6PreviousToTsExclusive);
    $stmtG6Previous->execute();
    $resG6Previous = $stmtG6Previous->get_result();
    $g6PreviousMonthCountsByPob = [];
    $g6PreviousMonthSalesByPob = [];
    if ($resG6Previous instanceof mysqli_result) {
        while ($row = $resG6Previous->fetch_assoc()) {
            $g6PreviousMonthCountsByPob[(int)($row['id_pob'] ?? 0)] = (int)($row['objednavky'] ?? 0);
            $g6PreviousMonthSalesByPob[(int)($row['id_pob'] ?? 0)] = (float)($row['trzba'] ?? 0);
        }
        $resG6Previous->free();
    }
    $stmtG6Previous->close();

    $labels = [];
    $dokoncenoData = [];
    $naCesteData = [];
    $osobniOdberData = [];
    $vyrabiSeData = [];
    $miniObjednavkyData = [];
    $miniTrzbaData = [];
    $barvyPobocek = [];
    $objednavkyData = [];
    $trzbaData = [];
    $prumerCenaData = [];
    $rozdilData = [];
    $rozdilTrzbaData = [];
    $g5RozdilData = [];
    $g5RozdilTrzbaData = [];
    $g6RozdilData = [];
    $g6RozdilTrzbaData = [];
    $sumObjednavky = 0;
    $sumTrzba = 0.0;
    $sumDokonceno = 0;
    $sumNaCeste = 0;
    $sumOsobniOdber = 0;
    $sumVyrabiSe = 0;

    foreach ($branches as $branch) {
        $labels[] = (string)$branch['nazev'];
        $barvyPobocek[] = (string)$branch['barva'];
        $dokonceno = (int)$branch['dokonceno'];
        $naCeste = (int)$branch['na_ceste'];
        $osobniOdber = (int)$branch['osobni_odber'];
        $vyrabiSe = (int)$branch['vyrabi_se'];
        $objednavky = (int)$branch['objednavky'];
        $trzba = (float)$branch['trzba'];
        $prumerCena = ($objednavky > 0) ? ($trzba / $objednavky) : 0.0;
        $rozdilObjednavek = $objednavky - (int)($prevWeekCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $rozdilTrzby = $trzba - (float)($prevWeekSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);
        $g5CurrentObjednavky = (int)($g5CurrentWeekCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $g5CurrentTrzba = (float)($g5CurrentWeekSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);
        $g5PreviousObjednavky = (int)($g5PreviousWeekCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $g5PreviousTrzba = (float)($g5PreviousWeekSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);
        $g6CurrentObjednavky = (int)($g6CurrentMonthCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $g6CurrentTrzba = (float)($g6CurrentMonthSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);
        $g6PreviousObjednavky = (int)($g6PreviousMonthCountsByPob[(int)($branch['id_pob'] ?? 0)] ?? 0);
        $g6PreviousTrzba = (float)($g6PreviousMonthSalesByPob[(int)($branch['id_pob'] ?? 0)] ?? 0.0);

        $objednavkyData[] = $objednavky;
        $trzbaData[] = $trzba;
        $prumerCenaData[] = $prumerCena;
        $rozdilData[] = $rozdilObjednavek;
        $rozdilTrzbaData[] = $rozdilTrzby;
        $g5RozdilData[] = $g5CurrentObjednavky - $g5PreviousObjednavky;
        $g5RozdilTrzbaData[] = $g5CurrentTrzba - $g5PreviousTrzba;
        $g6RozdilData[] = $g6CurrentObjednavky - $g6PreviousObjednavky;
        $g6RozdilTrzbaData[] = $g6CurrentTrzba - $g6PreviousTrzba;
        $dokoncenoData[] = $dokonceno;
        $naCesteData[] = $naCeste;
        $osobniOdberData[] = $osobniOdber;
        $vyrabiSeData[] = $vyrabiSe;
        $miniObjednavkyData[] = $objednavky;
        $miniTrzbaData[] = $trzba;

        $sumDokonceno += $dokonceno;
        $sumNaCeste += $naCeste;
        $sumOsobniOdber += $osobniOdber;
        $sumVyrabiSe += $vyrabiSe;
        $sumObjednavky += $objednavky;
        $sumTrzba += $trzba;
    }

    $chartId = 'k19-online-chart';
    $payload = [
        'kind' => 'online_stavy',
        'labels' => $labels,
        'series' => [
            ['id' => 'dokonceno', 'name' => 'Dokončeno', 'data' => $dokoncenoData, 'colors' => $barvyPobocek],
            ['id' => 'na_ceste', 'name' => 'Na cestě', 'data' => $naCesteData],
            ['id' => 'osobni_odber', 'name' => 'Osobní odběr', 'data' => $osobniOdberData],
            ['id' => 'vyrabi_se', 'name' => 'Vyrábí se', 'data' => $vyrabiSeData],
            ['id' => 'objednavky', 'name' => 'Objednávky', 'data' => $miniObjednavkyData],
            ['id' => 'trzba', 'name' => 'Tržba', 'data' => $miniTrzbaData],
        ],
    ];
    $payloadJson = cb_k19_chart_json($payload, 'Nepodařilo se připravit graf pro K19.');

    $g1Payload = [
        'kind' => 'bar_dual',
        'labels' => $labels,
        'series' => [
            ['id' => 'orders', 'name' => 'Obj.', 'data' => $objednavkyData, 'colors' => $barvyPobocek],
            ['id' => 'sales', 'name' => 'Trzba', 'data' => $trzbaData],
            ['id' => 'avg_price', 'name' => 'Prumerna cena', 'data' => $prumerCenaData],
        ],
        'meta' => [
            'legend_orders' => 'Obj.',
            'legend_sales' => 'Trzba',
        ],
    ];
    $g1PayloadJson = cb_k19_chart_json($g1Payload, 'Nepodarilo se pripravit graf G1 pro K19.');

    $g1PeriodText = (new DateTimeImmutable((string)$range['from'], new DateTimeZone('Europe/Prague')))->format('j.n.Y G:i')
        . ' - '
        . $aktualizaceDoText;
    $k19G1MaxTileHtml = cb_k19_render_max_chart_tile('G1', 'Objednávky a tržba dnes', $g1PeriodText, 'k19-max-g1-chart', $g1PayloadJson);

    $g2Payload = [
        'kind' => 'bar_dual_diff_centered',
        'labels' => $labels,
        'series' => [
            ['id' => 'orders', 'name' => 'Objednavky', 'data' => $rozdilData, 'colors' => $barvyPobocek],
            ['id' => 'sales', 'name' => 'Trzba', 'data' => $rozdilTrzbaData],
        ],
        'meta' => [
            'legend_orders' => 'Objednavky',
            'legend_sales' => 'Trzba',
        ],
    ];
    $g2PayloadJson = cb_k19_chart_json($g2Payload, 'Nepodarilo se pripravit graf G2 pro K19.');

    $g2PeriodText = 'Časový interval 6:00 až ' . $aktualizaceDoText;
    $k19G2MaxTileHtml = cb_k19_render_max_chart_tile(
        'G2',
        'Porovnání dnešek vs minulý týden',
        $g2PeriodText,
        'k19-max-g2-chart',
        $g2PayloadJson
    );

    $g5Payload = [
        'kind' => 'bar_dual_diff_centered',
        'labels' => $labels,
        'series' => [
            ['id' => 'orders', 'name' => 'Objednavky', 'data' => $g5RozdilData, 'colors' => $barvyPobocek],
            ['id' => 'sales', 'name' => 'Trzba', 'data' => $g5RozdilTrzbaData],
        ],
        'meta' => [
            'legend_orders' => 'Objednavky',
            'legend_sales' => 'Trzba',
        ],
    ];
    $g5PayloadJson = cb_k19_chart_json($g5Payload, 'Nepodarilo se pripravit graf G5 pro K19.');

    $g5Now = new DateTimeImmutable((string)$range['to'], new DateTimeZone('Europe/Prague'));
    $g5CurrentWeekMonday = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $g5Now->modify('monday this week')->format('Y-m-d') . ' 06:00:00', new DateTimeZone('Europe/Prague'));
    $g5PreviousWeekMonday = $g5CurrentWeekMonday instanceof DateTimeImmutable
        ? $g5CurrentWeekMonday->modify('-7 days')
        : null;
    $g5PreviousWeekToday = $g5Now->modify('-7 days');
    $g5PeriodText = 'Období '
        . (($g5PreviousWeekMonday instanceof DateTimeImmutable) ? $g5PreviousWeekMonday->format('j.n.Y') : '')
        . ' 6:00 až '
        . $g5PreviousWeekToday->format('j.n.Y')
        . ' '
        . $aktualizaceDoText
        . ' vs '
        . (($g5CurrentWeekMonday instanceof DateTimeImmutable) ? $g5CurrentWeekMonday->format('j.n.Y') : '')
        . ' 6:00 až '
        . $g5Now->format('j.n.Y')
        . ' '
        . $aktualizaceDoText;
    $k19G5MaxTileHtml = cb_k19_render_max_chart_tile(
        'G5',
        'Porovnání tento týden vs předchozí týden',
        $g5PeriodText,
        'k19-max-g5-chart',
        $g5PayloadJson
    );

    $g6Payload = [
        'kind' => 'bar_dual_diff_centered',
        'labels' => $labels,
        'series' => [
            ['id' => 'orders', 'name' => 'Objednavky', 'data' => $g6RozdilData, 'colors' => $barvyPobocek],
            ['id' => 'sales', 'name' => 'Trzba', 'data' => $g6RozdilTrzbaData],
        ],
        'meta' => [
            'legend_orders' => 'Objednavky',
            'legend_sales' => 'Trzba',
        ],
    ];
    $g6PayloadJson = cb_k19_chart_json($g6Payload, 'Nepodarilo se pripravit graf G6 pro K19.');

    $g6PeriodText = 'Období '
        . $g6PreviousMonthStart->format('j.n.Y')
        . ' 6:00 až '
        . $g6PreviousPeriodEnd->format('j.n.Y')
        . ' '
        . $aktualizaceDoText
        . ' vs '
        . $g6CurrentMonthStart->format('j.n.Y')
        . ' 6:00 až '
        . $g6Now->format('j.n.Y')
        . ' '
        . $aktualizaceDoText;
    $k19G6MaxTileHtml = cb_k19_render_max_chart_tile(
        'G6',
        'Porovnání tento měsíc vs předchozí měsíc',
        $g6PeriodText,
        'k19-max-g6-chart',
        $g6PayloadJson
    );

    ob_start();
    ?>
    <div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;">
      <div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">
        
        <span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">
          <span><strong style="color:#16a34a;"><?= h((string)$sumDokonceno) ?></strong> dokončeno</span>
          <span><strong style="color:#f59e0b;"><?= h((string)$sumNaCeste) ?></strong> na cestě</span>
          <span><strong style="color:#0ea5e9;"><?= h((string)$sumOsobniOdber) ?></strong> osobní odběr</span>
          <span><strong style="color:#dc2626;"><?= h((string)$sumVyrabiSe) ?></strong> vyrábí se</span>
        </span>
      </div>

      <div id="<?= h($chartId) ?>" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:180px;"></div>
    </div>
    <?php
    $card_min_html = cb_k19_render_chart_root((string)ob_get_clean(), $payloadJson);

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
<div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:minmax(0, 1fr); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:stretch;">
  <?= $k19G2MaxTileHtml ?>
  <?= $k19G5MaxTileHtml ?>
  <?= $k19G6MaxTileHtml ?>
</div>
<?php
$card_max_html = cb_k19_render_chart_root((string)ob_get_clean());

/* karty/objednavky_online.php * Verze: V5 * Aktualizace: 29.04.2026 */
?>
