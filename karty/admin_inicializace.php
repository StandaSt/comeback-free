<?php
// karty/admin_inicializace.php * Verze: V13 * Aktualizace: 03.04.2026

declare(strict_types=1);

$cbRestiaCount = 0;
$cbSmenyCount = 0;
$cbReportCount = 0;

$cbRestiaDate = 'Ne';
$cbSmenyDate = 'Ne';
$cbReportDate = 'Ne';

$cbSmenyPlanMaData = false;
$cbReportMaData = false;
$cbRunSmenyPlan = (
    isset($_POST['run_smeny_plan']) && (string)$_POST['run_smeny_plan'] === '1'
);
$cbOpenSmenyPlan = (
    isset($_POST['open_smeny_plan']) && (string)$_POST['open_smeny_plan'] === '1'
);
$cbRunGoogleData = (
    isset($_POST['run_google_data']) && (string)$_POST['run_google_data'] === '1'
);
$cbOpenGoogleData = (
    isset($_POST['open_google_data']) && (string)$_POST['open_google_data'] === '1'
);
$cbRunRestia = (
    isset($_POST['run_restia_obj']) && (string)$_POST['run_restia_obj'] === '1'
);
$cbRunRestiaMenu = (
    isset($_POST['run_restia_menu']) && (string)$_POST['run_restia_menu'] === '1'
);
$cbOpenRestiaMenu = (
    isset($_POST['open_restia_menu']) && (string)$_POST['open_restia_menu'] === '1'
);
$cbBackAdminInit = (
    isset($_POST['back_admin_init']) && (string)$_POST['back_admin_init'] === '1'
);
$cbRestiaState = $_SESSION['cb_restia_hist_v4_state'] ?? null;
$cbKeepRestiaMax = false;
if (is_array($cbRestiaState)) {
    $cbKeepRestiaMax = (
        (int)($cbRestiaState['finished'] ?? 0) === 0
        && (
            (int)($cbRestiaState['auto_next'] ?? 0) === 1
            || (int)($cbRestiaState['waiting_continue'] ?? 0) === 1
        )
    );
}
if ($cbBackAdminInit) {
    unset($_SESSION['cb_restia_hist_v4_state'], $_SESSION['cb_restia_hist_v4_rows'], $_SESSION['cb_restia_hist_v4_msg']);
    $cbRunSmenyPlan = false;
    $cbOpenSmenyPlan = false;
    $cbRunGoogleData = false;
    $cbOpenGoogleData = false;
    $cbRunRestia = false;
    $cbRunRestiaMenu = false;
    $cbOpenRestiaMenu = false;
    $cbKeepRestiaMax = false;
}

$qRestia = db()->query('SELECT COALESCE(MAX(id_obj), 0) AS cnt, MAX(`import`) AS dt FROM objednavky_restia');
if ($qRestia instanceof mysqli_result) {
    $r = $qRestia->fetch_assoc();
    $cbRestiaCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbRestiaDate = ($dt !== '') ? $dt : 'Ne';
    $qRestia->free();
}

$qSmeny = db()->query('SELECT COUNT(*) AS cnt, MAX(created_at) AS dt FROM smeny_plan');
if ($qSmeny instanceof mysqli_result) {
    $r = $qSmeny->fetch_assoc();
    $cbSmenyCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbSmenyDate = ($dt !== '') ? $dt : 'Ne';
    $cbSmenyPlanMaData = ($cbSmenyCount > 0);
    $qSmeny->free();
}

$qReport = db()->query('SELECT COUNT(*) AS cnt, MAX(created_at) AS dt FROM smeny_report');
if ($qReport instanceof mysqli_result) {
    $r = $qReport->fetch_assoc();
    $cbReportCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbReportDate = ($dt !== '') ? $dt : 'Ne';
    $cbReportMaData = ($cbReportCount > 0);
    $qReport->free();
}

