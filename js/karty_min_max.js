// js/karty_min_max.js * Verze: V1 * Aktualizace: 09.03.2026
'use strict';

(function (w) {
  const ICON_MAX = '\u2922'; // ⤢
  const ICON_MIN = '\u2921'; // ⤡

  function setExpanded(root, compactSel, expandedSel, toggleSel, on) {
    if (!root) return;

    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const toggle = root.querySelector(toggleSel);
    const dashCard = root.closest('.dash_card');
    const isOn = !!on;

    if (compact) compact.classList.toggle('is-hidden', isOn);
    if (expanded) expanded.classList.toggle('is-hidden', !isOn);
    if (dashCard) dashCard.classList.toggle('is-expanded', isOn);

    if (toggle) {
      toggle.textContent = isOn ? ICON_MIN : ICON_MAX;
      toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
    }
  }

  function initAdminKartyCard(root) {
    if (!root || root.getAttribute('data-admink-init') === '1') return;
    root.setAttribute('data-admink-init', '1');

    const toggle = root.querySelector('[data-admink-toggle]');
    const tabs = root.querySelectorAll('[data-admink-tab]');
    const panels = root.querySelectorAll('[data-admink-panel]');
    const tabKey = 'cb_admin_karty_tab';

    const readSavedTab = () => {
      try {
        return String(window.sessionStorage.getItem(tabKey) || 'nova');
      } catch (e) {
        return 'nova';
      }
    };

    const saveTab = (v) => {
      try {
        window.sessionStorage.setItem(tabKey, String(v || 'nova'));
      } catch (e) {
        // ignore
      }
    };

    const setTab = (name) => {
      const key = String(name || 'nova');
      tabs.forEach((btn) => {
        const active = String(btn.getAttribute('data-admink-tab') || '') === key;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        const active = String(panel.getAttribute('data-admink-panel') || '') === key;
        panel.classList.toggle('is-hidden', !active);
      });
      saveTab(key);
    };

    setExpanded(root, '[data-admink-compact]', '[data-admink-expanded]', '[data-admink-toggle]', false);
    setTab(readSavedTab());

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(root, '[data-admink-compact]', '[data-admink-expanded]', '[data-admink-toggle]', !isExpanded);
      });
    }

    tabs.forEach((btn) => {
      btn.addEventListener('click', () => {
        setTab(btn.getAttribute('data-admink-tab') || 'nova');
      });
    });
  }

  function initZadaniReportuCard(root) {
    if (!root || root.getAttribute('data-zr-init') === '1') return;
    root.setAttribute('data-zr-init', '1');

    const toggle = root.querySelector('[data-zr-toggle]');

    const setMockApiValues = () => {
      const map = {
        api_pocet_obj: '78',
        api_make_time: '13 min 24 s',
        api_zrusene_ks: '0',
        api_zrusene_castka: '0'
      };
      Object.keys(map).forEach((key) => {
        const el = root.querySelector('[name="' + key + '"]');
        if (el instanceof HTMLInputElement) {
          el.value = map[key];
        }
      });
    };

    setExpanded(root, '[data-zr-compact]', '[data-zr-expanded]', '[data-zr-toggle]', false);
    setMockApiValues();

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(root, '[data-zr-compact]', '[data-zr-expanded]', '[data-zr-toggle]', !isExpanded);
      });
    }
  }

  function initUzivateleCard(root) {
    if (!root || root.getAttribute('data-uz-init') === '1') return;
    root.setAttribute('data-uz-init', '1');

    const toggle = root.querySelector('[data-uz-toggle]');
    setExpanded(root, '[data-uz-compact]', '[data-uz-expanded]', '[data-uz-toggle]', false);

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(root, '[data-uz-compact]', '[data-uz-expanded]', '[data-uz-toggle]', !isExpanded);
      });
    }
  }

  function initZakazniciCard(root) {
    if (!root || root.getAttribute('data-zak-init') === '1') return;
    root.setAttribute('data-zak-init', '1');

    const toggle = root.querySelector('[data-zak-toggle]');
    setExpanded(root, '[data-zak-compact]', '[data-zak-expanded]', '[data-zak-toggle]', false);

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(root, '[data-zak-compact]', '[data-zak-expanded]', '[data-zak-toggle]', !isExpanded);
      });
    }
  }

  function forceCompact() {
    document.querySelectorAll('.cb-admin-karty').forEach((root) => {
      setExpanded(root, '[data-admink-compact]', '[data-admink-expanded]', '[data-admink-toggle]', false);
    });
    document.querySelectorAll('.cb-zadani-reportu').forEach((root) => {
      setExpanded(root, '[data-zr-compact]', '[data-zr-expanded]', '[data-zr-toggle]', false);
    });
    document.querySelectorAll('.cb-uzivatele').forEach((root) => {
      setExpanded(root, '[data-uz-compact]', '[data-uz-expanded]', '[data-uz-toggle]', false);
    });
    document.querySelectorAll('.cb-zakaznici').forEach((root) => {
      setExpanded(root, '[data-zak-compact]', '[data-zak-expanded]', '[data-zak-toggle]', false);
    });
  }

  function initKartyMinMax() {
    document.querySelectorAll('.cb-admin-karty').forEach(initAdminKartyCard);
    document.querySelectorAll('.cb-zadani-reportu').forEach(initZadaniReportuCard);
    document.querySelectorAll('.cb-uzivatele').forEach(initUzivateleCard);
    document.querySelectorAll('.cb-zakaznici').forEach(initZakazniciCard);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:main-swapped', initKartyMinMax);
    document.addEventListener('cb:menu-same-sekce', forceCompact);
  }

  wireOnce();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyMinMax, { once: true });
  } else {
    initKartyMinMax();
  }
})(window);

// js/karty_min_max.js * Verze: V1 * Aktualizace: 09.03.2026
// Konec souboru
