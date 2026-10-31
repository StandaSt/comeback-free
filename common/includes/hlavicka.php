<?php
/*
 * Ucel souboru: Vykresli viditelnou hlavicku aplikace z predem pripravenych dat.
 * Data pripravi common/lib/priprava_hlavicky.php; osobni stitky obnovuje hlavicka_stitky.js.
 * Pruh prebranych prav je soucasti trvale hlavicky, aby nezmizel pri vymene modulu.
 * E-mail ma vlastni prepinac a pocet neprectenych zprav jen s pravem 800.
 * Co-Link se prepina do PP ikonou Co s indikaci neprectenych zprav; ikona je viditelna pouze s pravem 700.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/priprava_hlavicky.php';
require_once __DIR__ . '/../lib/prebrani_prav.php';
$cbHeaderModuleAllowed = [
    'provoz' => cb_modul_ma_pristup('provoz'),
    'hr' => cb_modul_ma_pristup('hr'),
    'smeny' => cb_modul_ma_pristup('smeny'),
    'ukoly' => cb_modul_ma_pristup('ukoly'),
    'helpdesk' => cb_modul_ma_pristup('helpdesk'),
];
$cbHeaderModuleDeniedText = 'Tento modul nyní nemáte povolen.';
$cbHeaderCoLinkAllowed = $cbLoginOk && array_key_exists(700, $_SESSION['prava_stav'] ?? []) && cb_pravo_ma(700);
$cbHeaderEmailAllowed = $cbLoginOk && cb_modul_ma_pristup('email');
$cbHeaderMaintenanceActive = $cbLoginOk
    && is_file(__DIR__ . '/../udrzba_on.php')
    && function_exists('cb_session_admin_pro_udrzbu')
    && cb_session_admin_pro_udrzbu();
?>
<header class="blok_hlavicka sirka100<?= $cbHeaderMaintenanceActive ? ' cb-maintenance-active' : '' ?><?= $cbHeaderCoLinkAllowed ? ' cb-has-colink' : '' ?><?= $cbHeaderEmailAllowed ? ' cb-has-email' : '' ?>">

    <?php if ($cbLoginOk): ?>
      <div class="cb_orientation_notice" role="status" aria-live="polite">
        <strong>Otočte zařízení na šířku</strong>
        <span>Pro pohodlné používání informačního systému je potřeba širší pracovní plocha.</span>
      </div>
      <button type="button" class="cb_menu_toggle" data-cb-menu-toggle="1" aria-controls="cb-main-menu" aria-expanded="false" aria-label="Otevřít menu">
        <span aria-hidden="true">☰</span>
      </button>
      <button type="button" class="cb_menu_backdrop" data-cb-menu-backdrop="1" aria-label="Zavřít menu" tabindex="-1"></button>
    <?php endif; ?>

    <?php require __DIR__ . '/hlavicka/head_logo.php'; ?>
    <div class="head_brand_time" aria-label="Aktuální datum a čas">
      <span class="head_subtitle"><?= $cbHeaderMaintenanceActive ? 'ÚDRŽBA ZAPNUTA' : 'informační systém' ?></span>
      <span class="head_date_today" data-cb-head-date><?= h($cbHeaderDateText) ?></span>
      <time class="head_time_now" datetime="<?= h($cbHeaderNow->format(DateTimeInterface::ATOM)) ?>" data-cb-head-time><?= h($cbHeaderTimeText) ?></time>
    </div>

    <?php if ($cbLoginOk): ?>
      <nav class="head_module_nav" aria-label="Moduly">
        <a class="head_module_link head_module_link--provoz<?= $cbCurrentModule === 'provoz' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['provoz'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="provoz" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['provoz'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['provoz'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['provoz'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['provoz'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>><span class="head_module_name">Provoz</span><span class="head_module_tags" data-cb-header-tags="provoz"></span></a>
        <a class="head_module_link head_module_link--hr<?= $cbCurrentModule === 'hr' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['hr'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="hr" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['hr'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['hr'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['hr'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['hr'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>><span class="head_module_name">HR</span><span class="head_module_tags" data-cb-header-tags="hr"></span></a>
        <a class="head_module_link head_module_link--smeny<?= $cbCurrentModule === 'smeny' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['smeny'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="smeny" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['smeny'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['smeny'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['smeny'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['smeny'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>><span class="head_module_name">Směny</span><span class="head_module_tags" data-cb-header-tags="smeny"></span></a>
      </nav>

      <button type="button" class="head_task_btn head_task_btn--todo<?= $cbCurrentModule === 'ukoly' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['ukoly'] ? ' is-disabled' : '' ?>" data-cb-module-link="1" data-cb-module="ukoly" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['ukoly'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['ukoly'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['ukoly'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['ukoly'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>
        <span class="head_module_name">Úkoly</span>
        <span class="head_module_tags" data-cb-header-tags="ukoly"></span>
      </button>
      <button type="button" class="head_task_btn head_task_btn--helpdesk<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' is-disabled' : '' ?>" data-cb-module-link="1" data-cb-module="helpdesk" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>
        <span class="head_module_name">HelpDesk</span>
        <span class="head_module_tags" data-cb-header-tags="helpdesk"></span>
      </button>


      <?php if ($cbHeaderEmailAllowed): ?>
        <a class="head_module_link head_module_link--email<?= $cbCurrentModule === 'email' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('index.php?m=email')) ?>" data-cb-module-link="1" data-cb-module="email" data-cb-email-api="<?= h(cb_root_url('email/api.php')) ?>" aria-label="E-mail" title="E-mail"><span aria-hidden="true">✉</span><span class="head_colink_unread" data-cb-email-unread hidden></span></a>
      <?php endif; ?>
      <?php if ($cbHeaderCoLinkAllowed): ?>
        <a class="head_module_link head_module_link--colink<?= $cbCurrentModule === 'co_link' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('index.php?m=co_link')) ?>" data-cb-module-link="1" data-cb-module="co_link" aria-label="Co-Link" title="Co-Link"><span class="head_colink_icon" aria-hidden="true">Co</span><span class="head_colink_unread" data-cb-colink-unread hidden aria-hidden="true"></span></a>
      <?php endif; ?>

      <?php if ($cbHeaderMaintenanceActive): ?>
        <div class="head_update head_maintenance_status" role="status" aria-label="Údržba IS je zapnutá">
          <strong class="head_maintenance_long">ÚDRŽBA ZAPNUTA</strong>
          <strong class="head_maintenance_short">ÚDRŽBA</strong>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="head_guest ram_hlavicka bg_bila zaobleni_12"></div>
    <?php endif; ?>

<?php if (($cbTestProfile = cb_prebrani_prav_profil()) !== null): ?>
<aside class="cb_prebrani_prav_notice" role="status">
    <strong>Přebraná práva: <?= h($cbTestProfile['jmeno']) ?> (ID <?= h((string)$cbTestProfile['id_user_zdroj']) ?>)</strong>
    <span>Přihlášený uživatel a autor změn: <?= h((string)($_SESSION['cb_user']['name'] ?? '')) ?> — ID 1.</span>
    <form method="post" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="cb_action" value="admin_prebrani_prav_opustit">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <button type="submit">Opustit přebrání práv a odhlásit</button>
    </form>
</aside>
<?php endif; ?>
</header>
<!-- Skript prubezne obnovuje datum a cas v hlavicce. -->
<script>
(function () {
  var dateBox = document.querySelector('[data-cb-head-date]');
  var timeBox = document.querySelector('[data-cb-head-time]');
  var days = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];

  // Doplni nulu pred jednociferny casovy udaj.
  function pad(value) {
    return String(value).padStart(2, '0');
  }

  // Prepise zobrazeny cas podle hodin prohlizece.
  function refreshHeaderClock() {
    var now = new Date();
    if (dateBox instanceof HTMLElement) {
      dateBox.textContent = days[now.getDay()] + ' ' + now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
    }
    if (timeBox instanceof HTMLElement) {
      timeBox.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
      timeBox.setAttribute('datetime', now.toISOString());
    }
  }

  refreshHeaderClock();
  window.setInterval(refreshHeaderClock, 1000);
})();
</script>
<?php if ($cbLoginOk): ?>
<script>
/* Po dokonceni Restie obnovi cas v titulku Prehledu Provozu. */
(function () {
  function refreshRestiaTime() {
    var value = document.querySelector('[data-cb-orders-update-time]');
    if (!(value instanceof HTMLElement)) { return; }

    fetch(String(window.CB_ENDPOINT || window.location.href), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Comeback-Restia-Refresh-Time': '1', 'Accept': 'application/json' }
    })
      .then(function (response) {
        if (!response.ok) { throw new Error('HTTP ' + response.status); }
        return response.json();
      })
      .then(function (data) {
        var time = String(data && data.time ? data.time : '');
        if (data && data.ok === true && /^\d{2}:\d{2}:\d{2}$/.test(time)) {
          value.textContent = time;
        }
      })
      .catch(function () {
      });
  }

  window.addEventListener('cb:restia-finished', refreshRestiaTime);
})();
</script>
<?php endif; ?>
<?php if ($cbLoginOk): ?>
  <!-- Štítky sdílí obnovu a tooltipy; každý modul dodává pouze povolené osobní informace. -->
  <script src="<?= h(cb_public_url('js/hlavicka_stitky.js') . '?v=' . (string)filemtime(__DIR__ . '/../js/hlavicka_stitky.js')) ?>" defer></script>
<?php endif; ?>