$cbScriptTables = [
    'plnime_smeny_plan.php' => [
        'smeny_aktualizace',
        'smeny_plan',
    ],
    'google_data.php' => [
        'smeny_report',
    ],
    'plnime_restia_objednavky.php' => [
        'api_restia',
        'cis_doruceni',
        'cis_obj_platforma',
        'cis_obj_platby',
        'cis_obj_stav',
        'obj_adresa',
        'obj_casy',
        'obj_ceny',
        'obj_import',
        'obj_kuryr',
        'obj_polozka_kds_tag',
        'obj_polozka_mod',
        'obj_polozky',
        'obj_raw',
        'obj_sluzba',
        'objednavky_restia',
        'zakaznik',
    ],
    'plnime_restia_menu.php' => [
        'api_restia',
        'res_alergen',
        'res_cena',
        'res_kategorie',
        'res_polozky',
    ],
];

$cbDb = db();
$cbScriptStats = [];
foreach ($cbScriptTables as $cbScriptName => $cbTables) {
    $cbRows = [];
    foreach ($cbTables as $cbTable) {
        $cbSql = 'SELECT COUNT(*) AS cnt FROM `' . str_replace('`', '``', (string)$cbTable) . '`';
        $cbRes = $cbDb->query($cbSql);
        $cbCnt = 0;
        if ($cbRes instanceof mysqli_result) {
            $cbRow = $cbRes->fetch_assoc();
            $cbCnt = (int)($cbRow['cnt'] ?? 0);
            $cbRes->free();
        }
        $cbRows[] = [
            'table' => (string)$cbTable,
            'count' => $cbCnt,
        ];
    }
    $cbScriptStats[$cbScriptName] = $cbRows;
}

if (!function_exists('cb_admin_init_tabulky_word')) {
    function cb_admin_init_tabulky_word(int $count): string
    {
        $abs = abs($count);
        $lastTwo = $abs % 100;
        $last = $abs % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return 'tabulek';
        }
        if ($last === 1) {
            return 'tabulku';
        }
        if ($last >= 2 && $last <= 4) {
            return 'tabulky';
        }
        return 'tabulek';
    }
}

if (!function_exists('cb_admin_init_status_html')) {
    function cb_admin_init_status_html(string $scriptName, array $scriptStats): string
    {
        $rows = $scriptStats[$scriptName] ?? [];
        $countTables = count($rows);
        $word = cb_admin_init_tabulky_word($countTables);

        $html = '<span style="display:inline-flex;align-items:center;gap:6px;">';
        $html .= '<span class="tooltip_box tooltip_table" style="display:inline-flex;align-items:center;justify-content:center;width:17px;height:17px;border-radius:999px;background:var(--clr_modra_5);color:var(--clr_bila);font-size:11px;font-weight:700;line-height:1;cursor:default;" aria-label="Informace o tabulkách">i';
        $html .= '<span class="tooltip_table_content">';
        $html .= '<table class="tooltip_table_data">';
        $html .= '<thead><tr><th class="txt_l">tabulka</th><th class="txt_r">počet záznamů</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $table = (string)($row['table'] ?? '');
            $count = (int)($row['count'] ?? 0);
            $html .= '<tr><td>' . h($table) . '</td><td class="txt_r">' . h(number_format($count, 0, ',', ' ')) . '</td></tr>';
        }

        $html .= '</tbody></table></span></span>';
        $html .= '<span>plní ' . h((string)$countTables) . ' ' . h($word) . '</span>';
        $html .= '</span>';
        return $html;
    }
}

$card_min_html = ''
    . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
    . '  <table class="table ram_normal bg_bila radek_1_35">'
    . '    <thead>'
    . '      <tr>'
    . '        <th class="txt_l">Zdroj</th>'
    . '        <th class="txt_r">záznamů</th>'
    . '        <th class="txt_r">aktualizace</th>'
    . '      </tr>'
    . '    </thead>'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Restia</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbRestiaCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbRestiaDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Směny</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbSmenyCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbSmenyDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Reporty</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbReportCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbReportDate) . '</td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>
