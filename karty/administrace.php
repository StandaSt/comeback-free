<?php
// K2
// karty/administrace.php * Verze: V4 * Aktualizace: 17.05.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text odstup_vnejsi_0">administrace</p>';
$card_max_html = '';

$cbAdminSystem = [
    'restia_online' => 0,
    'on_2fa' => 0,
    'system_logout' => 0,
    'pauza_obdobi' => 1000,
    'log_1' => 0,
    'log_2' => 0,
    'log_3' => 0,
    'log_4' => 0,
];
$cbAdminError = '';
$cbAdminSaveName = trim((string)($_POST['cb_admin_set_name'] ?? ''));
$cbAdminSaveValue = trim((string)($_POST['cb_admin_set_value'] ?? ''));
$cbAdminLogoutOptions = [2, 5, 10, 15, 20, 30, 60];
$cbAdminPauzaOptions = [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000];
$cbAdminLogLabels = [
    'log_1' => 'Měření výkonu systému (SQL + karty)',
    'log_2' => 'Měření načítání dashboardu',
    'log_3' => 'Sledování průběhu načítání a AJAX komunikace',
    'log_4' => 'Historie importu objednávek z Restia (po dnech)',
];

try {
    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    if ($cbAdminSaveName !== '') {
        $allowedBoolFields = ['restia_online', 'on_2fa', 'log_1', 'log_2', 'log_3', 'log_4'];
        if (in_array($cbAdminSaveName, $allowedBoolFields, true)) {
            $saveValue = ($cbAdminSaveValue === '1') ? 1 : 0;
            $sql = 'UPDATE set_system SET `' . $cbAdminSaveName . '` = ? WHERE id_set = 1 LIMIT 1';
            $stmt = $conn->prepare($sql);
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        } elseif ($cbAdminSaveName === 'system_logout') {
            $saveValue = (int)$cbAdminSaveValue;
            if (!in_array($saveValue, $cbAdminLogoutOptions, true)) {
                $saveValue = 20;
            }
            $stmt = $conn->prepare('UPDATE set_system SET system_logout = ? WHERE id_set = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        } elseif ($cbAdminSaveName === 'pauza_obdobi') {
            $saveValue = (int)$cbAdminSaveValue;
            if (!in_array($saveValue, $cbAdminPauzaOptions, true)) {
                $saveValue = 1000;
            }
            $stmt = $conn->prepare('UPDATE set_system SET pauza_obdobi = ? WHERE id_set = 1 LIMIT 1');
            if ($stmt instanceof mysqli_stmt) {
                $stmt->bind_param('i', $saveValue);
                $stmt->execute();
                $stmt->close();
            } else {
                $cbAdminError = 'Uložení nastavení selhalo.';
            }
        }
    }

    if ($cbAdminError === '') {
        $res = $conn->query('
            SELECT restia_online, on_2fa, system_logout, pauza_obdobi, log_1, log_2, log_3, log_4
            FROM set_system
            WHERE id_set = 1
            LIMIT 1
        ');

        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            if (is_array($row)) {
                $cbAdminSystem['restia_online'] = (int)($row['restia_online'] ?? 0);
                $cbAdminSystem['on_2fa'] = (int)($row['on_2fa'] ?? 0);
                $cbAdminSystem['system_logout'] = (int)($row['system_logout'] ?? 0);
                $cbAdminSystem['pauza_obdobi'] = (int)($row['pauza_obdobi'] ?? 1000);
                $cbAdminSystem['log_1'] = (int)($row['log_1'] ?? 0);
                $cbAdminSystem['log_2'] = (int)($row['log_2'] ?? 0);
                $cbAdminSystem['log_3'] = (int)($row['log_3'] ?? 0);
                $cbAdminSystem['log_4'] = (int)($row['log_4'] ?? 0);
                if (function_exists('cb_store_system_settings')) {
                    cb_store_system_settings($row);
                }
            } else {
                $cbAdminError = 'Nastavení systému nebylo nalezeno.';
            }
            $res->free();
        } else {
            $cbAdminError = 'Načtení nastavení systému selhalo.';
        }
    }
} catch (Throwable $e) {
    $cbAdminError = 'Načtení nastavení systému selhalo.';
}

$cbAdminBoolClass = static function (int $value): string {
    return $value === 1 ? 'txt_zelena text_tucny' : 'txt_cervena text_tucny';
};

$cbAdminFormAction = cb_url('/index.php');

ob_start();
?>
<?php if ($cbAdminError !== ''): ?>
  <p class="card_text txt_cervena odstup_vnejsi_0"><?= h($cbAdminError) ?></p>
<?php else: ?>
  <?php // VZHLED K2 JE ZAMCENY: bez vyslovneho schvaleni nemenit sirky sloupcu, zarovnani, zalamovani ani texty (vcetne diakritiky). ?>
  <div class="card_stack gap_8">
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Nastavení</th>
            <th style="width:1%;white-space:nowrap;">Aktuální hodnota</th>
            <th style="white-space:nowrap;">Význam</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="white-space:nowrap;">Restia online</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['restia_online'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="restia_online">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['restia_online'] === 1 ? ' selected' : '' ?>>Aktivni</option>
                  <option value="0"<?= $cbAdminSystem['restia_online'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Online aktualizace objednávek Restia</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">2FA</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['on_2fa'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="on_2fa">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['on_2fa'] === 1 ? ' selected' : '' ?>>Aktivní</option>
                  <option value="0"<?= $cbAdminSystem['on_2fa'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Dvoufázové ověření při přihlášení</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">System logout</td>
            <td class="text_tucny">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="system_logout">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminLogoutOptions as $cbLogoutValue): ?>
                    <option value="<?= h((string)$cbLogoutValue) ?>"<?= $cbAdminSystem['system_logout'] === $cbLogoutValue ? ' selected' : '' ?>><?= h((string)$cbLogoutValue) ?> min</option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Odhlášení uživatele pro neaktivitu, limit</td>
          </tr>
          <tr>
            <td style="white-space:nowrap;">Pauza období</td>
            <td class="text_tucny">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="pauza_obdobi">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <?php foreach ($cbAdminPauzaOptions as $cbPauzaValue): ?>
                    <option value="<?= h((string)$cbPauzaValue) ?>"<?= $cbAdminSystem['pauza_obdobi'] === $cbPauzaValue ? ' selected' : '' ?>><?= h((string)$cbPauzaValue) ?> ms</option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">Prodleva při ruční volbě globálního nastavení období</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="table-wrap ram_normal bg_bila" style="width:100%;margin:0 auto;">
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="width:100%;table-layout:auto;">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap;">Aktivní</th>
            <th>Aktivace logování</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cbAdminLogLabels as $cbLogKey => $cbLogLabel): ?>
            <tr>
              <td class="txt_c" style="white-space:nowrap;">
                <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                  <input type="hidden" name="cb_admin_set_name" value="<?= h($cbLogKey) ?>">
                  <input type="hidden" name="cb_admin_set_value" value="0">
                  <input
                    type="checkbox"
                    name="cb_admin_set_value"
                    value="1"
                    <?= ((int)($cbAdminSystem[$cbLogKey] ?? 0) === 1) ? 'checked' : '' ?>
                    onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}"
                  >
                </form>
              </td>
              <td><?= h($cbLogLabel) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
