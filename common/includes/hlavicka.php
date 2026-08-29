<?php
/*
 * Ucel souboru: Vykresli viditelnou hlavicku aplikace z predem pripravenych dat.
 * Data pripravi common/lib/priprava_hlavicky.php; vybery jsou samostatne komponenty.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/priprava_hlavicky.php';
$cbHeaderModuleAllowed = [
    'provoz' => cb_modul_ma_pristup('provoz'),
    'hr' => cb_modul_ma_pristup('hr'),
    'smeny' => cb_modul_ma_pristup('smeny'),
    'ukoly' => cb_modul_ma_pristup('ukoly'),
    'helpdesk' => cb_modul_ma_pristup('helpdesk'),
];
$cbHeaderModuleDeniedText = 'Tento modul nyní nemáte povolen.';
?>
<header class="blok_hlavicka sirka100">

    <?php require __DIR__ . '/hlavicka/head_logo.php'; ?>
    <div class="head_brand_time" aria-label="Aktuální datum a čas">
      <span class="head_subtitle">informační systém</span>
      <span class="head_date_today" data-cb-head-date><?= h($cbHeaderDateText) ?></span>
      <time class="head_time_now" datetime="<?= h($cbHeaderNow->format(DateTimeInterface::ATOM)) ?>" data-cb-head-time><?= h($cbHeaderTimeText) ?></time>
    </div>

    <?php if ($cbLoginOk): ?>
      <nav class="head_module_nav" aria-label="Moduly">
        <a class="head_module_link head_module_link--provoz<?= $cbCurrentModule === 'provoz' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['provoz'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="provoz" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['provoz'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['provoz'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['provoz'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['provoz'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>Provoz</a>
        <a class="head_module_link head_module_link--hr<?= $cbCurrentModule === 'hr' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['hr'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="hr" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['hr'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['hr'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['hr'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['hr'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>HR</a>
        <a class="head_module_link head_module_link--smeny<?= $cbCurrentModule === 'smeny' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['smeny'] ? ' is-disabled' : '' ?>" href="<?= h(cb_root_url('')) ?>" data-cb-module-link="1" data-cb-module="smeny" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['smeny'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['smeny'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['smeny'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['smeny'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>Směny</a>
      </nav>

      <button type="button" class="head_task_btn head_task_btn--todo<?= $cbCurrentModule === 'ukoly' ? ' is-active' : '' ?><?= !$cbHeaderModuleAllowed['ukoly'] ? ' is-disabled' : '' ?>" data-cb-module-link="1" data-cb-module="ukoly" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['ukoly'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['ukoly'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['ukoly'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['ukoly'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>
        <span>Úkoly</span>
        <strong class="head_task_count">0</strong>
      </button>
      <?php if ($cbHelpdeskIsRoleOne): ?>
        <button type="button" class="head_task_btn head_task_btn--helpdesk<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' is-disabled' : '' ?>" data-cb-module-link="1" data-cb-module="helpdesk" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>
          <span>HelpDesk</span>
          <strong class="head_task_count" data-cb-helpdesk-header-count="all">0</strong>
        </button>
      <?php else: ?>
        <button type="button" class="head_task_btn head_task_btn--helpdesk<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' is-disabled' : '' ?>" data-cb-module-link="1" data-cb-module="helpdesk" data-cb-module-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" data-cb-tooltip-warning="<?= $cbHeaderModuleAllowed['helpdesk'] ? '0' : '1' ?>" aria-disabled="<?= $cbHeaderModuleAllowed['helpdesk'] ? 'false' : 'true' ?>"<?= !$cbHeaderModuleAllowed['helpdesk'] ? ' title="' . h($cbHeaderModuleDeniedText) . '"' : '' ?>>
          <span>HelpDesk</span>
          <strong class="head_task_count" data-cb-helpdesk-header-count="all">0</strong>
        </button>
      <?php endif; ?>

      <?php require __DIR__ . '/vyber_pobocek.php'; ?>
      <?php require __DIR__ . '/vyber_obdobi.php'; ?>

      <?php if ($cbCurrentModule === 'provoz'): ?>
        <div class="head_update" aria-label="Aktualizace dat" data-cb-head-update="1">
          <span class="head_update_icon" aria-hidden="true">⟳</span>
          <span>
            <span class="head_block_label">Aktualizace dat</span>
            <strong class="head_update_value"><?= h($cbHeadAktualizaceDat) ?></strong>
          </span>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="head_guest ram_hlavicka bg_bila zaobleni_12"></div>
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
  <!-- Skript nacita a zobrazuje pocty tiketu Helpdesk. -->
  <script>
  (function () {
    var apiUrl = <?= json_encode($cbHelpdeskApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    // Najde prvek pro zadany pocet tiketu.
    function countBox(key) {
      return document.querySelector('[data-cb-helpdesk-header-count="' + key + '"]');
    }

    // Prevede hodnotu na nezaporne cele cislo.
    function numberValue(value) {
      var n = Number(value || 0);
      if (!Number.isFinite(n) || n < 0) {
        return 0;
      }
      return Math.trunc(n);
    }

    // Prepise vsechny viditelne pocty tiketu.
    function setCounts(counts) {
      var source = counts || {};
      ['all', 'new', 'active', 'resolved'].forEach(function (key) {
        var box = countBox(key);
        if (box instanceof HTMLElement) {
          box.textContent = String(numberValue(source[key]));
        }
      });
    }

    // Nacte aktualni pocty tiketu z Helpdesku.
    function refresh() {
      var moduleName = window.CB_HELPDESK_SOURCE_MODULE || window.CB_ACTIVE_MAIN_MODULE || 'provoz';
      return fetch(apiUrl + '?helpdesk_action=stav_tiketu&cb_helpdesk_module=' + encodeURIComponent(String(moduleName)), {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'X-Comeback-Helpdesk': '1'
        }
      })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
          if (data && data.ok === true && data.counts) {
            setCounts(data.counts);
          }
        })
        .catch(function () {
        });
    }

    // Otevre Helpdesk s pozadovanym filtrem.
    function openHelpdesk(filter) {
      var filterValue = String(filter || 'all');
      if (window.CB_HELPDESK && typeof window.CB_HELPDESK.openUnreadFilter === 'function') {
        window.CB_HELPDESK.openUnreadFilter(filterValue);
        return;
      }

      window.CB_HELPDESK_PENDING_FILTER = filterValue;

      if (typeof window.CB_LOAD_MODULE === 'function') {
        window.CB_LOAD_MODULE('helpdesk', true);
      }
    }

    // Umiesti napovedu k polozce Helpdesku.
    function placeTooltip(root, event) {
      var panel = root.querySelector('[data-cb-helpdesk-head-tooltip="1"]');
      if (!(panel instanceof HTMLElement)) { return; }
      panel.classList.add('is-visible');

      var rect = root.getBoundingClientRect();
      var clientX = event && typeof event.clientX === 'number' ? event.clientX : rect.right;
      var clientY = event && typeof event.clientY === 'number' ? event.clientY : rect.top;
      var panelRect = panel.getBoundingClientRect();
      var gap = 8;
      var left = clientX + 14;
      var top = clientY - panelRect.height - gap;
      var viewWidth = window.innerWidth || document.documentElement.clientWidth || 0;

      if (left + panelRect.width + gap > viewWidth) {
        left = Math.max(gap, viewWidth - panelRect.width - gap);
      }
      if (top < gap) {
        top = clientY + gap;
      }

      panel.style.left = String(left) + 'px';
      panel.style.top = String(top) + 'px';
    }

    // Skryje napovedu k polozce Helpdesku.
    function hideTooltip(root) {
      var panel = root.querySelector('[data-cb-helpdesk-head-tooltip="1"]');
      if (!(panel instanceof HTMLElement)) { return; }
      panel.classList.remove('is-visible');
      panel.style.left = '';
      panel.style.top = '';
    }

    window.CB_HELPDESK_HEADER = {
      refresh: refresh,
      open: openHelpdesk
    };

    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!(target instanceof Element)) { return; }
      var item = target.closest('[data-cb-helpdesk-header-filter]');
      if (!(item instanceof HTMLElement)) { return; }
      openHelpdesk(item.getAttribute('data-cb-helpdesk-header-filter') || 'all');
    });

    document.addEventListener('mouseenter', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, e);
      }
    }, true);

    document.addEventListener('mousemove', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, e);
      }
    }, true);

    document.addEventListener('mouseleave', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      var related = e.relatedTarget;
      if (item instanceof HTMLElement && !(related instanceof Node && item.contains(related))) {
        hideTooltip(item);
      }
    }, true);

    document.addEventListener('focusin', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      if (item instanceof HTMLElement) {
        placeTooltip(item, null);
      }
    });

    document.addEventListener('focusout', function (e) {
      var target = e.target;
      var item = target instanceof Element ? target.closest('[data-cb-helpdesk-header-filter]') : null;
      var related = e.relatedTarget;
      if (item instanceof HTMLElement && !(related instanceof Node && item.contains(related))) {
        hideTooltip(item);
      }
    });

    refresh();
  })();
  </script>
<?php endif; ?>
