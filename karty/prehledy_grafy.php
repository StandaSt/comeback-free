<?php
// K12
// jednoducha karta - od nuly

declare(strict_types=1);

require_once __DIR__ . '/../db/db_connect.php';

$card_min_html = '';
$card_max_html = '';

$grafPolozky = [];
$nazvy_pobocek = [];
$hodnoty_pobocek = [];

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
        p.pob_color,
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
        $idPob = (int)($radek['id_pob'] ?? 0);
        $nazev = trim((string)($radek['nazev'] ?? ''));
        $barva = trim((string)($radek['pob_color'] ?? ''));
        $cnt = (int)($radek['cnt'] ?? 0);
        if ($nazev === '') {
            $nazev = (string)$idPob;
        }
        if ($barva === '') {
            throw new RuntimeException('Chybi barva pro pobočku s id_pob=' . $idPob . '.');
        }

        $grafPolozky[] = [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'hodnota' => $cnt,
            'barva' => $barva,
        ];
        $nazvy_pobocek[] = $nazev;
        $hodnoty_pobocek[] = $cnt;
    }
    $stmt->free();
}

$grafPayload = [
    'kind' => 'bar',
    'labels' => $nazvy_pobocek,
    'values' => $hodnoty_pobocek,
    'colors' => array_map(static fn(array $item): string => (string)$item['barva'], $grafPolozky),
];

$grafJson = json_encode(
    $grafPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($grafJson) || $grafJson === '') {
    throw new RuntimeException('Nepodarilo se pripravit data pro graf.');
}

$renderGrafTile = static function (string $title, string $chartId) use ($grafJson): string {
    $titleEsc = h($title);
    $idEsc = h($chartId);

    return ''
        . '<section class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8" style="display:flex; flex-direction:column; min-width:0; min-height:0; height:100%;">'
        . '<div class="card_text txt_tucne odstup_spod_4">' . $titleEsc . '</div>'
        . '<div id="' . $idEsc . '" data-cb-prehledy-grafy-chart="1" style="width:100%; flex:1 1 auto; min-height:0;"></div>'
        . '</section>';
};

$renderGrafRoot = static function (string $bodyHtml) use ($grafJson): string {
    return ''
        . '<div style="width:100%; height:100%; min-height:0; display:flex; flex-direction:column;" data-cb-prehledy-grafy="1">'
        . '<script type="application/json" data-cb-prehledy-grafy-data>' . $grafJson . '</script>'
        . $bodyHtml
        . '</div>';
};

$card_min_html = $renderGrafRoot(
    '<div style="width:100%;"><div id="mini_graf" data-cb-prehledy-grafy-chart="1" style="width:100%; height:200px;"></div></div>'
);

$maxTiles = '';
for ($i = 1; $i <= 6; $i++) {
    $maxTiles .= $renderGrafTile('Graf ' . $i, 'graf_max_' . $i);
}

$card_max_html = $renderGrafRoot(
    '<div style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:repeat(2, minmax(0, 1fr)); gap:10px; width:100%; height:100%; min-height:0; flex:1 1 auto;">'
    . $maxTiles
    . '</div>'
);

/* karty/prehledy_grafy.php * Verze: V2 * Aktualizace: 17.04.2026 */
?>
