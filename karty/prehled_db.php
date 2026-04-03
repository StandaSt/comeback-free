<?php
// karty/prehled_db.php * Verze: V11 * Aktualizace: 02.04.2026
declare(strict_types=1);

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
                    'obj_raw',
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
                    'obj_raw',
                    'obj_sluzba',
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
                    'zakaznik',
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

if (!function_exists('cb_prehled_db_fmt_int')) {
    function cb_prehled_db_fmt_int(int $value): string
    {
        return number_format($value, 0, ',', ' ');
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
        return $value === 0 ? 'text-align:right; color:#b91c1c; font-weight:700;' : 'text-align:right;';
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

if (!function_exists('cb_prehled_db_table_exists')) {
    function cb_prehled_db_table_exists(mysqli $conn, string $table): bool
    {
        $sql = '
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nepodařilo se připravit kontrolu tabulky.');
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res instanceof mysqli_result) ? ($res->fetch_row() !== null) : false;

        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('cb_prehled_db_all_tables')) {
    function cb_prehled_db_all_tables(mysqli $conn): array
    {
        $sql = '
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY table_name
        ';
        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se načíst seznam tabulek.');
        }

        $tables = [];
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['table_name'] ?? '');
            if ($name !== '') {
                $tables[] = $name;
            }
        }
        $res->free();

        return $tables;
    }
}

if (!function_exists('cb_prehled_db_scope_tables')) {
    function cb_prehled_db_scope_tables(mysqli $conn, string $scope): array
    {
        $scopes = cb_prehled_db_scopes();

        if ($scope === 'komplet') {
            return cb_prehled_db_all_tables($conn);
        }

        $tables = $scopes[$scope]['tables'] ?? [];
        $out = [];
        foreach ($tables as $table) {
            if (cb_prehled_db_table_exists($conn, (string)$table)) {
                $out[] = (string)$table;
            }
        }

        return $out;
    }
}

