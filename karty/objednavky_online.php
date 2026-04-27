<?php
// K19
// karty/objednavky_online.php * Verze: V2 * Aktualizace: 27.04.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Data online objednavek nejsou k dispozici.</p>';

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

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $range = cb_k19_online_workday_range();
    $selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    $selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v > 0));

    $branchWhere = ' WHERE p.restia_activePosId IS NOT NULL AND p.restia_activePosId <> ""';
    $ordersWhere = ' WHERE COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) >= ? AND COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) <= ?';

    if ($selectedPob !== []) {
        $branchWhere .= ' AND p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
        $ordersWhere .= ' AND o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
    }

    $branches = [];
    $branchSql = '
        SELECT p.id_pob, p.nazev
        FROM pobocka p
        ' . $branchWhere . '
        ORDER BY p.id_pob ASC
    ';
    $branchRes = $conn->query($branchSql);
    if ($branchRes instanceof mysqli_result) {
        while ($row = $branchRes->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $nazev = trim((string)($row['nazev'] ?? ''));
            if ($idPob > 0 && $nazev !== '') {
                $branches[$idPob] = [
                    'id_pob' => $idPob,
                    'nazev' => $nazev,
                    'dokonceno' => 0,
                    'na_ceste' => 0,
                    'vyrabi_se' => 0,
                ];
            }
        }
        $branchRes->free();
    }

    $countsSql = '
        SELECT
            o.id_pob,
            SUM(CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NOT NULL THEN 1 ELSE 0 END) AS dokonceno,
            SUM(CASE WHEN COALESCE(ca.cas_doruc, ca.cas_uzavreni) IS NULL AND ca.cas_dokonc IS NOT NULL THEN 1 ELSE 0 END) AS na_ceste,
            SUM(CASE WHEN ca.cas_dokonc IS NULL THEN 1 ELSE 0 END) AS vyrabi_se
        FROM objednavky_restia o
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        ' . $ordersWhere . '
        GROUP BY o.id_pob
    ';

    $stmtCounts = $conn->prepare($countsSql);
    if ($stmtCounts === false) {
        throw new RuntimeException('Nepodarilo se pripravit data pro K19.');
    }
    $fromTs = (string)$range['from'];
    $toTs = (string)$range['to'];
    $stmtCounts->bind_param('ss', $fromTs, $toTs);
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

    $labels = [];
    $dokoncenoData = [];
    $naCesteData = [];
    $vyrabiSeData = [];
    $sumDokonceno = 0;
    $sumNaCeste = 0;
    $sumVyrabiSe = 0;

    foreach ($branches as $branch) {
        $labels[] = (string)$branch['nazev'];
        $dokonceno = (int)$branch['dokonceno'];
        $naCeste = (int)$branch['na_ceste'];
        $vyrabiSe = (int)$branch['vyrabi_se'];

        $dokoncenoData[] = $dokonceno;
        $naCesteData[] = $naCeste;
        $vyrabiSeData[] = $vyrabiSe;

        $sumDokonceno += $dokonceno;
        $sumNaCeste += $naCeste;
        $sumVyrabiSe += $vyrabiSe;
    }

    $chartId = 'k19-online-chart';
    $payload = [
        'labels' => $labels,
        'dokonceno' => $dokoncenoData,
        'na_ceste' => $naCesteData,
        'vyrabi_se' => $vyrabiSeData,
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

    ob_start();
    ?>
    <div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;">
      <div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">
        <span><?= h((string)$range['label']) ?></span>
        <span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">
          <span><strong style="color:#16a34a;"><?= h((string)$sumDokonceno) ?></strong> dokončeno</span>
          <span><strong style="color:#f59e0b;"><?= h((string)$sumNaCeste) ?></strong> na cestě</span>
          <span><strong style="color:#dc2626;"><?= h((string)$sumVyrabiSe) ?></strong> vyrábí se</span>
        </span>
      </div>

      <div id="<?= h($chartId) ?>" class="sirka100" style="flex:1 1 auto; min-height:210px; height:100%;"></div>
      <script type="application/json" id="<?= h($chartId) ?>-data"><?= $payloadJson ?></script>
      <script>
      (function () {
        const chartId = <?= json_encode($chartId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const dataId = chartId + '-data';

        function initK19OnlineChart(attempt) {
          const node = document.getElementById(chartId);
          const dataNode = document.getElementById(dataId);
          const echarts = window.echarts;

          if (!(node instanceof HTMLElement) || !(dataNode instanceof HTMLElement) || !echarts || typeof echarts.init !== 'function') {
            if ((attempt || 0) < 30) {
              window.setTimeout(function () {
                initK19OnlineChart((attempt || 0) + 1);
              }, 120);
            }
            return;
          }

          let payload = null;
          try {
            payload = JSON.parse(String(dataNode.textContent || '').trim());
          } catch (e) {
            payload = null;
          }
          if (!payload || typeof payload !== 'object') {
            return;
          }

          const labels = Array.isArray(payload.labels) ? payload.labels : [];
          const dokonceno = Array.isArray(payload.dokonceno) ? payload.dokonceno : [];
          const naCeste = Array.isArray(payload.na_ceste) ? payload.na_ceste : [];
          const vyrabiSe = Array.isArray(payload.vyrabi_se) ? payload.vyrabi_se : [];

          const existing = typeof echarts.getInstanceByDom === 'function' ? echarts.getInstanceByDom(node) : null;
          if (existing) {
            existing.dispose();
          }

          const chart = echarts.init(node);
          chart.setOption({
            grid: { left: 16, right: 8, top: 8, bottom: 58, containLabel: true },
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
            legend: { show: false },
            xAxis: {
              type: 'category',
              data: labels,
              axisTick: { show: false },
              axisLabel: { interval: 0, rotate: labels.length > 4 ? 32 : 0, fontSize: 10, lineHeight: 11 }
            },
            yAxis: {
              type: 'value',
              minInterval: 1,
              splitNumber: 4
            },
            series: [
              {
                name: 'Dokončeno',
                type: 'bar',
                stack: 'online',
                barMaxWidth: 36,
                itemStyle: { color: '#16a34a' },
                data: dokonceno
              },
              {
                name: 'Na cestě',
                type: 'bar',
                stack: 'online',
                barMaxWidth: 36,
                itemStyle: { color: '#f59e0b' },
                data: naCeste
              },
              {
                name: 'Vyrábí se',
                type: 'bar',
                stack: 'online',
                barMaxWidth: 36,
                itemStyle: { color: '#dc2626' },
                data: vyrabiSe
              }
            ]
          }, true);

          window.addEventListener('resize', function () {
            chart.resize();
          });
        }

        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function () {
            initK19OnlineChart(0);
          }, { once: true });
        } else {
          initK19OnlineChart(0);
        }
      })();
      </script>
    </div>
    <?php
    $card_min_html = (string)ob_get_clean();
} catch (Throwable $e) {
    $card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Online objednavky se nepodarilo nacist.</p>';
}

ob_start();
?>
<p class="card_text txt_seda odstup_vnejsi_0">Zde bude max obsah</p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/objednavky_online.php * Verze: V2 * Aktualizace: 27.04.2026 */
?>
