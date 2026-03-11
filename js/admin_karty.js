// js/admin_karty.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  function initOne(root) {
    if (!root || root.getAttribute('data-admink-init') === '1') return;
    root.setAttribute('data-admink-init', '1');

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

    setTab(readSavedTab());

    tabs.forEach((btn) => {
      btn.addEventListener('click', () => {
        setTab(btn.getAttribute('data-admink-tab') || 'nova');
      });
    });
  }

  function initAdminKarty() {
    document.querySelectorAll('.cb-admin-karty').forEach(initOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminKarty);
  } else {
    initAdminKarty();
  }
}(window));