if (!function_exists('cb_prehled_db_count_rows')) {
    function cb_prehled_db_count_rows(mysqli $conn, string $table): int
    {
        $sql = 'SELECT COUNT(*) AS cnt FROM `' . str_replace('`', '``', $table) . '`';
        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se spočítat řádky tabulky ' . $table . '.');
        }

        $row = $res->fetch_assoc();
        $res->free();

        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('cb_prehled_db_sizes')) {
    function cb_prehled_db_sizes(mysqli $conn): array
    {
        $sql = '
            SELECT table_name, COALESCE(data_length, 0) + COALESCE(index_length, 0) AS size_bytes
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ';
        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se načíst objemy tabulek.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['table_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] = (int)($row['size_bytes'] ?? 0);
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_prehled_db_delete_table')) {
    function cb_prehled_db_delete_table(mysqli $conn, string $table): int
    {
        $sql = 'DELETE FROM `' . str_replace('`', '``', $table) . '`';
        $ok = $conn->query($sql);
        if ($ok === false) {
            throw new RuntimeException('Mazání selhalo pro tabulku ' . $table . ': ' . $conn->error);
        }

        return (int)$conn->affected_rows;
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

        $conn->begin_transaction();
        try {
            $conn->query('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                $deleted[$table] = cb_prehled_db_delete_table($conn, $table);
                cb_prehled_db_reset_ai($conn, $table);
            }

            $conn->query('SET FOREIGN_KEY_CHECKS = 1');
            $conn->commit();
        } catch (Throwable $e) {
            $conn->query('SET FOREIGN_KEY_CHECKS = 1');
            $conn->rollback();
            throw $e;
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['db_action'] ?? '') === 'wipe') {
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
    $sizes = cb_prehled_db_sizes($conn);
    $tables = cb_prehled_db_scope_tables($conn, $scope);

    foreach ($tables as $table) {
        $count = cb_prehled_db_count_rows($conn, $table);
        $bytes = (int)($sizes[$table] ?? 0);

        $rows[] = [
            'table' => $table,
            'count' => $count,
            'bytes' => $bytes,
        ];

        $totalRows += $count;
        $totalBytes += $bytes;
    }

    foreach (['restia', 'smeny', 'reporty', 'system'] as $summaryScope) {
        $summaryTables = cb_prehled_db_scope_tables($conn, $summaryScope);
        $summaryRows = 0;
        $summaryBytes = 0;

        foreach ($summaryTables as $summaryTable) {
            $summaryRows += cb_prehled_db_count_rows($conn, $summaryTable);
            $summaryBytes += (int)($sizes[$summaryTable] ?? 0);
        }

        $minSummary[] = [
            'label' => (string)$scopes[$summaryScope]['label'],
            'count' => $summaryRows,
            'bytes' => $summaryBytes,
        ];
    }
} catch (Throwable $e) {
    $msgErr = $e->getMessage();
}

$wipeCode = (string)($_SESSION['cb_prehled_db_kod'][$scope] ?? '');
$cardRootId = 'prehled_db_root_' . substr(md5(__FILE__), 0, 8);
$cleanUrl = cb_url('/');

ob_start();
?>
<div style="display:flex; justify-content:center;">
  <table class="table ram_normal bg_bila radek_1_35" style="width:auto;">
    <thead>
      <tr>
        <th>Skupina</th>
        <th style="text-align:right;">Záznamů</th>
        <th style="text-align:right;">Objem</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($minSummary as $item): ?>
        <tr>
          <td><?= cb_prehled_db_h((string)$item['label']) ?></td>
          <td style="<?= cb_prehled_db_h(cb_prehled_db_count_style((int)$item['count'])) ?>"><?= cb_prehled_db_h(cb_prehled_db_fmt_int((int)$item['count'])) ?></td>
          <td style="text-align:right;"><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes((int)$item['bytes'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<div id="<?= cb_prehled_db_h($cardRootId) ?>" class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10" style="width:100%; max-height:100%; box-sizing:border-box; overflow:hidden; display:flex; flex-direction:column;">
  <div style="display:grid; grid-template-columns:220px minmax(0, 1fr); gap:12px; align-items:stretch; flex:1 1 auto; min-height:0; max-height:100%;">
    <div style="display:flex; flex-direction:column; min-height:0; max-height:100%;">
      <form method="get" action="<?= cb_prehled_db_h(cb_url('/')) ?>" class="odstup_vnejsi_0">
        <input type="hidden" name="page" value="<?= cb_prehled_db_h($page) ?>">
        <div style="display:flex; flex-direction:column; gap:8px;">
          <?php foreach (['komplet', 'restia_obj', 'restia_menu', 'smeny', 'reporty', 'system'] as $key): ?>
            <?php $cfg = $scopes[$key]; ?>
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
              <input
                type="radio"
                name="db_scope"
                value="<?= cb_prehled_db_h($key) ?>"
                <?= $scope === $key ? 'checked' : '' ?>
                onchange="this.form.submit()"
              >
              <span style="<?= $scope === $key ? 'font-weight:700;' : '' ?>"><?= cb_prehled_db_h((string)$cfg['label']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </form>
    </div>

    <div style="display:flex; flex-direction:column; min-height:0; height:100%;">
      <?php if ($msgErr !== ''): ?>
        <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
          <p class="card_text txt_cervena odstup_vnejsi_0"><?= cb_prehled_db_h($msgErr) ?></p>
        </div>
      <?php endif; ?>

      <?php if ($msgOk !== ''): ?>
        <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10<?= $msgErr !== '' ? ' odstup_horni_10' : '' ?>">
          <p class="card_text txt_zelena odstup_vnejsi_0"><?= cb_prehled_db_h($msgOk) ?></p>
          <?php if ($wipeResult !== []): ?>
            <div class="card_text txt_seda odstup_vnejsi_0 odstup_horni_10" style="display:flex; flex-wrap:wrap; gap:6px 10px;">
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
        <table class="table ram_normal bg_bila radek_1_35" style="width:100%; table-layout:fixed; margin:0;">
          <colgroup>
            <col style="width:42%;">
            <col style="width:29%;">
            <col style="width:29%;">
          </colgroup>
          <thead>
            <tr>
              <th>Tabulka</th>
              <th style="text-align:right;">Počet záznamů</th>
              <th style="text-align:right;">Objem dat</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><?= cb_prehled_db_h((string)$row['table']) ?></td>
                <td style="<?= cb_prehled_db_h(cb_prehled_db_count_style((int)$row['count'])) ?>"><?= cb_prehled_db_h(cb_prehled_db_fmt_int((int)$row['count'])) ?></td>
                <td style="text-align:right;"><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes((int)$row['bytes'])) ?></td>
              </tr>
            <?php endforeach; ?>

            <?php if ($rows === []): ?>
              <tr>
                <td colspan="3">Bez tabulek.</td>
              </tr>
            <?php else: ?>
              <tr>
                <td><strong>Součet</strong></td>
                <td style="text-align:right;"><strong><?= cb_prehled_db_h(cb_prehled_db_fmt_int($totalRows)) ?></strong></td>
                <td style="text-align:right;"><strong><?= cb_prehled_db_h(cb_prehled_db_fmt_bytes($totalBytes)) ?></strong></td>
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

        <div class="displ_flex gap_8" style="align-items:center; justify-content:center; flex-wrap:wrap;">
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
// Počet řádků: 640
// Předchozí počet řádků: 640
?>
