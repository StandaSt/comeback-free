<?php
// includes/hlavicka.php * Verze: V37 * Aktualizace: 2.3.2026
declare(strict_types=1);

/*
 * HLAVIČKA (dashboard) – nový layout
 * - 2 řádky:
 *   (1) Období + KPI (4 stejné) + TECH (ikona vpravo)
 *   (2) Menu + Stav systému (nadpis + 1 řádek DB/Směny/Restia se světýlky)
 * - vpravo user blok (2 sloupce) + úzký logout proužek
 * - bez sidebaru / dropdownu
 */

$cbLoginOk = !empty($_SESSION['login_ok']);

$cbUser = $_SESSION['cb_user'] ?? [];
$cbUserName = 'Uživatel';
$cbUserRole = '—';
if (is_array($cbUser)) {
    $cbUserName = (string)($cbUser['jmeno'] ?? $cbUser['email'] ?? $cbUser['login'] ?? $cbUserName);
    $cbUserRole = (string)($cbUser['role'] ?? $cbUser['nazev_role'] ?? $cbUserRole);
}

/* stavové semafory – dočasně (napojení později) */
$sysDb = 'ok';
$sysSmeny = 'ok';
$sysRestia = 'var';

?>
<header class="cb-header">
  <div class="cb-hdr">

    <a class="cb-brand" href="<?= h(cb_url('')) ?>" aria-label="Domů">
      <img class="cb-logo" src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
    </a>

    <?php if ($cbLoginOk): ?>

      <div class="cb-top">

        <div class="cb-interval" aria-label="Období">
          <div class="cb-int-row">
            <label class="cb-date">
              <span>Od</span>
              <input type="date" value="">
            </label>
            <div class="cb-quick">
              <button type="button" class="cb-pill is-on">Včera</button>
              <button type="button" class="cb-pill">Týden</button>
            </div>
          </div>

          <div class="cb-int-row">
            <label class="cb-date">
              <span>Do</span>
              <input type="date" value="">
            </label>
            <div class="cb-quick">
              <button type="button" class="cb-pill">Měsíc</button>
              <button type="button" class="cb-pill">Rok</button>
            </div>
          </div>
        </div>

        <div class="cb-arrow" aria-hidden="true">→</div>

        <div class="cb-kpi" aria-label="KPI">
          <div class="cb-kpi-item">
            <div class="cb-kpi-k">Tržba</div>
            <div class="cb-kpi-v">152&nbsp;000 Kč <span class="cb-delta is-pos">(+5%)</span></div>
          </div>
          <div class="cb-kpi-item">
            <div class="cb-kpi-k">Zisk</div>
            <div class="cb-kpi-v">31&nbsp;600 Kč <span class="cb-delta is-pos">(+3%)</span></div>
          </div>
          <div class="cb-kpi-item">
            <div class="cb-kpi-k">Trend</div>
            <div class="cb-kpi-v">+1,2 p.&nbsp;b.</div>
          </div>
          <div class="cb-kpi-item">
            <div class="cb-kpi-k">Odpracováno</div>
            <div class="cb-kpi-v">268 h</div>
          </div>
        </div>

        <button type="button" class="cb-tech cb-tip" data-tip="TECH" aria-label="TECH">
          <span aria-hidden="true">⚙</span>
        </button>

      </div>

      <nav class="cb-bottom" aria-label="Menu a stav systému">
        <div class="cb-menu" role="navigation" aria-label="Hlavní menu">
          <button type="button" class="cb-menu-btn is-on" onclick="if(window.CB_MENU){CB_MENU.goPage('top_dashboard');}">Dashboard</button>
          <button type="button" class="cb-menu-btn" onclick="if(window.CB_MENU){CB_MENU.goPage('reporty_porovnani');}">Reporty</button>
          <button type="button" class="cb-menu-btn" onclick="if(window.CB_MENU){CB_MENU.goPage('hr_uzivatele');}">Lidi</button>
          <button type="button" class="cb-menu-btn" onclick="if(window.CB_MENU){CB_MENU.goPage('admin_logs');}">Admin</button>
        </div>

        <div class="cb-sys" aria-label="Stav systému">
          <div class="cb-sys-title">Stav systému</div>
          <div class="cb-sys-line">
            <span class="cb-sys-item"><span class="cb-sys-lab">DB</span><span class="cb-led is-<?= h($sysDb) ?>" aria-hidden="true"></span></span>
            <span class="cb-sys-item"><span class="cb-sys-lab">Směny</span><span class="cb-led is-<?= h($sysSmeny) ?>" aria-hidden="true"></span></span>
            <span class="cb-sys-item"><span class="cb-sys-lab">Restia</span><span class="cb-led is-<?= h($sysRestia) ?>" aria-hidden="true"></span></span>
          </div>
        </div>
      </nav>

      <div class="cb-user">
        <div class="cb-user-col cb-user-col--left">
          <div class="cb-user-name"><strong><?= h($cbUserName) ?></strong></div>
          <div class="cb-user-lab">Poslední přístup</div>
          <div class="cb-user-lab">Přihlášení</div>
          <div class="cb-user-lab">Seance</div>
        </div>

        <div class="cb-user-col cb-user-col--right">
          <div class="cb-user-role"><?= h($cbUserRole) ?></div>
          <div class="cb-user-val">1.3.2026 08:35</div>
          <div class="cb-user-val">celkem 87× / dnes 2×</div>
          <div class="cb-user-val">0 min / zbývá 20 min</div>
        </div>

        <a class="cb-user-exit cb-tip" data-tip="Odhlásit" href="<?= h(cb_url('lib/logout.php')) ?>" aria-label="Odhlásit">
          <svg class="cb-user-exit-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
        </a>
      </div>

    <?php else: ?>

      <div class="cb-guest"></div>

    <?php endif; ?>

  </div>
</header>

<?php
// includes/hlavicka.php * Verze: V37 * Aktualizace: 2.3.2026 * Počet řádků: 140
