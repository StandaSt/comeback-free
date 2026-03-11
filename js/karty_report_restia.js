// js/karty_report_restia.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function () {
  function setMockApiValues(root) {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    const map = {
      '[data-zr-restia-trzba]': '36 618 Kč',
      '[data-zr-restia-wolt]': '9 034 Kč',
      '[data-zr-restia-bolt]': '1 056 Kč',
      '[data-zr-restia-dj]': '8 491 Kč',
      '[data-zr-restia-web]': '12 699 Kč',
      '[data-zr-restia-wolt-cash]': '0 Kč',
      '[data-zr-restia-dj-cash]': '2 093 Kč',
      '[data-zr-cancel-count]': '0',
      '[data-zr-cancel-value]': '0 Kč',
      '[data-zr-delay-count]': '5',
      '[data-zr-make-time]': '13 min 24 s',
      '[data-zr-docs-count]': '0',
      '[data-zr-orders-total]': '78',
      '[data-zr-own-deliveries]': '12',
      '[data-zr-woltdrive-late]': '0'
    };

    Object.keys(map).forEach((selector) => {
      const el = root.querySelector(selector);
      if (el) {
        el.textContent = map[selector];
      }
    });
  }

  function initKartyReportRestia() {
    document.querySelectorAll('.cb-zadani-reportu').forEach((root) => {
      if (!(root instanceof HTMLElement) || root.getAttribute('data-zr-restia-init') === '1') {
        return;
      }
      root.setAttribute('data-zr-restia-init', '1');
      setMockApiValues(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyReportRestia);
  } else {
    initKartyReportRestia();
  }
}());
