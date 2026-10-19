// Sdílený panel místního období; změna obnoví pouze vlastní blok a nikdy nepřenáší filtr do jiné stránky.
(function () {
  'use strict';
  var timers = new WeakMap();

  // Každý panel se inicializuje z vlastního modelu i po částečné výměně stránky.
  function init(scope) {
    var roots = Array.prototype.slice.call(scope.querySelectorAll('[data-cb-period-root]'));
    if (scope.matches && scope.matches('[data-cb-period-root]')) roots.push(scope);
    roots.forEach(function (root) {
      root.querySelectorAll('input[type="date"]').forEach(function (input) { input.max = root.dataset.periodMaxDate; });
      root.querySelectorAll('[data-range]').forEach(function (button) { button.classList.toggle('is-on', button.dataset.range === root.dataset.activeMode); });
    });
  }
  // Odložená změna odpojeného panelu se po přechodu jinam nesmí sama uložit.
  function cancel(root) {
    clearTimeout(timers.get(root)); timers.delete(root);
    var meter = root.querySelector('.head_interval_meter');
    if (meter) meter.classList.remove('is-active');
  }
  function close(root) {
    root.querySelector('[data-cb-period-panel]').classList.add('is-hidden');
    root.querySelector('[data-cb-period-toggle]').setAttribute('aria-expanded', 'false');
  }
  // Zamknutí ovladačů do odpovědi brání souběžnému uložení dvou různých voleb.
  async function save(root, payload, refresh) {
    if (!root.isConnected || root.dataset.saving === '1') return;
    cancel(root); root.dataset.saving = '1';
    var enabled = Array.prototype.slice.call(root.querySelectorAll('input, select, button')).filter(function (control) { return !control.disabled; });
    enabled.forEach(function (control) { control.disabled = true; });
    try {
      payload.scope = root.dataset.periodScope;
      var response = await fetch(root.dataset.saveUrl, {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-Comeback-Page-Period':'1','X-Comeback-Crf':window.CB_CRF_TOKEN || ''},
        body:JSON.stringify(payload)
      });
      var result = await window.CB_CHYBY.readJson(response, 'Uložení období se nepodařilo.');
      if (!result.ok) throw new Error(result.message);
      var period = result.data.period;
      if (!period || period.scope !== root.dataset.periodScope) throw new Error('Neplatná odpověď výběru období.');
      root.dataset.activeMode = period.mode;
      root.dataset.manualSaveDelayMs = String(period.delay);
      root.querySelector('[data-cb-period-summary]').textContent = period.label;
      root.querySelector('[data-period-field="od"]').value = period.input_od;
      root.querySelector('[data-period-field="do"]').value = period.input_do;
      root.querySelector('[data-cb-prodleva-value]').textContent = String(period.delay / 1000) + ' sec.';
      if (refresh) {
        close(root);
        // Další volba smí přijít až po výměně dat, aby se odpovědi dvou rozsahů nemohly předběhnout.
        var detail = {root:root};
        document.dispatchEvent(new CustomEvent('cb:period-changed', {detail:detail}));
        if (detail.refreshPromise) await detail.refreshPromise;
      }
    } catch (error) {
      if (payload.action === 'delay') root.querySelector('[data-cb-prodleva-range]').value = String(Number(root.dataset.manualSaveDelayMs) / 1000);
      window.alert(window.CB_CHYBY.errorMessage(error));
    } finally {
      root.dataset.saving = '0'; enabled.forEach(function (control) { control.disabled = false; }); init(root);
    }
  }
  // Kontrola posílá datumy včetně, ostatní stránky přesný začátek a výhradní konec.
  function manualPayload(root) {
    var from = root.querySelector('[data-period-field="od"]').value;
    var to = root.querySelector('[data-period-field="do"]').value;
    if (root.dataset.periodDays !== '1') {
      from += ' ' + root.querySelector('[data-period-field="od_time"]').value;
      to += ' ' + root.querySelector('[data-period-field="do_time"]').value;
    }
    return {mode:'manual',od:from,do:to};
  }
  document.addEventListener('click', function (event) {
    var root = event.target.closest('[data-cb-period-root]');
    document.querySelectorAll('[data-cb-period-root]').forEach(function (other) { if (other !== root) close(other); });
    if (!root) return;
    var toggle = event.target.closest('[data-cb-period-toggle]');
    if (toggle) {
      var panel = root.querySelector('[data-cb-period-panel]');
      var open = panel.classList.contains('is-hidden');
      panel.classList.toggle('is-hidden', !open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        // U pravého okraje se panel posune do viditelného okna.
        panel.style.left = '0px'; var rect = panel.getBoundingClientRect();
        panel.style.left = Math.min(0, window.innerWidth - rect.right - 12) + 'px';
      }
    }
    var quick = event.target.closest('[data-range]');
    if (quick && !quick.disabled) save(root, {mode:quick.dataset.range}, true);
  });
  document.addEventListener('change', function (event) {
    var root = event.target.closest('[data-cb-period-root]');
    if (!root) return;
    if (event.target.matches('[data-cb-prodleva-range]')) { save(root, {action:'delay',delay:Number(event.target.value)*1000}, false); return; }
    if (!event.target.matches('[data-period-field]')) return;
    cancel(root); var delay = Number(root.dataset.manualSaveDelayMs || 3000);
    var meter = root.querySelector('.head_interval_meter'), bar = root.querySelector('.head_interval_meter_bar');
    meter.classList.add('is-active'); bar.style.transitionDuration = '0ms'; bar.style.transform = 'scaleX(0)';
    requestAnimationFrame(function () { bar.style.transitionDuration = delay + 'ms'; bar.style.transform = 'scaleX(1)'; });
    timers.set(root, setTimeout(function () { save(root, manualPayload(root), true); }, delay));
  });
  document.addEventListener('focusin', function (event) {
    var root = event.target.closest('[data-cb-period-root]');
    if (root && event.target.matches('[data-period-field]')) cancel(root);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') document.querySelectorAll('[data-cb-period-root]').forEach(function (root) { cancel(root); close(root); });
  });
  document.addEventListener('cb:main-swapped', function () { init(document); });
  document.addEventListener('cb:gn-block-refreshed', function (event) { init(event.detail.block); });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); }, {once:true});
  else init(document);
}());
