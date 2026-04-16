// js/ajax_core.js * Verze: V5 * Aktualizace: 15.04.2026
'use strict';

/*
 * AJAX core - spolecny pomocnik pro fetch a tri oddelene loadery:
 * - dashboard
 * - cards
 * - restia
 */

(function (w) {
  const CB_AJAX = w.CB_AJAX || (w.CB_AJAX = {});
  const LOADER_MODES = ['dashboard', 'cards', 'restia'];

  function createLoaderState() {
    return {
      loading: false,
      timer: null,
      startedAt: 0
    };
  }

  const loaderState = {
    dashboard: createLoaderState(),
    cards: createLoaderState(),
    restia: createLoaderState()
  };

  const AJAX_TRACE_URL = new URL('/comeback/lib/ajax_trace.php', w.location.origin).toString();

  function normalizeLoaderMode(mode) {
    const raw = String(mode || 'dashboard').trim();
    return LOADER_MODES.indexOf(raw) !== -1 ? raw : 'dashboard';
  }

  function getLoaderState(mode) {
    return loaderState[normalizeLoaderMode(mode)];
  }

  function traceAjax(event, data) {
    try {
      const payload = {
        event: String(event || '').trim(),
        data: data && typeof data === 'object' ? data : {},
        href: String(w.location.href || ''),
        path: String(w.location.pathname || ''),
        ts: Date.now()
      };
      if (payload.event === '') {
        return;
      }
      const body = JSON.stringify(payload);
      if (w.navigator && typeof w.navigator.sendBeacon === 'function') {
        w.navigator.sendBeacon(AJAX_TRACE_URL, new Blob([body], { type: 'application/json' }));
        return;
      }
      w.fetch(AJAX_TRACE_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body,
        keepalive: true,
        credentials: 'same-origin'
      }).catch(() => {});
    } catch (e) {}
  }

  function getNavigationEntry() {
    const perf = w.performance || null;
    if (!perf || typeof perf.getEntriesByType !== 'function') {
      return null;
    }
    const navEntries = perf.getEntriesByType('navigation');
    return Array.isArray(navEntries) && navEntries.length > 0 ? navEntries[0] : null;
  }

  function getNavigationSummary(stage) {
    const perf = w.performance || null;
    const nav = getNavigationEntry();
    const legacy = perf && perf.timing ? perf.timing : null;
    const navType = nav ? String(nav.type || '') : '';
    const legacyNavType = perf && perf.navigation ? Number(perf.navigation.type || 0) : 0;
    const isReload = navType === 'reload' || legacyNavType === 1;

    return {
      stage: String(stage || ''),
      nav_type: navType || (legacyNavType === 1 ? 'reload' : ''),
      is_reload: isReload ? 1 : 0,
      dom_content_loaded_ms: nav && typeof nav.domContentLoadedEventEnd === 'number'
        ? Math.max(0, Math.round(nav.domContentLoadedEventEnd))
        : (legacy ? Math.max(0, Math.round(legacy.domContentLoadedEventEnd - legacy.navigationStart)) : 0),
      load_event_ms: nav && typeof nav.loadEventEnd === 'number'
        ? Math.max(0, Math.round(nav.loadEventEnd))
        : (legacy ? Math.max(0, Math.round(legacy.loadEventEnd - legacy.navigationStart)) : 0),
      dom_complete_ms: nav && typeof nav.domComplete === 'number'
        ? Math.max(0, Math.round(nav.domComplete))
        : (legacy ? Math.max(0, Math.round(legacy.domComplete - legacy.navigationStart)) : 0),
      response_end_ms: nav && typeof nav.responseEnd === 'number'
        ? Math.max(0, Math.round(nav.responseEnd))
        : (legacy ? Math.max(0, Math.round(legacy.responseEnd - legacy.navigationStart)) : 0),
      transfer_size: nav && typeof nav.transferSize === 'number' ? Math.max(0, Math.round(nav.transferSize)) : 0,
      encoded_body_size: nav && typeof nav.encodedBodySize === 'number' ? Math.max(0, Math.round(nav.encodedBodySize)) : 0,
      decoded_body_size: nav && typeof nav.decodedBodySize === 'number' ? Math.max(0, Math.round(nav.decodedBodySize)) : 0,
      url: String(w.location.href || '')
    };
  }

  function getSlowResources(limit) {
    try {
      const perf = w.performance || null;
      if (!perf || typeof perf.getEntriesByType !== 'function') {
        return [];
      }
      const entries = perf.getEntriesByType('resource');
      if (!Array.isArray(entries) || entries.length === 0) {
        return [];
      }

      const sorted = entries
        .filter((entry) => entry && typeof entry === 'object')
        .map((entry) => ({
          name: String(entry.name || ''),
          initiatorType: String(entry.initiatorType || ''),
          duration_ms: Math.max(0, Math.round(Number(entry.duration || 0))),
          start_ms: Math.max(0, Math.round(Number(entry.startTime || 0))),
          end_ms: Math.max(0, Math.round(Number(entry.responseEnd || 0))),
          transfer_size: Math.max(0, Math.round(Number(entry.transferSize || 0)))
        }))
        .sort((a, b) => b.duration_ms - a.duration_ms)
        .slice(0, Math.max(0, Number(limit) || 10));

      return sorted;
    } catch (e) {
      return [];
    }
  }

  function traceLifecycle(stage) {
    try {
      if (w.__cbLifecycleTraceDone === true) {
        return;
      }
      traceAjax('measure_lifecycle_' + String(stage || 'unknown'), {
        nav: getNavigationSummary(stage),
        slow_resources: getSlowResources(12),
        ready_state: String(document.readyState || ''),
        visibility_state: String(document.visibilityState || '')
      });
    } catch (e) {}
  }

  function markLifecycleDone() {
    w.__cbLifecycleTraceDone = true;
  }

  function logFullPageReloadTiming() {
    try {
      if (w.__cbFullPageReloadTimingSent === true) {
        return;
      }

      const nav = getNavigationEntry();
      const perf = w.performance || null;
      const navType = nav ? String(nav.type || '') : '';
      const legacyNavType = perf && perf.navigation ? Number(perf.navigation.type || 0) : 0;
      const isReload = navType === 'reload' || legacyNavType === 1;
      if (!isReload) {
        return;
      }

      const totalMs = Math.max(
        0,
        Math.round(
          Number(
            (nav && typeof nav.duration === 'number' ? nav.duration : 0)
            || (nav && typeof nav.loadEventEnd === 'number' ? nav.loadEventEnd : 0)
            || (nav && typeof nav.domComplete === 'number' ? nav.domComplete : 0)
            || 0
          )
        )
      );

      w.__cbFullPageReloadTimingSent = true;
      traceAjax('measure_full_page_reload', {
        nav_type: navType || (legacyNavType === 1 ? 'reload' : ''),
        total_ms: totalMs,
        nav: getNavigationSummary('load'),
        slow_resources: getSlowResources(20),
        url: String(w.location.href || '')
      });
    } catch (e) {}
  }

  function traceDomReady(stage) {
    try {
      traceAjax('measure_dom_' + stage, {
        nav: getNavigationSummary(stage),
        slow_resources: getSlowResources(12),
        ready_state: String(document.readyState || ''),
        visibility_state: String(document.visibilityState || '')
      });
    } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('readystatechange', function () {
      traceAjax('measure_readystatechange', {
        state: String(document.readyState || ''),
        nav: getNavigationSummary('readystatechange'),
        slow_resources: getSlowResources(8)
      });
    });
    document.addEventListener('DOMContentLoaded', function () {
      traceDomReady('contentloaded');
    }, { once: true });
  } else {
    traceDomReady('already_ready');
  }

  if (document.readyState === 'complete') {
    traceDomReady('load_already_complete');
    w.setTimeout(logFullPageReloadTiming, 0);
  } else {
    w.addEventListener('load', function () {
      traceDomReady('load');
      w.setTimeout(logFullPageReloadTiming, 0);
    }, { once: true });
  }

  w.addEventListener('pageshow', function (event) {
    traceAjax('measure_pageshow', {
      persisted: event && event.persisted ? 1 : 0,
      nav: getNavigationSummary('pageshow'),
      slow_resources: getSlowResources(10)
    });
  });

  w.addEventListener('pagehide', function (event) {
    traceAjax('measure_pagehide', {
      persisted: event && event.persisted ? 1 : 0,
      nav: getNavigationSummary('pagehide')
    });
  });

  w.addEventListener('beforeunload', function () {
    traceAjax('measure_beforeunload', {
      nav: getNavigationSummary('beforeunload')
    });
  });

  document.addEventListener('visibilitychange', function () {
    traceAjax('measure_visibilitychange', {
      state: String(document.visibilityState || ''),
      nav: getNavigationSummary('visibilitychange')
    });
  });

  function getLoaderParts(mode) {
    const loaderMode = normalizeLoaderMode(mode);
    const loader = document.querySelector('.dash_loader[data-cb-loader-mode="' + loaderMode + '"]');
    if (!(loader instanceof HTMLElement)) return null;
    const box = loader.closest('.dash_box');
    if (!(box instanceof HTMLElement)) return null;
    const timer = loader.querySelector('[data-cb-loader-time]');
    return {
      mode: loaderMode,
      box,
      loader,
      timer: (timer instanceof HTMLElement) ? timer : null
    };
  }

  function anyLoaderActive() {
    return LOADER_MODES.some((mode) => getLoaderState(mode).loading);
  }

  function syncDashBoxLoadingState() {
    const parts = getLoaderParts('dashboard') || getLoaderParts('cards') || getLoaderParts('restia');
    if (!parts) return;
    if (anyLoaderActive()) {
      parts.box.classList.add('is-dashboard-loading');
      parts.box.setAttribute('aria-busy', 'true');
    } else {
      parts.box.classList.remove('is-dashboard-loading');
      parts.box.removeAttribute('aria-busy');
    }
  }

  function setLoaderVisibility(mode, visible) {
    const loaderMode = normalizeLoaderMode(mode);
    const loaders = Array.from(document.querySelectorAll('.dash_loader[data-cb-loader-mode]')).filter((el) => el instanceof HTMLElement);
    if (loaders.length === 0) return;
    if (visible) {
      loaders.forEach((loader) => {
        const isTarget = String(loader.getAttribute('data-cb-loader-mode') || '') === loaderMode;
        if (isTarget) {
          loader.classList.remove('is-hidden');
          loader.setAttribute('aria-hidden', 'false');
          loader.setAttribute('data-cb-loader-visible', '1');
        } else {
          loader.classList.add('is-hidden');
          loader.setAttribute('aria-hidden', 'true');
          loader.removeAttribute('data-cb-loader-visible');
        }
      });
    } else {
      loaders.forEach((loader) => {
        if (String(loader.getAttribute('data-cb-loader-mode') || '') !== loaderMode) {
          return;
        }
        loader.classList.add('is-hidden');
        loader.setAttribute('aria-hidden', 'true');
        loader.removeAttribute('data-cb-loader-visible');
      });
    }
  }

  function formatLoadingTime(ms) {
    const sec = Math.max(0, Number(ms) || 0) / 1000;
    return sec.toFixed(2) + ' s';
  }

  function stopLoaderTimer(mode) {
    const state = getLoaderState(mode);
    if (state.timer !== null) {
      w.clearInterval(state.timer);
      state.timer = null;
    }
    state.startedAt = 0;
    const parts = getLoaderParts(mode);
    if (parts && parts.timer) {
      parts.timer.textContent = '0.00 s';
    }
  }

  function startLoaderTimer(mode) {
    const loaderMode = normalizeLoaderMode(mode);
    const state = getLoaderState(loaderMode);
    stopLoaderTimer(loaderMode);
    state.startedAt = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
    const tick = function () {
      const parts = getLoaderParts(loaderMode);
      if (!parts || !parts.timer) return;
      const now = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
      parts.timer.textContent = formatLoadingTime(now - state.startedAt);
    };
    tick();
    state.timer = w.setInterval(tick, 50);
  }

  function getElapsedMs(mode) {
    const state = getLoaderState(mode);
    if (!state.startedAt) return 0;
    const now = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
    return Math.max(0, now - state.startedAt);
  }

  function setLoaderLoading(mode, on) {
    const loaderMode = normalizeLoaderMode(mode);
    const state = getLoaderState(loaderMode);
    const nextOn = !!on;
    traceAjax('loader_state', {
      mode: loaderMode,
      on: nextOn ? 1 : 0,
      active: anyLoaderActive() ? 1 : 0
    });

    if (nextOn) {
      if (!state.loading) {
        startLoaderTimer(loaderMode);
      }
      state.loading = true;
      setLoaderVisibility(loaderMode, true);
      syncDashBoxLoadingState();
      return;
    }

    state.loading = false;
    stopLoaderTimer(loaderMode);
    setLoaderVisibility(loaderMode, false);
    syncDashBoxLoadingState();
  }

  function getRestiaImportParts() {
    const form = document.getElementById('cb_restia_import_form');
    if (!(form instanceof HTMLFormElement)) return null;
    const actionField = document.getElementById('cb_action_field');
    return {
      form,
      actionField: (actionField instanceof HTMLInputElement) ? actionField : null,
      pauseMs: parseInt(String(form.getAttribute('data-restia-pause-ms') || '0'), 10) || 0
    };
  }

  function initRestiaImportLoader() {
    const parts = getRestiaImportParts();
    if (!parts) {
      traceAjax('restia_init_missing_form', {});
      setLoaderLoading('restia', false);
      return;
    }

    const form = parts.form;
    const actionField = parts.actionField;
    traceAjax('restia_init', {
      pauseMs: parts.pauseMs,
      bound: form.getAttribute('data-restia-loader-bound') === '1' ? 1 : 0
    });

    if (form.getAttribute('data-restia-loader-bound') === '1') {
      setLoaderLoading('restia', false);
      return;
    }
    form.setAttribute('data-restia-loader-bound', '1');

    function startLoading() {
      traceAjax('restia_start', {
        pauseMs: parts.pauseMs
      });
      setLoaderLoading('restia', true);
    }

    function submitAjax(targetForm) {
      if (!(targetForm instanceof HTMLFormElement)) {
        return;
      }
      traceAjax('restia_submit', {
        formId: String(targetForm.id || ''),
        action: String(targetForm.action || ''),
        actionValue: actionField ? String(actionField.value || '') : ''
      });
      CB_AJAX.submitFormAndRefresh(targetForm, {
        showLoading: false,
        loaderMode: 'restia',
        refreshMode: 'dashboard'
      }).catch((err) => {
        traceAjax('restia_submit_error', {
          message: String((err && err.message) ? err.message : 'Import selhal.')
        });
        setLoaderLoading('restia', false);
        const msg = String((err && err.message) ? err.message : 'Import selhal.');
        w.alert(msg);
      });
    }

    form.addEventListener('submit', function (event) {
      const action = actionField ? String(actionField.value || '') : '';
      if (action === 'start' || action === 'continue_yes' || action === 'auto_next') {
        event.preventDefault();
        event.stopPropagation();
        startLoading();
        submitAjax(form);
      }
    }, { capture: true });

    setLoaderLoading('restia', false);
  }

  CB_AJAX.fetchText = function fetchText(url, headers, signal) {
    const u = String(url || '');
    const h = (headers && typeof headers === 'object') ? headers : {};

    return fetch(u, {
      method: 'GET',
      headers: h,
      signal: signal
    }).then((res) => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    });
  };

  CB_AJAX.setDashboardLoading = function setDashboardLoadingPublic(on, mode) {
    setLoaderLoading(mode || 'dashboard', !!on);
  };

  CB_AJAX.trace = function tracePublic(event, data) {
    traceAjax(event, data);
  };

  CB_AJAX.submitFormAndRefresh = function submitFormAndRefreshPublic(form, options) {
    const targetForm = (form instanceof HTMLFormElement) ? form : null;
    if (!targetForm) {
      return Promise.reject(new Error('Formular nebyl nalezen.'));
    }

    const opts = (options && typeof options === 'object') ? options : {};
    const loaderMode = normalizeLoaderMode(opts.loaderMode || 'dashboard');
    const refreshMode = normalizeLoaderMode(opts.refreshMode || 'dashboard');
    const keepLoading = !!opts.keepLoading;
    const body = new FormData(targetForm);
    const reqUrl = String(targetForm.action || w.location.href || 'index.php');
    const method = String(targetForm.method || 'POST').toUpperCase();
    traceAjax('submit_start', {
      mode: loaderMode,
      refreshMode: refreshMode,
      formId: String(targetForm.id || ''),
      action: reqUrl,
      method: method
    });

    if (opts.showLoading !== false) {
      setLoaderLoading(loaderMode, true);
    }

    return fetch(reqUrl, {
      method: method,
      body: body,
      credentials: 'same-origin',
      redirect: 'follow'
    }).then((res) => {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.text();
    }).then(() => CB_AJAX.refreshDashboard({
      force: true,
      keepLoading: keepLoading,
      loaderMode: refreshMode
    })).then(() => {
      traceAjax('submit_done', {
        mode: loaderMode,
        refreshMode: refreshMode
      });
    }).catch((err) => {
      traceAjax('submit_error', {
        mode: loaderMode,
        refreshMode: refreshMode,
        message: String((err && err.message) ? err.message : 'submit selhal')
      });
      throw err;
    });
  };

  CB_AJAX.runAfterDashboardLoading = function runAfterDashboardLoading(action, minVisibleMs) {
    if (typeof action !== 'function') return;
    const minMs = Math.max(0, Number(minVisibleMs) || 0);
    if (!anyLoaderActive() || minMs <= 0) {
      action();
      return;
    }
    const activeMode = LOADER_MODES.find((mode) => getLoaderState(mode).loading) || 'dashboard';
    const waitMs = Math.max(0, minMs - getElapsedMs(activeMode));
    w.setTimeout(action, waitMs);
  };

  CB_AJAX.refreshDashboard = function refreshDashboard(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const force = !!opts.force;
    const keepLoading = !!opts.keepLoading;
    const loaderMode = normalizeLoaderMode(opts.loaderMode || 'dashboard');
    const refreshStartedAt = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();

    if (getLoaderState(loaderMode).loading && !force) {
      return Promise.resolve({ ok: false, busy: true });
    }

    const parts = getLoaderParts(loaderMode);
    if (!parts) {
      traceAjax('refresh_missing_parts', {
        mode: loaderMode
      });
      return Promise.reject(new Error('Dashboard container nebyl nalezen.'));
    }
    if (!force) {
      setLoaderLoading(loaderMode, true);
    }

    const reqUrl = String(w.location.href || 'index.php');
    traceAjax('refresh_start', {
      mode: loaderMode,
      force: force ? 1 : 0,
      keepLoading: keepLoading ? 1 : 0,
      url: reqUrl
    });
    traceAjax('measure_refresh_start', {
      mode: loaderMode,
      force: force ? 1 : 0,
      keepLoading: keepLoading ? 1 : 0,
      url: reqUrl
    });
    return CB_AJAX.fetchText(reqUrl, { 'X-Comeback-Partial': '1' })
      .then((html) => {
        parts.box.querySelector('[data-cb-dash-content="1"]').innerHTML = String(html || '');
        document.dispatchEvent(new CustomEvent('cb:main-swapped'));
        traceAjax('refresh_done', {
          mode: loaderMode
        });
        traceAjax('measure_refresh_done', {
          mode: loaderMode,
          total_ms: Math.max(0, Math.round(((w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now()) - refreshStartedAt)),
          url: reqUrl
        });
        return { ok: true };
      }).catch((err) => {
        traceAjax('refresh_error', {
          mode: loaderMode,
          message: String((err && err.message) ? err.message : 'refresh selhal')
        });
        traceAjax('measure_refresh_error', {
          mode: loaderMode,
          total_ms: Math.max(0, Math.round(((w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now()) - refreshStartedAt)),
          message: String((err && err.message) ? err.message : 'refresh selhal'),
          url: reqUrl
        });
        throw err;
      })
      .finally(() => {
        if (!keepLoading) {
          setLoaderLoading(loaderMode, false);
        }
      });
  };

  CB_AJAX.refreshCard = function refreshCard(cardId, options) {
    const id = parseInt(String(cardId || '0'), 10);
    const opts = (options && typeof options === 'object') ? options : {};
    const force = !!opts.force;
    const keepLoading = !!opts.keepLoading;
    const loaderMode = normalizeLoaderMode(opts.loaderMode || 'cards');

    if (!Number.isFinite(id) || id <= 0) {
      return Promise.reject(new Error('ID karty nebylo nalezeno.'));
    }

    if (getLoaderState(loaderMode).loading && !force) {
      return Promise.resolve({ ok: false, busy: true });
    }

    const dashCard = document.querySelector('[data-cb-dash-card="1"] .card_shell[data-card-id="' + String(id) + '"]');
    const currentShell = dashCard instanceof HTMLElement ? dashCard : null;
    const currentCard = currentShell ? currentShell.closest('[data-cb-dash-card="1"]') : null;

    if (!(currentCard instanceof HTMLElement)) {
      traceAjax('refresh_card_missing', {
        mode: loaderMode,
        card_id: id
      });
      return Promise.reject(new Error('Karta nebyla nalezena.'));
    }

    if (!force) {
      setLoaderLoading(loaderMode, true);
    }

    currentCard.classList.add('is-card-refreshing');
    currentCard.setAttribute('aria-busy', 'true');

    const reqUrl = 'index.php?cb_card_id=' + encodeURIComponent(String(id));
    traceAjax('refresh_card_start', {
      mode: loaderMode,
      force: force ? 1 : 0,
      keepLoading: keepLoading ? 1 : 0,
      card_id: id,
      url: reqUrl
    });

    return CB_AJAX.fetchText(reqUrl, { 'X-Comeback-Card': '1' })
      .then((html) => {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '').trim();
        const nextCard = wrap.firstElementChild;

        if (!(nextCard instanceof HTMLElement)) {
          throw new Error('Nova karta ma neplatny obsah.');
        }

        currentCard.replaceWith(nextCard);
        document.dispatchEvent(new CustomEvent('cb:card-swapped', {
          detail: {
            cardId: id,
            card: nextCard
          }
        }));
        traceAjax('refresh_card_done', {
          mode: loaderMode,
          card_id: id
        });
        return { ok: true, cardId: id, card: nextCard };
      }).catch((err) => {
        traceAjax('refresh_card_error', {
          mode: loaderMode,
          card_id: id,
          message: String((err && err.message) ? err.message : 'refresh card selhal')
        });
        throw err;
      }).finally(() => {
        currentCard.classList.remove('is-card-refreshing');
        currentCard.removeAttribute('aria-busy');
        if (!keepLoading) {
          setLoaderLoading(loaderMode, false);
        }
      });
  };


  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initRestiaImportLoader();
    }, { once: true });
  } else {
    initRestiaImportLoader();
  }

  document.addEventListener('cb:main-swapped', function () {
    initRestiaImportLoader();
  });

})(window);
// js/ajax_core.js * Verze: V5 * Aktualizace: 15.04.2026 * Konec souboru
