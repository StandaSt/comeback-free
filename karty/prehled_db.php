<?php
// K5
// karty/prehled_db.php * Verze: V12 * Aktualizace: 17.04.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/vypocet_prehled_db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('cb_prehled_db_page')) {
    function cb_prehled_db_page(): string
    {
        return pathinfo(__FILE__, PATHINFO_FILENAME);
    }
}

if (!function_exists('cb_prehled_db_scopes')) {
    function cb_prehled_db_scopes(): array
    {
        return [
            'komplet' => [
                'label' => 'Komplet DB',
                'allow_wipe' => false,
                'tables' => [],
            ],
            'restia' => [
                'label' => 'Restia',
                'allow_wipe' => false,
                'tables' => [
                    'api_restia',
                    'cis_doruceni',
                    'cis_obj_platby',
                    'cis_obj_platforma',
                    'cis_obj_stav',
                    'objednavky_restia',
                    'obj_adresa',
                    'obj_casy',
                    'obj_ceny',
                    'obj_import',
                    'obj_kuryr',
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_sluzba',
                    'res_alergen',
                    'res_cena',
                    'res_kategorie',
                    'res_polozky',
                ],
            ],
            'restia_obj' => [
                'label' => 'Restia objednávky',
                'allow_wipe' => true,
                'tables' => [
                    'api_restia',
                    'cis_doruceni',
                    'cis_obj_platby',
                    'cis_obj_platforma',
                    'cis_obj_stav',
                    'objednavky_restia',
                    'obj_adresa',
                    'obj_casy',
                    'obj_ceny',
                    'obj_import',
                    'obj_kuryr',
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_sluzba',
                    'zakaznik',
                ],
            ],
            'restia_menu' => [
                'label' => 'Restia menu',
                'allow_wipe' => true,
                'tables' => [
                    'res_alergen',
                    'res_cena',
                    'res_kategorie',
                    'res_polozky',
                ],
            ],
            'smeny' => [
                'label' => 'Směny',
                'allow_wipe' => true,
                'tables' => [
                    'api_smeny',
                    'smeny_akceptovane',
                    'smeny_aktualizace',
                    'smeny_plan',
                    'smeny_report',
                ],
            ],
            'reporty' => [
                'label' => 'Reporty',
                'allow_wipe' => true,
                'tables' => [
                    'cb_reporty_person',
                    'cb_reporty',
                ],
            ],
            'system' => [
                'label' => 'Systém',
                'allow_wipe' => false,
                'tables' => [
                    'cis_chyby',
                    'cis_polozka_kat',
                    'cis_polozky',
                    'cis_prac_zarazeni',
                    'cis_role',
                    'cis_slot',
                    'cis_sloupce',
                    'init_scripty',
                    'karty',
                    'log_chyby',
                    'pobocka',
                    'pob_email',
                    'pob_manager',
                    'pob_povoleni',
                    'pob_povoleni_hist',
                    'pob_tel',
                    'push_audit',
                    'push_login_2fa',
                    'push_parovani',
                    'push_zarizeni',
                    'restia_token',
                    'user',
                    'user_bad_login',
                    'user_login',
                    'user_nano',
                    'user_pin',
                    'user_pobocka',
                    'user_pobocka_set',
                    'user_role',
                    'user_set',
                    'user_slot',
                    'user_spy',
                ],
            ],
        ];
    }
}


if (!function_exists('cb_prehled_db_norm_scope')) {
    function cb_prehled_db_norm_scope(string $scope): string
    {
        $scopes = cb_prehled_db_scopes();
        return isset($scopes[$scope]) ? $scope : 'restia';
    }
}

if (!function_exists('cb_prehled_db_h')) {
    function cb_prehled_db_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_prehled_db_fmt_bytes')) {
    function cb_prehled_db_fmt_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 'B';

        foreach ($units as $u) {
            $value /= 1024;
            $unit = $u;
            if ($value < 1024) {
                break;
            }
        }

        return number_format($value, 2, ',', ' ') . ' ' . $unit;
    }
}

if (!function_exists('cb_prehled_db_count_style')) {
    function cb_prehled_db_count_style(int $value): string
    {
        return $value === 0 ? 'txt_r txt_cervena text_tucny' : 'txt_r';
    }
}

