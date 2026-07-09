<?php
// K12
// jednoducha karta - od nuly

declare(strict_types=1);

require_once __DIR__ . '/../db/db_connect.php';

$card_min_html = '';
$card_max_html = '';

$db = db_connect();
if (method_exists($db, 'set_charset')) {
    $db->set_charset('utf8mb4');
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
    $today = (new DateTimeImmutable('today'))->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$periodOdDate = new DateTimeImmutable($periodOd);
$periodDoDate = new DateTimeImmutable($periodDo);
$periodOdTs = $periodOdDate;
$periodDoExclusive = $periodDoDate;
$titleOd = $periodOdDate->format('j.n.Y G:i');
$titleDo = $periodDoDate->format('j.n.Y G:i');
$periodLabel = $titleOd . ' - ' . $titleDo;

$safeOdTs = $db->real_escape_string($periodOdTs->format('Y-m-d H:i:s'));
$safeDoTsExclusive = $db->real_escape_string($periodDoExclusive->format('Y-m-d H:i:s'));
$selectedPobSql = $selectedPob !== [] ? implode(',', array_map('intval', $selectedPob)) : '';

$pobWhere = '';
if ($selectedPob !== []) {
    $pobWhere = 'WHERE p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$branchesSql = '
    SELECT p.id_pob, p.nazev, p.pob_color
    FROM pobocka p
    ' . $pobWhere . '
    ORDER BY p.id_pob
';

$grafPolozky = [];
$branchOrder = [];
$stmtBranches = $db->query($branchesSql);
if ($stmtBranches instanceof mysqli_result) {
    while ($row = $stmtBranches->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $nazev = trim((string)($row['nazev'] ?? ''));
        $barva = trim((string)($row['pob_color'] ?? ''));
        if ($idPob <= 0) {
            continue;
        }
        if ($nazev === '') {
            $nazev = (string)$idPob;
        }
        if ($barva === '') {
            throw new RuntimeException('Chybí barva pro pobočku s id_pob=' . $idPob . '.');
        }

        $grafPolozky[$idPob] = [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'barva' => $barva,
        ];
        $branchOrder[] = $idPob;
    }
    $stmtBranches->free();
}

$renderGrafRoot = static function (string $bodyHtml, string $rootJson = ''): string {
    $dataScript = '';
    if ($rootJson !== '') {
        $dataScript = '<script type="application/json" data-cb-prehledy-grafy-data>' . $rootJson . '</script>';
    }

    return ''
        . '<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">'
        . $dataScript
        . $bodyHtml
        . '</div>';
};

$renderGrafTile = static function (string $code, string $title, string $periodText, string $chartId, string $chartJson = '', string $chartStyleExtra = '', string $grafKey = ''): string {
    $payloadAttr = '';
    if ($chartJson !== '') {
        $payloadAttr = ' data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '"';
    }

    $grafAttrs = ' data-cb-graf-tile="1" data-cb-graf-code="' . h($code) . '"';
    if ($grafKey !== '') {
        $grafAttrs .= ' data-cb-graf-key="' . h($grafKey) . '"';
    }

    return ''
        . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;"' . $grafAttrs . '>'
        . '<div class="odstup_spod_4">'
        . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) 36px; align-items:start; column-gap:8px; line-height:1.15;">'
        . '<div class="card_text text_12" style="color:var(--clr_seda_3);">' . h($code) . '</div>'
        . '<div class="card_text txt_c"><strong>' . h($title) . '</strong></div>'
        . '<div></div>'
        . '</div>'
        . '<div class="card_text txt_seda text_12 txt_c" style="line-height:1.15;">' . h($periodText) . '</div>'
        . '</div>'
        . '<div id="' . h($chartId) . '" data-cb-prehledy-grafy-chart="1"' . $payloadAttr . ' class="sirka100" style="height:460px;' . h(trim($chartStyleExtra)) . '"></div>'
        . '</div>';
};

$jsonEncode = static function (array $payload, string $errorMessage): string {
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
};

$cbGrafContext = [
    'db' => $db,
    'selectedPob' => $selectedPob,
    'selectedPobSql' => $selectedPobSql,
    'periodOdDate' => $periodOdDate,
    'periodDoDate' => $periodDoDate,
    'safeOdTs' => $safeOdTs,
    'safeDoTsExclusive' => $safeDoTsExclusive,
    'periodLabel' => $periodLabel,
    'grafPolozky' => $grafPolozky,
    'branchOrder' => $branchOrder,
];

$renderGrafFile = static function (string $file) use ($cbGrafContext, $renderGrafTile, $jsonEncode): string {
    $path = __DIR__ . '/../grafy/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException('Soubor grafu neexistuje: ' . $file);
    }

    $html = require $path;
    if (!is_string($html)) {
        throw new RuntimeException('Soubor grafu nevrátil HTML: ' . $file);
    }

    return $html;
};

if (($cbDashboardRenderMode ?? '') === 'mini') {
    $card_min_html = $renderGrafRoot($renderGrafFile('g0_pocet_objednavek_pobocky.php'));

    return;
}

$grafyK12 = [
    'g1_trend_objednavek.php',
    'g2_vytizenost_pobocek.php',
    'g3_typ_zakaznika.php',
    'g4_trzby_mesic.php',
];

$maxTiles = '';
foreach ($grafyK12 as $grafSoubor) {
    $maxTiles .= $renderGrafFile($grafSoubor);
}

$card_max_html = $renderGrafRoot(
    '<div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-auto-rows:calc((100% - 10px) / 2); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:start; overflow-y:auto;">'
    . $maxTiles
    . '</div>'
);
