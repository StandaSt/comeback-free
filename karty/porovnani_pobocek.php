<?php
// jednoducha karta – od nuly

$nazvy_pobocek = [];
$hodnoty_pobocek = [];

require_once __DIR__ . '/../db/db_connect.php';
$pdo = db_connect();
if (method_exists($pdo, 'set_charset')) {
    $pdo->set_charset('utf8mb4');
}

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v > 0));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

$periodOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));
if ($periodOd === '' || $periodDo === '') {
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$safeOd = $pdo->real_escape_string($periodOd);
$safeDo = $pdo->real_escape_string($periodDo);
$pobWhere = '';
if ($selectedPob !== []) {
    $pobWhere = 'WHERE p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$sql = '
    SELECT
        p.id_pob,
        p.nazev,
        COALESCE(x.cnt, 0) AS cnt
    FROM pobocka p
    LEFT JOIN (
        SELECT
            o.id_pob,
            COUNT(*) AS cnt
        FROM objednavky_restia o
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        WHERE COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) >= "' . $safeOd . '"
          AND COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) <= "' . $safeDo . '"
        GROUP BY o.id_pob
    ) x ON x.id_pob = p.id_pob
    ' . $pobWhere . '
    ORDER BY p.id_pob
';

$stmt = $pdo->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($radek = $stmt->fetch_assoc()) {
        $nazev = trim((string)($radek['nazev'] ?? ''));
        $cnt = (int)($radek['cnt'] ?? 0);
        if ($nazev === '') {
            $nazev = (string)($radek['id_pob'] ?? '');
        }
        $nazvy_pobocek[] = $nazev;
        $hodnoty_pobocek[] = $cnt;
    }
    $stmt->free();
}
?>

<div style="width:100%;">
    <!-- MINI -->
    <div id="mini_graf" style="width:100%; height:200px;"></div>

    <!-- MAX -->
    <div style="display:none;">
        Zde bude max graf
    </div>
</div>

<script>
(function () {
    function vykresliMiniGraf(pokus) {
        var chartDom = document.getElementById('mini_graf');

        if (!chartDom || typeof echarts === 'undefined') {
            if (pokus < 20) {
                setTimeout(function () {
                    vykresliMiniGraf(pokus + 1);
                }, 50);
            }
            return;
        }

        var oldChart = echarts.getInstanceByDom(chartDom);
        if (oldChart) {
            oldChart.dispose();
        }

        var myChart = echarts.init(chartDom);

        var option = {
            grid: {
                left: 10,
                right: 10,
                top: 10,
                bottom: 40,
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: <?= json_encode($nazvy_pobocek, JSON_UNESCAPED_UNICODE) ?>,
                axisLabel: {
                    interval: 0
                }
            },
            yAxis: {
                type: 'value'
            },
            series: [{
                data: <?= json_encode($hodnoty_pobocek) ?>,
                type: 'bar'
            }]
        };

        myChart.setOption(option);
    }

    vykresliMiniGraf(0);
})();
</script>
