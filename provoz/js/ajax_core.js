// Účel: společné síťové pomocné funkce Provozu, přesměrování po vypršení relace a diagnostické měření AJAX provozu.
'use strict';

(function (w) {
  const CB_AJAX = w.CB_AJAX || (w.CB_AJAX = {});
  const nativeFetch = typeof w.fetch === 'function' ? w.fetch.bind(w) : null;
  const applicationUrl = new URL(String(w.CB_ENDPOINT || 'index.php'), w.location.href);
  const AJAX_TRACE_URL = new URL('provoz/lib/ajax_trace.php', applicationUrl).toString();
  let loginRedirectStarted = false;

  // Rozpoznání stejného původu brání přesměrování kvůli cizím HTTP požadavkům.
  function isSameOriginRequest(input) {
    try {
      const requestUrl = input && typeof input === 'object' && typeof input.url === 'string'
        ? input.url
        : String(input || '');
      return new URL(requestUrl, w.location.href).origin === w.location.origin;
    } catch (error) {
      return false;
    }
  }

  // První odpověď 401 z aplikace vrátí uživatele do společného přihlašovacího toku.
  function redirectToLogin() {
    if (loginRedirectStarted) {
      return;
    }
    loginRedirectStarted = true;
    w.location.replace(applicationUrl.toString());
  }

  if (nativeFetch) {
    w.fetch = function (input, init) {
      return nativeFetch(input, init).then((response) => {
        if (response && response.status === 401 && isSameOriginRequest(input)) {
          redirectToLogin();
        }
        return response;
      });
    };
  }

  // Diagnostická událost se odesílá mimo hlavní tok a nesmí blokovat práci uživatele.
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
    } catch (error) {
    }
  }

  // Přehled navigace tvoří společný základ diagnostických záznamů životního cyklu stránky.
  function getNavigationSummary(stage) {
    const performanceApi = w.performance || null;
    const entries = performanceApi && typeof performanceApi.getEntriesByType === 'function'
      ? performanceApi.getEntriesByType('navigation')
      : [];
    const navigation = Array.isArray(entries) && entries.length > 0 ? entries[0] : null;
    const legacy = performanceApi && performanceApi.timing ? performanceApi.timing : null;
    const legacyStart = legacy ? Number(legacy.navigationStart || 0) : 0;

    return {
      stage: String(stage || ''),
      nav_type: navigation ? String(navigation.type || '') : '',
      dom_content_loaded_ms: navigation && typeof navigation.domContentLoadedEventEnd === 'number'
        ? Math.max(0, Math.round(navigation.domContentLoadedEventEnd))
        : (legacy ? Math.max(0, Math.round(Number(legacy.domContentLoadedEventEnd || 0) - legacyStart)) : 0),
      load_event_ms: navigation && typeof navigation.loadEventEnd === 'number'
        ? Math.max(0, Math.round(navigation.loadEventEnd))
        : (legacy ? Math.max(0, Math.round(Number(legacy.loadEventEnd || 0) - legacyStart)) : 0),
      response_end_ms: navigation && typeof navigation.responseEnd === 'number'
        ? Math.max(0, Math.round(navigation.responseEnd))
        : (legacy ? Math.max(0, Math.round(Number(legacy.responseEnd || 0) - legacyStart)) : 0),
      url: String(w.location.href || '')
    };
  }

  function traceLifecycle(stage, extra) {
    traceAjax('measure_' + String(stage || 'unknown'), Object.assign({
      nav: getNavigationSummary(stage),
      ready_state: String(document.readyState || ''),
      visibility_state: String(document.visibilityState || '')
    }, extra || {}));
  }

  // Veřejné rozhraní používají ostatní jednoúčelové skripty Provozu.
  CB_AJAX.trace = function tracePublic(event, data) {
    traceAjax(event, data);
  };

  CB_AJAX.fetchText = function fetchText(url, headers, signal) {
    return w.fetch(String(url || ''), {
      method: 'GET',
      headers: headers && typeof headers === 'object' ? headers : {},
      signal,
      credentials: 'same-origin'
    }).then((response) => {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.text();
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      traceLifecycle('dom_content_loaded');
    }, { once: true });
  } else {
    traceLifecycle('dom_ready');
  }

  w.addEventListener('load', function () {
    traceLifecycle('load');
  }, { once: true });

  w.addEventListener('pageshow', function (event) {
    traceLifecycle('pageshow', { persisted: event && event.persisted ? 1 : 0 });
  });

  w.addEventListener('pagehide', function (event) {
    traceLifecycle('pagehide', { persisted: event && event.persisted ? 1 : 0 });
  });

  document.addEventListener('visibilitychange', function () {
    traceLifecycle('visibilitychange');
  });
})(window);
