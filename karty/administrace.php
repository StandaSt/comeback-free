<?php
// K2
// karty/administrace.php * Verze: V3 * Aktualizace: 11.05.2026
declare(strict_types=1);

$card_min_html = '<p class="card_text odstup_vnejsi_0">administrace</p>';
$card_max_html = '';

$cbAdminSystem = [
    'restia_online' => 0,
    'on_2fa' => 0,
    'system_logout' => 0,
    'pauza_obdobi' => 1000,
];
$cbAdminError = '';
$cbAdminSaveName = trim((string)($_POST['cb_admin_set_name'] ?? ''));
$cbAdminSaveValue = trim((string)($_POST['cb_admin_set_value'] ?? ''));
$cbAdminLogoutOptions = [2, 5, 10, 15, 20, 30, 60];
$cbAdminPauzaOptions = [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000];

try {
    $conn = db();
    if (method_exists($conn, 'set_charset')) {
        $conn->set_charset('utf8mb4');
    }

    if ($cbAdminSaveName !== '') {
        $allowedBoolFields = ['restia_online', 'on_2fa'];
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
            SELECT restia_online, on_2fa, system_logout, pauza_obdobi
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
  <div class="card_stack gap_8">
    <div class="table-wrap ram_normal bg_bila">
      <table class="table ram_normal bg_bila radek_1_35 sirka100">
        <thead>
          <tr>
            <th>Nastavení</th>
            <th>Aktuální hodnota</th>
            <th>Význam</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Restia online</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['restia_online'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="restia_online">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['restia_online'] === 1 ? ' selected' : '' ?>>Aktivní</option>
                  <option value="0"<?= $cbAdminSystem['restia_online'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td>Automatické online aktualizace objednávek Restia</td>
          </tr>
          <tr>
            <td>2FA</td>
            <td class="<?= h($cbAdminBoolClass($cbAdminSystem['on_2fa'])) ?>">
              <form method="post" action="<?= h($cbAdminFormAction) ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
                <input type="hidden" name="cb_admin_set_name" value="on_2fa">
                <select name="cb_admin_set_value" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value="1"<?= $cbAdminSystem['on_2fa'] === 1 ? ' selected' : '' ?>>Aktivní</option>
                  <option value="0"<?= $cbAdminSystem['on_2fa'] === 0 ? ' selected' : '' ?>>Neaktivní</option>
                </select>
              </form>
            </td>
            <td>Dvoufázové ověření při přihlášení</td>
          </tr>
          <tr>
            <td>System logout</td>
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
            <td>Limit nečinnosti před automatickým odhlášením</td>
          </tr>
          <tr>
            <td>Pauza období</td>
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
            <td>Čekání při ruční volbě období v GN hlavičce</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