<div class="table-wrap ram_normal bg_bila zaobleni_12">
<table class="table ram_normal bg_bila radek_1_35">
  <thead>
    <tr>
      <th class="txt_l">script v inicializace/</th>
      <th class="txt_l">databáze</th>
      <th class="txt_l">co stahuje</th>
      <th class="txt_c">stav testu</th>
      <th class="txt_l">akce</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>plnime_restia_objednavky.php</td>
      <td><?= cb_admin_init_status_html('plnime_restia_objednavky.php', $cbScriptStats) ?></td>
      <td>Restia objednávky</td>
      <td class="txt_c"><span class="txt_zelena text_tucny">OK</span></td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="run_restia_obj" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Importovat</button>
        </form>
      </td>
    </tr>
    <tr>
      <td>plnime_restia_menu.php</td>
      <td><?= cb_admin_init_status_html('plnime_restia_menu.php', $cbScriptStats) ?></td>
      <td>Restia menu</td>
      <td class="txt_c"><span class="txt_cervena text_tucny" style="font-size:150%; line-height:1;">x</span></td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="open_restia_menu" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">další test</button>
        </form>
      </td>
    </tr>
    <tr>
      <td>plnime_smeny_plan.php</td>
      <td><?= cb_admin_init_status_html('plnime_smeny_plan.php', $cbScriptStats) ?></td>
      <td>směny plán</td>
      <td class="txt_c"><span class="txt_cervena text_tucny" style="font-size:150%; line-height:1;">x</span></td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="open_smeny_plan" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">další test</button>
        </form>
      </td>
    </tr>
    <tr>
      <td>google_data.php</td>
      <td><?= cb_admin_init_status_html('google_data.php', $cbScriptStats) ?></td>
      <td>směny Google</td>
      <td class="txt_c"><span class="txt_cervena text_tucny" style="font-size:150%; line-height:1;">x</span></td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="open_google_data" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">další test</button>
        </form>
      </td>
    </tr>
</tbody>
</table>
<div style="margin-top:20px; margin-left:18px; margin-right:10px; margin-bottom:90px;">
  <p class="txt_cervena text_tucny" style="font-size:1.08rem; margin:0 0 6px 0;">Důležité informace !</p>
  <p class="card_text txt_seda odstup_vnejsi_0">Uvedené scripty slouží pouze k inicializaci systému.</p>
  <p class="card_text txt_seda odstup_vnejsi_0">Inicializace se provádí pouze jednou na každém serveru.</p>
  <p class="card_text txt_seda odstup_vnejsi_0">Před spuštěním bude většina existujících dat odstraněna z databáze.</p>
  <p class="card_text txt_seda odstup_vnejsi_0">Stránka přístupná výhradně pro admina, tím pádem je toto info vlastně zbytečné <span role="img" aria-label="smajlík">🙂</span></p>
</div>
</div>
<?php
$card_max_html = (string)ob_get_clean();

if ($cbRunSmenyPlan || $cbOpenSmenyPlan) {
    ob_start();
    require __DIR__ . '/../inicializace/plnime_smeny_plan.php';
    $card_max_html = (string)ob_get_clean();
}

if ($cbRunGoogleData || $cbOpenGoogleData) {
    ob_start();
    require __DIR__ . '/../inicializace/google_data.php';
    $card_max_html = (string)ob_get_clean();
}

if ($cbRunRestia || $cbKeepRestiaMax) {
    ob_start();
    require __DIR__ . '/../inicializace/plnime_restia_objednavky.php';
    $card_max_html = (string)ob_get_clean();
}

if ($cbRunRestiaMenu || $cbOpenRestiaMenu) {
    ob_start();
    require __DIR__ . '/../inicializace/plnime_restia_menu.php';
    $card_max_html = (string)ob_get_clean();
}

// karty/admin_inicializace.php * Verze: V13 * Aktualizace: 03.04.2026
// počet řádků 180
?>
