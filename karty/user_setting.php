<?php
// K7
// karty/user_setting.php * Verze: V3 * Aktualizace: 13.05.2026
declare(strict_types=1);

$usError = '';
$usOk = '';

$usUser = $_SESSION['cb_user'] ?? null;
$usUserId = (is_array($usUser) && isset($usUser['id_user'])) ? (int)$usUser['id_user'] : 0;
$usRoleId = (is_array($usUser) && isset($usUser['id_role'])) ? (int)$usUser['id_role'] : 0;
$usCanManager = ($usRoleId > 0 && $usRoleId <= 3);
$usCurrentSettings = cb_user_settings();

$usNanoKde = in_array((int)($usCurrentSettings['nano_kde'] ?? 0), [0, 1], true) ? (int)($usCurrentSettings['nano_kde'] ?? 0) : 0;
$usProdleva = (int)($usCurrentSettings['prodleva'] ?? 3000);
$usPismo = in_array((int)($usCurrentSettings['pismo'] ?? 2), [1, 2, 3], true) ? (int)($usCurrentSettings['pismo'] ?? 2) : 2;
$usDark = in_array((int)($usCurrentSettings['dark'] ?? 0), [0, 1], true) ? (int)($usCurrentSettings['dark'] ?? 0) : 0;
$usLogoutLimitRaw = $usCurrentSettings['logout_limit'] ?? null;
$usLogoutLimit = ($usLogoutLimitRaw === null || $usLogoutLimitRaw === '') ? null : (int)$usLogoutLimitRaw;
if (!in_array($usLogoutLimit, [30, 60, 120, 240, 480], true)) {
    $usLogoutLimit = null;
}
$usProdlevaOptions = [
    0 => 'Bez prodlevy - POZOR, toto způsobí časté přepočítávání obsahu',
    1000 => '1 sekunda',
    1500 => '1,5 sekundy',
    2000 => '2 sekundy',
    2500 => '2,5 sekundy',
    3000 => '3 sekundy',
    3500 => '3,5 sekundy',
    4000 => '4 sekundy',
    4500 => '4,5 sekundy',
    5000 => '5 sekund',
];
$usLogoutLimitOptions = [
    30 => '30 min.',
    60 => '1 hodina',
    120 => '2 hodiny',
    240 => '4 hodiny',
    480 => '8 hodin',
];

$formAction = cb_url('/');

if ($usUserId > 0) {
    try {
        $conn = db();

        if ((string)($_POST['us_action'] ?? '') === 'save') {
            $postNanoKde = (int)($_POST['us_nano_kde'] ?? 0);
            $postProdleva = (int)($_POST['us_prodleva'] ?? 3000);
            $postPismo = (int)($_POST['us_pismo'] ?? 2);
            $postDark = (int)($_POST['us_dark'] ?? 0);
            $postLogoutLimit = $usLogoutLimit;

            if (!in_array($postNanoKde, [0, 1], true)) {
                $postNanoKde = 0;
            }
            if (!array_key_exists($postProdleva, $usProdlevaOptions)) {
                $postProdleva = 3000;
            }
            if (!in_array($postPismo, [1, 2, 3], true)) {
                $postPismo = 2;
            }
            if (!in_array($postDark, [0, 1], true)) {
                $postDark = 0;
            }
            if ($usCanManager) {
                $postLogoutLimitRaw = trim((string)($_POST['us_logout_limit'] ?? ''));
                if ($postLogoutLimitRaw === '') {
                    $postLogoutLimit = null;
                } else {
                    $postLogoutLimit = (int)$postLogoutLimitRaw;
                    if (!array_key_exists($postLogoutLimit, $usLogoutLimitOptions)) {
                        $postLogoutLimit = null;
                    }
                }
            }

            $stmtUpd = $conn->prepare('UPDATE user_set SET nano_kde = ?, prodleva = ?, pismo = ?, dark = ?, logout_limit = ? WHERE id_user = ?');
            if ($stmtUpd === false) {
                throw new RuntimeException('Nepodarilo se pripravit update user_set.');
            }
            $stmtUpd->bind_param('iiiiii', $postNanoKde, $postProdleva, $postPismo, $postDark, $postLogoutLimit, $usUserId);
            $stmtUpd->execute();
            $stmtUpd->close();

            cb_store_user_settings([
                'nano_kde' => $postNanoKde,
                'prodleva' => $postProdleva,
                'pismo' => $postPismo,
                'dark' => $postDark,
                'logout_limit' => $postLogoutLimit,
            ]);
            $usNanoKde = $postNanoKde;
            $usProdleva = $postProdleva;
            $usPismo = $postPismo;
            $usDark = $postDark;
            $usLogoutLimit = $postLogoutLimit;
            $usOk = 'Nastavení bylo uloženo.';
        }
    } catch (Throwable $e) {
        $usError = 'Načtení uživatelského nastavení selhalo.';
    }
} else {
    $usError = 'Nastavení je dostupné až po přihlášení.';
}

