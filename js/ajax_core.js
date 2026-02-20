// js/ajax_core.js * Verze: V1 * Aktualizace: 19.2.2026
'use strict';

/*
 * AJAX core – společné minimum (100% identický kus)
 *
 * Cíl:
 * - žádné režimy, žádné rozhodování "menu vs tabulky"
 * - jen pomocník pro fetch → text + jednotné ošetření HTTP/neplatné odpovědi
 *
 * Použití:
 * - ostatní skripty si řeší samy:
 *   - odkud berou URL (menu = w.location.href, tabulky = href z odkazu/form)
 *   - co udělají po úspěchu (např. keep clean URL)
 *   - jak renderují loader/error (karta apod.)
 */

(function (w) {
  const CB_AJAX = w.CB_AJAX || (w.CB_AJAX = {});

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

})(window);

// js/ajax_core.js * Verze: V1 * Aktualizace: 19.2.2026 * Počet řádků: 38
// Konec souboru