// js/karty_form.js * Verze: V1 * Aktualizace: 09.03.2026
'use strict';

(function (w) {
  const K = w.CB_KARTY || (w.CB_KARTY = {});

  function readRowPayload(tr) {
    const id = K.toInt(tr?.getAttribute('data-id'), 0);
    const n = tr?.querySelector('input[data-f="nazev"]')?.value ?? '';
    const s = tr?.querySelector('input[data-f="soubor"]')?.value ?? '';
    const r = K.toInt(tr?.querySelector('input[data-f="min_role"]')?.value, 9);
    const p = K.toInt(tr?.querySelector('input[data-f="poradi"]')?.value, 999);

    return {
      id_karta: id,
      nazev: String(n).trim(),
      soubor: String(s).trim(),
      min_role: r,
      poradi: p
    };
  }

  async function onAddSubmit(ev) {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.classList.contains('cb-admin-karty-add')) return;

    const root = form.closest('.cb-admin-karty');
    if (!root) return;

    ev.preventDefault();

    const fd = new FormData(form);
    const payload = {
      action: 'add',
      nazev: String(fd.get('nazev') || '').trim(),
      soubor: String(fd.get('soubor') || '').trim(),
      min_role: K.toInt(fd.get('min_role'), 3),
      poradi: K.toInt(fd.get('poradi'), 100)
    };

    try {
      K.setMsg(root, 'Ukladam novou kartu...', false);
      await K.apiPost(root, payload);
      form.reset();
      await K.refreshList(root);
      K.setMsg(root, 'Karta byla pridana.', false);
    } catch (e) {
      K.setMsg(root, e && e.message ? e.message : 'Pridani selhalo.', true);
    }
  }

  async function onClick(ev) {
    const btn = ev.target?.closest('button');
    if (!btn) return;

    const root = btn.closest('.cb-admin-karty');
    const vyjRoot = btn.closest('.cb-admin-vyjimky');

    if (root && btn.hasAttribute('data-cb-karty-refresh')) {
      await K.refreshList(root);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-refresh-users')) {
      await K.refreshVyjUsers(vyjRoot);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-refresh-cards')) {
      await K.refreshVyjCards(vyjRoot);
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-refresh')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || K.getVyjLogRoot();
      const selUser = document.querySelector('[data-cb-vyj-user]');
      const selKarta = document.querySelector('[data-cb-vyj-log-karta]');
      const userId = (selUser instanceof HTMLSelectElement) ? K.toInt(selUser.value, 0) : 0;
      const kartaId = (selKarta instanceof HTMLSelectElement) ? K.toInt(selKarta.value, 0) : 0;
      if (logRoot) {
        await K.refreshVyjLog(logRoot, userId, kartaId, 1);
      }
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-export')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || K.getVyjLogRoot();
      if (!logRoot) return;
      const items = Array.isArray(logRoot.__cbVyjLogItems) ? logRoot.__cbVyjLogItems : [];
      K.exportVyjLogCsv(logRoot, items);
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-prev') || btn.hasAttribute('data-cb-vyj-log-next')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || K.getVyjLogRoot();
      if (!logRoot) return;
      const st = K.getVyjLogState(logRoot);
      const next = btn.hasAttribute('data-cb-vyj-log-next');
      const targetPage = next ? (st.page + 1) : (st.page - 1);
      const selUser = document.querySelector('[data-cb-vyj-user]');
      const selKarta = document.querySelector('[data-cb-vyj-log-karta]');
      const userId = (selUser instanceof HTMLSelectElement) ? K.toInt(selUser.value, 0) : 0;
      const kartaId = (selKarta instanceof HTMLSelectElement) ? K.toInt(selKarta.value, 0) : 0;
      await K.refreshVyjLog(logRoot, userId, kartaId, targetPage);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-set')) {
      const tr = btn.closest('tr[data-id-karta]');
      const sel = vyjRoot.querySelector('[data-cb-vyj-user]');
      if (!tr || !(sel instanceof HTMLSelectElement)) return;

      const idUser = K.toInt(sel.value, 0);
      const idKarta = K.toInt(tr.getAttribute('data-id-karta'), 0);
      const mode = String(btn.getAttribute('data-cb-vyj-set') || '');
      if (idUser <= 0 || idKarta <= 0 || (mode !== 'role' && mode !== 'allow' && mode !== 'deny')) return;

      try {
        K.setVyjMsg(vyjRoot, 'Ukladam vyjimku...', false);
        await K.apiPost(vyjRoot, {
          action: 'set_vyjimka',
          id_user: idUser,
          id_karta: idKarta,
          mode: mode
        });
        await K.refreshVyjCards(vyjRoot);
        const logRoot = K.getVyjLogRoot();
        if (logRoot) {
          const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
          const kartaId = (selKarta instanceof HTMLSelectElement) ? K.toInt(selKarta.value, 0) : 0;
          await K.refreshVyjLog(logRoot, idUser, kartaId, 1);
        }
        K.setVyjMsg(vyjRoot, 'Vyjimka byla ulozena.', false);
      } catch (e) {
        K.setVyjMsg(vyjRoot, e && e.message ? e.message : 'Ulozeni vyjimky selhalo.', true);
      }
      return;
    }

    if (!root) return;

    const action = String(btn.getAttribute('data-act') || '');
    if (!action) return;

    const tr = btn.closest('tr[data-id]');
    if (!tr) return;

    try {
      if (action === 'save') {
        const row = readRowPayload(tr);
        K.setMsg(root, 'Ukladam zmenu karty...', false);
        await K.apiPost(root, {
          action: 'update',
          id_karta: row.id_karta,
          nazev: row.nazev,
          soubor: row.soubor,
          min_role: row.min_role,
          poradi: row.poradi
        });
        await K.refreshList(root);
        K.setMsg(root, 'Zmena byla ulozena.', false);
        return;
      }

      if (action === 'toggle') {
        const id = K.toInt(tr.getAttribute('data-id'), 0);
        const val = K.toInt(btn.getAttribute('data-val'), 0) === 1 ? 1 : 0;
        K.setMsg(root, 'Ukladam stav karty...', false);
        await K.apiPost(root, {
          action: 'toggle',
          id_karta: id,
          aktivni: val
        });
        await K.refreshList(root);
        K.setMsg(root, 'Stav karty byl zmenen.', false);
        return;
      }

      if (action === 'move-up' || action === 'move-down') {
        const id = K.toInt(tr.getAttribute('data-id'), 0);
        const dir = (action === 'move-up') ? 'up' : 'down';
        K.setMsg(root, 'Meni poradi karty...', false);
        await K.apiPost(root, {
          action: 'move',
          id_karta: id,
          direction: dir
        });
        await K.refreshList(root);
        K.setMsg(root, 'Poradi bylo upraveno.', false);
      }
    } catch (e) {
      K.setMsg(root, e && e.message ? e.message : 'Akce se nepovedla.', true);
    }
  }

  function onChange(ev) {
    const sel = ev.target;
    if (!(sel instanceof HTMLSelectElement)) return;

    if (sel.hasAttribute('data-cb-vyj-user')) {
      const vyjRoot = sel.closest('.cb-admin-vyjimky');
      if (!vyjRoot) return;

      K.refreshVyjCards(vyjRoot);

      const logRoot = K.getVyjLogRoot();
      if (logRoot) {
        const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
        const kartaId = (selKarta instanceof HTMLSelectElement) ? K.toInt(selKarta.value, 0) : 0;
        K.refreshVyjLog(logRoot, K.toInt(sel.value, 0), kartaId, 1);
      }
      return;
    }

    if (sel.hasAttribute('data-cb-vyj-log-karta')) {
      const logRoot = sel.closest('.cb-admin-vyjimky-log');
      if (!logRoot) return;

      const selUser = document.querySelector('[data-cb-vyj-user]');
      const userId = (selUser instanceof HTMLSelectElement) ? K.toInt(selUser.value, 0) : 0;
      K.refreshVyjLog(logRoot, userId, K.toInt(sel.value, 0), 1);
      return;
    }

    if (sel.hasAttribute('data-cb-vyj-log-per-page')) {
      const logRoot = sel.closest('.cb-admin-vyjimky-log');
      if (!logRoot) return;

      const selUser = document.querySelector('[data-cb-vyj-user]');
      const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
      const userId = (selUser instanceof HTMLSelectElement) ? K.toInt(selUser.value, 0) : 0;
      const kartaId = (selKarta instanceof HTMLSelectElement) ? K.toInt(selKarta.value, 0) : 0;
      K.refreshVyjLog(logRoot, userId, kartaId, 1);
    }
  }

  function onInput(ev) {
    const el = ev.target;
    if (!(el instanceof HTMLInputElement)) return;
    if (!el.hasAttribute('data-cb-vyj-log-search')) return;

    const logRoot = el.closest('.cb-admin-vyjimky-log');
    if (!logRoot) return;

    const items = Array.isArray(logRoot.__cbVyjLogItems) ? logRoot.__cbVyjLogItems : [];
    K.renderVyjLogList(logRoot, items);
  }

  function initKartyData() {
    const root = K.getRoot();
    if (root && root.getAttribute('data-karty-data-init') !== '1') {
      root.setAttribute('data-karty-data-init', '1');
      K.refreshList(root);
    }

    const vyjRoot = K.getVyjRoot();
    if (vyjRoot && vyjRoot.getAttribute('data-karty-data-init') !== '1') {
      vyjRoot.setAttribute('data-karty-data-init', '1');
      K.refreshVyjUsers(vyjRoot);
    }

    const logRoot = K.getVyjLogRoot();
    if (logRoot && logRoot.getAttribute('data-karty-data-init') !== '1') {
      logRoot.setAttribute('data-karty-data-init', '1');
      K.refreshVyjLogCards(logRoot).then(() => {
        K.refreshVyjLog(logRoot, 0, 0, 1);
      });
    }
  }

  function wireOnce() {
    if (w.__CB_KARTY_FORM_WIRED__) return;
    w.__CB_KARTY_FORM_WIRED__ = true;

    document.addEventListener('submit', onAddSubmit, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('change', onChange, true);
    document.addEventListener('input', onInput, true);
    document.addEventListener('cb:main-swapped', initKartyData);
  }

  wireOnce();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyData, { once: true });
  } else {
    initKartyData();
  }
})(window);

// js/karty_form.js * Verze: V1 * Aktualizace: 09.03.2026
// Konec souboru