$usNanoText = ($usNanoKde === 1) ? 'Do gridu' : 'Řádek';
$usDarkText = ($usDark === 1) ? 'Ano' : 'Ne';

$card_min_html = ''
    . '<p class="card_mini_text txt_seda">Nano karty: <span class="text_tucny">' . h($usNanoText) . '</span></p>'
    . '<p class="card_mini_text txt_seda">Velikost textu: <span class="text_tucny">' . h((string)$usPismo) . '</span> | Tmavý režim: <span class="text_tucny">' . h($usDarkText) . '</span></p>';

ob_start();
?>
<?php if ($usError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($usError) ?></p>
<?php else: ?>
  <?php if ($usOk !== ''): ?>
    <p class="card_text txt_zelena odstup_vnejsi_0"><?= h($usOk) ?></p>
  <?php endif; ?>
  <form method="post" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-user-setting-form="1" data-cb-refresh-dashboard-on-save="1" data-cb-user-setting-initial-nano-kde="<?= h((string)$usNanoKde) ?>" data-cb-user-setting-initial-prodleva="<?= h((string)$usProdleva) ?>" data-cb-user-setting-initial-pismo="<?= h((string)$usPismo) ?>" data-cb-user-setting-initial-dark="<?= h((string)$usDark) ?>" data-cb-user-setting-initial-logout-limit="<?= h(($usCanManager && $usLogoutLimit !== null) ? (string)$usLogoutLimit : '') ?>">
    <input type="hidden" name="us_action" value="save">

    <style>
      .cb_user_tabs{display:flex;gap:8px;flex-wrap:wrap;}
      .cb_user_tabs label{padding:4px 10px;border:1px solid #d0d0d0;background:#fff;cursor:pointer;}
      .cb_user_panel{display:none;}
      #cb_user_tab_dashboard:checked ~ .cb_user_tabs label[for="cb_user_tab_dashboard"],
      #cb_user_tab_vzhled:checked ~ .cb_user_tabs label[for="cb_user_tab_vzhled"],
      #cb_user_tab_manager:checked ~ .cb_user_tabs label[for="cb_user_tab_manager"]{font-weight:700;}
      #cb_user_tab_dashboard:checked ~ .cb_user_panel_dashboard,
      #cb_user_tab_vzhled:checked ~ .cb_user_panel_vzhled,
      #cb_user_tab_manager:checked ~ .cb_user_panel_manager{display:block;}
    </style>
    <input type="radio" id="cb_user_tab_dashboard" name="cb_user_tab" checked hidden>
    <input type="radio" id="cb_user_tab_vzhled" name="cb_user_tab" hidden>
    <?php if ($usCanManager): ?>
      <input type="radio" id="cb_user_tab_manager" name="cb_user_tab" hidden>
    <?php endif; ?>
    <div class="cb_user_tabs">
      <label for="cb_user_tab_dashboard" class="zaobleni_6">Dashboard</label>
      <label for="cb_user_tab_vzhled" class="zaobleni_6">Vzhled</label>
      <?php if ($usCanManager): ?>
        <label for="cb_user_tab_manager" class="zaobleni_6">Manager</label>
      <?php endif; ?>
    </div>

    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 cb_user_panel cb_user_panel_dashboard">
      <h4 class="card_section_title txt_seda">Dashboard</h4>
      <label class="card_field gap_4 displ_flex">
        <span>Nano karty</span>
        <select class="card_select ram_sedy txt_seda vyska_32" name="us_nano_kde" data-cb-user-setting-field="1">
          <option value="0"<?= $usNanoKde === 0 ? ' selected' : '' ?>>0 = řádek</option>
          <option value="1"<?= $usNanoKde === 1 ? ' selected' : '' ?>>1 = do gridu</option>
        </select>
      </label>

      <label class="card_field gap_4 displ_flex">
        <span>Volba čekání při volbě období</span>
        <select class="card_select ram_sedy txt_seda vyska_32" name="us_prodleva" data-cb-user-setting-field="1">
          <?php foreach ($usProdlevaOptions as $prodlevaMs => $prodlevaLabel): ?>
            <option value="<?= h((string)$prodlevaMs) ?>"<?= $usProdleva === $prodlevaMs ? ' selected' : '' ?>><?= h($prodlevaLabel) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="card_actions gap_8 displ_flex jc_konec">
        <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-user-setting-save="dashboard" disabled>Uložit nastavení</button>
      </div>
    </section>

    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 cb_user_panel cb_user_panel_vzhled">
      <h4 class="card_section_title txt_seda">Vzhled</h4>
      <label class="card_field gap_4 displ_flex">
        <span>Velikost textu</span>
        <select class="card_select ram_sedy txt_seda vyska_32" name="us_pismo" data-cb-user-setting-field="1">
          <option value="1"<?= $usPismo === 1 ? ' selected' : '' ?>>1 = menší</option>
          <option value="2"<?= $usPismo === 2 ? ' selected' : '' ?>>2 = výchozí</option>
          <option value="3"<?= $usPismo === 3 ? ' selected' : '' ?>>3 = větší</option>
        </select>
      </label>

      <label class="card_field gap_4 displ_flex">
        <span>Tmavý režim</span>
        <select class="card_select ram_sedy txt_seda vyska_32" name="us_dark" data-cb-user-setting-field="1">
          <option value="0"<?= $usDark === 0 ? ' selected' : '' ?>>0 = ne</option>
          <option value="1"<?= $usDark === 1 ? ' selected' : '' ?>>1 = ano</option>
        </select>
      </label>
      <div class="card_actions gap_8 displ_flex jc_konec">
        <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-user-setting-save="vzhled" disabled>Uložit nastavení</button>
      </div>
    </section>

    <?php if ($usCanManager): ?>
      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 cb_user_panel cb_user_panel_manager">
        <h4 class="card_section_title txt_seda">Manager</h4>
        <label class="card_field gap_4 displ_flex">
          <span>Automatické odhlášení při nečinnosti</span>
          <select class="card_select ram_sedy txt_seda vyska_32" name="us_logout_limit" data-cb-user-setting-field="1">
            <option value=""<?= $usLogoutLimit === null ? ' selected' : '' ?>>Původní nastavení systému</option>
            <?php foreach ($usLogoutLimitOptions as $logoutLimitMin => $logoutLimitLabel): ?>
              <option value="<?= h((string)$logoutLimitMin) ?>"<?= $usLogoutLimit === $logoutLimitMin ? ' selected' : '' ?>><?= h($logoutLimitLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="card_actions gap_8 displ_flex jc_konec">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-user-setting-save="manager" disabled>Uložit nastavení</button>
        </div>
      </section>
    <?php endif; ?>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
