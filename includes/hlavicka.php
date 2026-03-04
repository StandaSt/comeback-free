<?php
// includes/hlavicka.php * Verze: V39 * Aktualizace: 04.03.2026
declare(strict_types=1);

/*
 * HLAVIČKA (dashboard)
 *
 * Bloky:
 * - logo
 * - období
 * - KPI
 * - TECH
 * - menu
 * - stav systému
 * - user blok
 */

$cbLoginOk = !empty($_SESSION['login_ok']);

$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserName = 'Uživatel';
$cbUserRole = '—';

if (is_array($cbUser)) {
    $fullName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    if ($fullName !== '') {
        $cbUserName = $fullName;
    } else {
        $cbUserName = (string)($cbUser['jmeno'] ?? $cbUser['email'] ?? $cbUser['login'] ?? $cbUserName);
    }

    $cbUserRole = (string)($cbUser['role'] ?? $cbUser['nazev_role'] ?? $cbUserRole);
}

/* stavové semafory – dočasně (napojení později) */
$sysDb = 'ok';
$sysSmeny = 'ok';
$sysRestia = 'var';

?>
<header class="head_box">
  <div class="head_grid">

    <a class="head_logo" href="<?= h(cb_url('')) ?>" aria-label="PizzaComeback">
      <img class="head_logo_img" src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
    </a>

    <?php if ($cbLoginOk): ?>

      <div class="head_top" aria-label="Horní řádek hlavičky">
        <div class="head_interval" aria-label="Období">
          <div class="head_int_row">
            <label class="head_date">
              <span>Od</span>
              <input type="date" value="">
            </label>
            <div class="head_quick">
              <button type="button" class="head_pill is-on">Včera</button>
              <button type="button" class="head_pill">Týden</button>
            </div>
          </div>

          <div class="head_int_row">
            <label class="head_date">
              <span>Do</span>
              <input type="date" value="">
            </label>
            <div class="head_quick">
              <button type="button" class="head_pill">Měsíc</button>
              <button type="button" class="head_pill">Rok</button>
            </div>
          </div>
        </div>

        <div class="head_kpi" aria-label="KPI">
          <div class="head_kpi_item">
            <div class="head_kpi_k">Tržba</div>
            <div class="head_kpi_v">152&nbsp;000 Kč <span class="head_delta is-pos">(+5%)</span></div>
          </div>
          <div class="head_kpi_item">
            <div class="head_kpi_k">Zisk</div>
            <div class="head_kpi_v">31&nbsp;600 Kč <span class="head_delta is-pos">(+3%)</span></div>
          </div>
          <div class="head_kpi_item">
            <div class="head_kpi_k">Trend</div>
            <div class="head_kpi_v">+1,2 p.&nbsp;b.</div>
          </div>
          <div class="head_kpi_item">
            <div class="head_kpi_k">Odpracováno</div>
            <div class="head_kpi_v">268 h</div>
          </div>
        </div>
      </div>

      <nav class="head_bottom" aria-label="Dolní řádek hlavičky">
        <div class="head_menu" role="navigation" aria-label="Hlavní menu">
          <button type="button" class="head_menu_btn is-on" onclick="if(window.CB_MENU){CB_MENU.goPage('top_dashboard');}">Dashboard</button>
          <button type="button" class="head_menu_btn" onclick="if(window.CB_MENU){CB_MENU.goPage('reporty_porovnani');}">Reporty</button>
          <button type="button" class="head_menu_btn" onclick="if(window.CB_MENU){CB_MENU.goPage('hr_uzivatele');}">Lidi</button>
          <button type="button" class="head_menu_btn" onclick="if(window.CB_MENU){CB_MENU.goPage('admin_logs');}">Admin</button>
        </div>

        <div class="head_sys" aria-label="Stav systému">
          <div class="head_sys_title">Stav systému</div>
          <button type="button" class="head_tech cb-tip" data-tip="TECH" aria-label="TECH">
            <span aria-hidden="true">⚙</span>
          </button>
          <div class="head_sys_line">
            <span class="head_sys_item"><span class="head_sys_lab">DB</span><span class="head_led is-<?= h($sysDb) ?>" aria-hidden="true"></span></span>
            <span class="head_sys_item"><span class="head_sys_lab">Směny</span><span class="head_led is-<?= h($sysSmeny) ?>" aria-hidden="true"></span></span>
            <span class="head_sys_item"><span class="head_sys_lab">Restia</span><span class="head_led is-<?= h($sysRestia) ?>" aria-hidden="true"></span></span>
          </div>
        </div>
      </nav>

      <div class="head_user">
        <div class="head_user_col head_user_col--left">
          <div class="head_user_name"><strong><?= h($cbUserName) ?></strong></div>
          <div class="head_user_lab">Poslední přístup</div>
          <div class="head_user_lab">Přihlášení</div>
          <div class="head_user_lab">Seance</div>
          <div class="head_user_lab">Do konce</div>
        </div>

        <div class="head_user_col head_user_col--right">
          <div class="head_user_role"><?= h($cbUserRole) ?></div>
          <div class="head_user_val">1.3.2026 08:35</div>
          <div class="head_user_val">celkem 87× / dnes 2×</div>
          <div class="head_user_val">0 min / zbývá 20 min</div>
          <div class="head_user_val" data-thermo="65" style="--thermo:65%">&nbsp;</div>
        </div>

        <a class="head_user_exit cb-tip" data-tip="Odhlásit" href="<?= h(cb_url('lib/logout.php')) ?>" aria-label="Odhlásit">
          <svg class="head_user_exit_ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
        </a>
      </div>

    <?php else: ?>

      <div class="head_guest"></div>

    <?php endif; ?>

  </div>
</header>


<?php
// includes/hlavicka.php * Verze: V39 * Aktualizace: 04.03.2026 * Počet řádků: 150
// konec souboru
?>