if (!function_exists('cb_prehled_db_fmt_rows_approx')) {
    function cb_prehled_db_fmt_rows_approx(int $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}

if (!function_exists('cb_prehled_db_random_code')) {
    function cb_prehled_db_random_code(): string
    {
        return str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('cb_prehled_db_set_flash')) {
    function cb_prehled_db_set_flash(string $type, string $message, array $wipeResult = []): void
    {
        $_SESSION['cb_prehled_db_flash'] = [
            'type' => $type,
            'message' => $message,
            'wipe_result' => $wipeResult,
        ];
    }
}

if (!function_exists('cb_prehled_db_get_flash')) {
    function cb_prehled_db_get_flash(): array
    {
        $flash = $_SESSION['cb_prehled_db_flash'] ?? [];
        unset($_SESSION['cb_prehled_db_flash']);

        return is_array($flash) ? $flash : [];
    }
}

if (!function_exists('cb_prehled_db_table_meta')) {
    function cb_prehled_db_table_meta(mysqli $conn): array
    {
        return cb_db_table_meta($conn);
    }
}


if (!function_exists('cb_prehled_db_scope_tables')) {
    function cb_prehled_db_scope_tables(mysqli $conn, string $scope): array
    {
        $scopes = cb_prehled_db_scopes();
        $meta = cb_prehled_db_table_meta($conn);

        if ($scope === 'komplet') {
            $all = array_keys($meta);
            sort($all, SORT_STRING);
            return $all;
        }

        $tables = $scopes[$scope]['tables'] ?? [];
        $out = [];
        foreach ($tables as $table) {
            $table = (string)$table;
            if (isset($meta[$table])) {
                $out[] = $table;
            }
        }

        sort($out, SORT_STRING);
        return $out;
    }
}


if (!function_exists('cb_prehled_db_reset_ai')) {
    function cb_prehled_db_reset_ai(mysqli $conn, string $table): void
    {
        $sql = 'ALTER TABLE `' . str_replace('`', '``', $table) . '` AUTO_INCREMENT = 1';
        $ok = $conn->query($sql);
        if ($ok === false) {
            throw new RuntimeException('Reset AUTO_INCREMENT selhal pro tabulku ' . $table . ': ' . $conn->error);
        }
    }
}

if (!function_exists('cb_prehled_db_delete_table')) {
    function cb_prehled_db_delete_table(mysqli $conn, string $table): int
    {
        $tableSql = '`' . str_replace('`', '``', $table) . '`';
        $sql = 'TRUNCATE TABLE ' . $tableSql;
        $ok = $conn->query($sql);
        if ($ok === false) {
            throw new RuntimeException('Mazání selhalo pro tabulku ' . $table . ': ' . $conn->error);
        }

        return 0;
    }
}

if (!function_exists('cb_prehled_db_wipe_tables')) {
    function cb_prehled_db_wipe_tables(mysqli $conn, string $scope): array
    {
        $scopes = cb_prehled_db_scopes();
        if (!isset($scopes[$scope])) {
            throw new RuntimeException('Neplatná oblast.');
        }

        if (($scopes[$scope]['allow_wipe'] ?? false) !== true) {
            throw new RuntimeException('Tuto oblast nelze vyprázdnit.');
        }

        $tables = cb_prehled_db_scope_tables($conn, $scope);
        $deleted = [];

        try {
            $conn->query('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                $deleted[$table] = cb_prehled_db_delete_table($conn, $table);
            }

            foreach ($tables as $table) {
                cb_prehled_db_reset_ai($conn, $table);
            }
        } finally {
            $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $deleted;
    }
}

$page = cb_prehled_db_page();
$scopes = cb_prehled_db_scopes();

$scopeRequest = trim((string)($_REQUEST['db_scope'] ?? ''));
if ($scopeRequest !== '') {
    $scope = cb_prehled_db_norm_scope($scopeRequest);
    $_SESSION['cb_prehled_db_scope'] = $scope;
} else {
    $scope = cb_prehled_db_norm_scope((string)($_SESSION['cb_prehled_db_scope'] ?? 'komplet'));
}

$conn = db();

$msgOk = '';
$msgErr = '';
$wipeResult = [];
$rows = [];
$totalRows = 0;
$totalBytes = 0;
$minSummary = [];

if (!isset($_SESSION['cb_prehled_db_kod']) || !is_array($_SESSION['cb_prehled_db_kod'])) {
    $_SESSION['cb_prehled_db_kod'] = [];
}

if (($scopes[$scope]['allow_wipe'] ?? false) === true && !isset($_SESSION['cb_prehled_db_kod'][$scope])) {
    $_SESSION['cb_prehled_db_kod'][$scope] = cb_prehled_db_random_code();
}

if (isset($_POST['db_action']) && (string)($_POST['db_action'] ?? '') === 'wipe') {
    try {
        $confirm = trim((string)($_POST['db_confirm'] ?? ''));
        $expected = (string)($_SESSION['cb_prehled_db_kod'][$scope] ?? '');

        if ($expected === '' || $confirm !== $expected) {
            throw new RuntimeException('Bezpečnostní kód nesouhlasí.');
        }

        $wipeResult = cb_prehled_db_wipe_tables($conn, $scope);
        $_SESSION['cb_prehled_db_kod'][$scope] = cb_prehled_db_random_code();
        cb_prehled_db_set_flash('ok', 'Vybrané tabulky byly vyprázdněny.', $wipeResult);
    } catch (Throwable $e) {
        cb_prehled_db_set_flash('err', $e->getMessage(), []);
    }

}

$flash = cb_prehled_db_get_flash();
if (($flash['type'] ?? '') === 'ok') {
    $msgOk = (string)($flash['message'] ?? '');
    $wipeResult = is_array($flash['wipe_result'] ?? null) ? $flash['wipe_result'] : [];
} elseif (($flash['type'] ?? '') === 'err') {
    $msgErr = (string)($flash['message'] ?? '');
}

try {
    $tableMeta = cb_prehled_db_table_meta($conn);
    $tables = cb_prehled_db_scope_tables($conn, $scope);

    foreach ($tables as $table) {
        $count = cb_vypocet_prehled_db_table_count($conn, $table);
        $bytes = (int)($tableMeta[$table]['bytes'] ?? 0);

        $rows[] = [
            'table' => $table,
            'count' => $count,
            'bytes' => $bytes,
        ];

        $totalRows += $count;
        $totalBytes += $bytes;
    }

    $summary = cb_vypocet_prehled_db($conn);
    $minSummary = (array)($summary['rows'] ?? []);
} catch (Throwable $e) {
    $msgErr = $e->getMessage();
}

$wipeCode = (string)($_SESSION['cb_prehled_db_kod'][$scope] ?? '');
$cardRootId = 'prehled_db_root_' . substr(md5(__FILE__), 0, 8);
$cleanUrl = cb_url('/');

ob_start();
?>
<div class="displ_flex jc_stred">
  <table class="table ram_normal bg_bila radek_1_35 sirka100">
    <thead>
      <tr>
        <th class="txt_l">Zdroj</th>
        <th class="txt_r">záznamů</th>
        <th class="txt_r">objem</th>
        <th class="txt_r">aktualizace</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($minSummary as $item): ?>
        <tr>
          <td><?= cb_prehled_db_h((string)$item['source']) ?></td>
          <td class="<?= cb_prehled_db_h(cb_prehled_db_count_style((int)$item['count'])) ?>"><strong><?= cb_prehled_db_h(number_format((int)$item['count'], 0, ',', ' ')) ?></strong></td>
          <td><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes((int)$item['bytes'])) ?></td>
          <td><?= cb_prehled_db_h((string)($item['updated_at'] ?? 'Ne')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<div id="<?= cb_prehled_db_h($cardRootId) ?>" class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10 sirka100" style="max-height:100%; box-sizing:border-box; overflow:hidden;">
  <div style="display:grid; grid-template-columns:220px minmax(0, 1fr); gap:12px; align-items:stretch; flex:1 1 auto; min-height:0; max-height:100%;">
    <div class="displ_flex flex_sloupec" style="min-height:0; max-height:100%;">
      <form method="post" action="<?= cb_prehled_db_h(cb_url('/')) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
        <input type="hidden" name="page" value="<?= cb_prehled_db_h($page) ?>">
        <div class="displ_flex flex_sloupec gap_8">
          <?php foreach (['komplet', 'restia_obj', 'restia_menu', 'smeny', 'reporty', 'system'] as $key): ?>
            <?php $cfg = $scopes[$key]; ?>
            <label class="displ_flex gap_8 filter-actions cursor_ruka">
              <input
                type="radio"
                name="db_scope"
                value="<?= cb_prehled_db_h($key) ?>"
                <?= $scope === $key ? 'checked' : '' ?>
                data-cb-submit-on-change="1"
              >
              <span class="<?= $scope === $key ? 'text_tucny' : '' ?>"><?= cb_prehled_db_h((string)$cfg['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </form>
    </div>

    <div class="displ_flex flex_sloupec" style="min-height:0; height:100%;">
      <?php if ($msgErr !== ''): ?>
        <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
          <p class="card_text txt_cervena odstup_vnejsi_0"><?= cb_prehled_db_h($msgErr) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($msgOk !== ''): ?>
        <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10<?= $msgErr !== '' ? ' odstup_horni_10' : '' ?>">
          <p class="card_text txt_zelena odstup_vnejsi_0"><?= cb_prehled_db_h($msgOk) ?></p>
          <?php if ($wipeResult !== []): ?>
            <div class="card_text txt_seda odstup_vnejsi_0 odstup_horni_10 displ_flex gap_10">
              <?php foreach ($wipeResult as $table => $count): ?>
                <span><?= cb_prehled_db_h($table . ': ' . $count) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <form method="get" action="<?= cb_prehled_db_h(cb_url('/')) ?>" class="odstup_vnejsi_0 odstup_horni_10">
            <input type="hidden" name="page" value="<?= cb_prehled_db_h($page) ?>">
            <input type="hidden" name="db_scope" value="<?= cb_prehled_db_h($scope) ?>">
            <button type="submit" class="btn btn-primary">Pokračovat</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="table-wrap" style="flex:1 1 auto; min-height:0; max-height:100%; overflow-y:auto; overflow-x:auto; background:var(--clr_bila);">
        <table class="table ram_normal bg_bila radek_1_35 sirka100" style="table-layout:fixed; margin:0;">
          <colgroup>
            <col style="width:42%;">
            <col style="width:29%;">
            <col style="width:29%;">
          </colgroup>
          <thead>
            <tr>
              <th>Tabulka</th>
              <th class="txt_r">Počet záznamů</th>
              <th class="txt_r">Objem dat</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= cb_prehled_db_h((string)$row['table']) ?></td>
                <td style="<?= cb_prehled_db_h(cb_prehled_db_count_style((int)$row['count'])) ?>"><?= cb_prehled_db_h(cb_prehled_db_fmt_rows_approx((int)$row['count'])) ?></td>
                <td class="txt_r"><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes((int)$row['bytes'])) ?></td>
              </tr>
            <?php endforeach; ?>

            <?php if ($rows === []): ?>
              <tr>
                <td colspan="3">Bez tabulek.</td>
              </tr>
            <?php else: ?>
              <tr>
                <td><strong>Součet</strong></td>
                <td class="txt_r"><strong><?= cb_prehled_db_h(cb_prehled_db_fmt_rows_approx($totalRows)) ?></strong></td>
                <td class="txt_r"><strong><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes($totalBytes)) ?></strong></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if (($scopes[$scope]['allow_wipe'] ?? false) === true): ?>
    <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10 odstup_horni_10">
      <form method="post" action="<?= cb_prehled_db_h(cb_url('/')) ?>" class="odstup_vnejsi_0">
        <input type="hidden" name="page" value="<?= cb_prehled_db_h($page) ?>">
        <input type="hidden" name="db_scope" value="<?= cb_prehled_db_h($scope) ?>">
        <input type="hidden" name="db_action" value="wipe">

        <div class="displ_flex gap_8 jc_stred filter-actions">
          <span class="card_text txt_seda">Zadej bezpečnostní kód: <strong><?= cb_prehled_db_h($wipeCode) ?></strong></span>
          <input
            type="text"
            name="db_confirm"
            value=""
            class="input"
            style="max-width:120px;"
            autocomplete="off"
          >
          <button type="submit" class="btn btn-primary">Vyprázdnit</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/prehled_db.php * Verze: V11 * Aktualizace: 02.04.2026 */
// Počet řádků: 647
// Předchozí počet řádků: 640
?>
