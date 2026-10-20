/* Osobní štítky hlavičky: bezpečný textový výstup, tooltipy a čas od úspěšných importů podle serverových hodin. */
(function () {
  'use strict';
  var endpoint = window.CB_ENDPOINT || 'index.php';
  var serverTime = 0;
  var receivedAt = 0;
  var pending = null;

  // Při obnově nevkládáme HTML ze serveru; i tabulka tooltipu používá pouze textové buňky.
  function render(data) {
    serverTime = Number(data.server_ts);
    receivedAt = performance.now();
    document.querySelectorAll('[data-cb-header-tags]').forEach(function (row) {
      row.replaceChildren();
      var tags = data.modules && data.modules[row.dataset.cbHeaderTags] || [];
      tags.slice(0, 2).forEach(function (tag) {
        var span = document.createElement('span');
        span.className = 'head_module_tag';
        span.tabIndex = 0;
        span.textContent = String(tag.text || '');
        span.title = String(tag.tooltip || '');
        if (Array.isArray(tag.table) && tag.table.length) span.dataset.cbTooltipTable = JSON.stringify(tag.table);
        if (Number(tag.updated_at) > 0) span.dataset.headerUpdatedAt = String(tag.updated_at);
        row.appendChild(span);
      });
    });
    tick();
    if (window.CB_TOOLTIP) window.CB_TOOLTIP.init(document.querySelector('.blok_hlavicka'));
  }

  // Počítání od serverového času se nerozbije nesprávně nastavenými hodinami zařízení.
  function tick() {
    if (!serverTime) return;
    var now = serverTime + (performance.now() - receivedAt) / 1000;
    document.querySelectorAll('[data-header-updated-at]').forEach(function (tag) {
      var seconds = Math.max(0, Math.floor(now - Number(tag.dataset.headerUpdatedAt)));
      var hours = Math.floor(seconds / 3600);
      var text = hours ? hours + ' h ' : '';
      tag.textContent = 'Data ' + text + Math.floor(seconds / 60) % 60 + ' min ' + seconds % 60 + ' s';
    });
  }

  // Jedna společná obnova je sdílená událostmi modulů a pravidelným načítáním.
  function refresh() {
    if (pending) return pending;
    var url = new URL(endpoint, window.location.href);
    url.searchParams.set('cb_header_tags', '1');
    pending = fetch(url.toString(), {credentials:'same-origin',cache:'no-store',headers:{'X-Comeback-Header-Tags':'1','Accept':'application/json'}})
      .then(function (response) { if (!response.ok) throw new Error('Štítky nejsou dostupné.'); return response.json(); })
      .then(function (data) { if (!data.ok) throw new Error('Štítky nejsou dostupné.'); render(data); })
      .catch(function () {
        document.querySelectorAll('[data-cb-header-tags]').forEach(function (row) { row.replaceChildren(); });
      })
      .finally(function () { pending = null; });
    return pending;
  }

  // Zachováváme veřejné rozhraní HelpDesku pro obnovu po přečtení nebo odeslání zprávy.
  window.CB_HELPDESK_HEADER = {
    refresh: refresh,
    open: function (filter) {
      if (window.CB_HELPDESK && window.CB_HELPDESK.openUnreadFilter) window.CB_HELPDESK.openUnreadFilter(filter || 'all');
      else { window.CB_HELPDESK_PENDING_FILTER = filter || 'all'; if (window.CB_LOAD_MODULE) window.CB_LOAD_MODULE('helpdesk', true); }
    }
  };
  document.addEventListener('cb:main-swapped', refresh);
  window.addEventListener('cb:restia-finished', refresh);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
  window.setInterval(function () { if (!document.hidden) refresh(); }, 60000);
  window.setInterval(tick, 1000);
  refresh();
}());
