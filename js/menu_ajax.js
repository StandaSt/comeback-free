// js/menu_ajax.js * Verze: V12 * Aktualizace: 07.03.2026
'use strict';

/*
 * menu_ajax.js
 * - AJAX navigace: URL v prohlÄ‚Â­ÄąÄľeĂ„Ĺ¤i se nemĂ„â€şnÄ‚Â­
 * - server dostane cÄ‚Â­lovou strÄ‚Ë‡nku v hlaviĂ„Ĺ¤ce X-Comeback-Page
 * - server pÄąâ„˘i hlaviĂ„Ĺ¤ce X-Comeback-Partial: 1 vrÄ‚Ë‡tÄ‚Â­ jen HTML do <main>
 *
 * V12:
 * - vyvola udalost "cb:main-swapped" po vymene obsahu
 * - obsahuje modul CB_ADMIN pro spravu karet v includes/admin.php
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});
  const CB_AJAX = w.CB_AJAX || null;

  function getMainEl() {
    return document.querySelector('.central-content main') || document.querySelector('main');
  }

  function setMainCard(mainEl, html) {
    if (!mainEl) return;
    mainEl.innerHTML = '<section class="card">' + html + '</section>';
  }

  function setMainLoading(mainEl) {
    setMainCard(mainEl, '<p>NaĂ„Ĺ¤Ä‚Â­tÄ‚Ë‡mĂ˘â‚¬Â¦</p>');
  }

  function setMainError(mainEl, msg) {
    const safe = String(msg || 'NeznÄ‚Ë‡mÄ‚Ë‡ chyba');
    setMainCard(
      mainEl,
      '<p><strong>NaĂ„Ĺ¤tenÄ‚Â­ se nepovedlo.</strong></p>' +
      '<p>' + safe + '</p>'
    );
  }

  function notifyMainSwap(pageKey) {
    document.dispatchEvent(new CustomEvent('cb:main-swapped', {
      detail: { page: String(pageKey || '') }
    }));
  }

  let activeController = null;

  function fetchMainAndSwap(pageKey) {
    const mainEl = getMainEl();
    const p = String(pageKey || '').trim() || 'home';

    if (!mainEl) {
      console.error('[CB_MENU AJAX] ChybÄ‚Â­ <main>, nelze provÄ‚Â©st swap. page=', p);
      return;
    }

    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      console.error('[CB_MENU AJAX] ChybÄ‚Â­ CB_AJAX.fetchText (js/ajax_core.js).');
      setMainError(mainEl, 'ChybÄ‚Â­ AJAX core.');
      return;
    }

    if (activeController) {
      activeController.abort();
      activeController = null;
    }

    const ctrl = new AbortController();
    activeController = ctrl;

    setMainLoading(mainEl);

    CB_AJAX.fetchText(w.location.href, {
      'X-Comeback-Partial': '1',
      'X-Comeback-Page': p
    }, ctrl.signal).then((html) => {
      if (activeController === ctrl) activeController = null;

      mainEl.innerHTML = html;
      CB_MENU._currentPage = p;
      notifyMainSwap(p);
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      if (activeController === ctrl) activeController = null;

      const msg = (err && err.message) ? err.message : 'AJAX chyba';
      console.error('[CB_MENU AJAX] Chyba naĂ„Ĺ¤tenÄ‚Â­ page=', p, err);
      setMainError(mainEl, msg);
    });
  }

  function wirePopstateOnce() {
    if (w.__CB_AJAX_POPSTATE_WIRED__) return;
    w.__CB_AJAX_POPSTATE_WIRED__ = true;
  }

  CB_MENU._ajaxFetchMainAndSwap = fetchMainAndSwap;
  CB_MENU._ajaxWirePopstateOnce = wirePopstateOnce;

  // ========================
  // CB_ADMIN - sprava karet
  // ========================
  const CB_ADMIN = w.CB_ADMIN || (w.CB_ADMIN = {});

  function esc(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function toInt(v, dflt) {
    const n = Number.parseInt(String(v ?? ''), 10);
    return Number.isFinite(n) ? n : dflt;
  }

  function getRoot() {
    return document.querySelector('.cb-admin-karty');
  }

  function getVyjRoot() {
    return document.querySelector('.cb-admin-vyjimky');
  }

  function getVyjLogRoot() {
    return document.querySelector('.cb-admin-vyjimky-log');
  }

  function getMsgEl(root) {
    return root ? root.querySelector('.cb-admin-karty-msg') : null;
  }

  function setMsg(root, text, isErr) {
    const el = getMsgEl(root);
    if (!el) return;
    el.textContent = String(text || '');
    el.style.color = isErr ? '#b91c1c' : '#166534';
  }

  async function apiGetList(root) {
    return apiGet(root, 'list');
  }

  async function apiGet(root, action, params) {
    const api = String(root?.getAttribute('data-api') || '').trim();
    if (!api) throw new Error('Chybi API URL.');

    const sp = new URLSearchParams();
    sp.set('action', String(action || ''));
    if (params && typeof params === 'object') {
      Object.keys(params).forEach((k) => {
        const v = params[k];
        if (v === null || typeof v === 'undefined' || v === '') return;
        sp.set(k, String(v));
      });
    }

    const url = api + (api.includes('?') ? '&' : '?') + sp.toString();
    const res = await fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();
    if (!res.ok || !data || data.ok !== true) {
      throw new Error((data && data.err) ? String(data.err) : 'Nacteni seznamu selhalo.');
    }
    return data;
  }

  async function apiPost(root, payload) {
    const api = String(root?.getAttribute('data-api') || '').trim();
    if (!api) throw new Error('Chybi API URL.');

    const res = await fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload || {})
    });
    const data = await res.json();
    if (!res.ok || !data || data.ok !== true) {
      throw new Error((data && data.err) ? String(data.err) : 'Ulozeni selhalo.');
    }
    return data;
  }

  function renderList(root, items) {
    const tbodies = root ? root.querySelectorAll('[data-cb-karty-list]') : [];
    if (!tbodies || tbodies.length === 0) return;

    if (!Array.isArray(items) || items.length === 0) {
      tbodies.forEach((tbody) => {
        tbody.innerHTML = '<tr><td colspan="8">Zatim nejsou zadne karty.</td></tr>';
      });
      return;
    }

    const html = items.map((it) => {
      const id = toInt(it.id_karta, 0);
      const aktivni = toInt(it.aktivni, 0) === 1;
      return '' +
        '<tr data-id="' + esc(id) + '">' +
          '<td>' + esc(id) + '</td>' +
          '<td>' + esc(it.kod) + '</td>' +
          '<td><input type="text" data-f="nazev" value="' + esc(it.nazev) + '" maxlength="120"></td>' +
          '<td><input type="text" data-f="soubor" value="' + esc(it.soubor) + '" maxlength="190"></td>' +
          '<td><input type="number" data-f="min_role" value="' + esc(toInt(it.min_role, 9)) + '" min="1" max="99"></td>' +
          '<td><input type="number" data-f="poradi" value="' + esc(toInt(it.poradi, 999)) + '" min="1" max="9999"></td>' +
          '<td>' + (aktivni ? 'ano' : 'ne') + '</td>' +
          '<td>' +
            '<button type="button" data-act="move-up">&uarr;</button> ' +
            '<button type="button" data-act="move-down">&darr;</button> ' +
            '<button type="button" data-act="save">Ulozit</button> ' +
            '<button type="button" data-act="toggle" data-val="' + (aktivni ? '0' : '1') + '">' + (aktivni ? 'Deaktivovat' : 'Aktivovat') + '</button>' +
          '</td>' +
        '</tr>';
    }).join('');

    tbodies.forEach((tbody) => {
      tbody.innerHTML = html;
    });
  }

  async function refreshList(root) {
    if (!root) return;
    try {
      setMsg(root, 'Nacitam seznam karet...', false);
      const data = await apiGetList(root);
      const items = Array.isArray(data.items) ? data.items : [];
      renderList(root, items);
      root.querySelectorAll('[data-admink-count]').forEach((el) => {
        el.textContent = String(items.length);
      });
      let lastName = '-';
      let lastId = -1;
      items.forEach((it) => {
        const id = toInt(it.id_karta, 0);
        if (id > lastId) {
          lastId = id;
          lastName = String(it.nazev || '').trim() || '-';
        }
      });
      root.querySelectorAll('[data-admink-last]').forEach((el) => {
        el.textContent = lastName;
      });
      setMsg(root, 'Seznam je aktualni.', false);
    } catch (e) {
      setMsg(root, e && e.message ? e.message : 'Nacteni se nepovedlo.', true);
    }
  }

  function initAdminKartyCard(root) {
    if (!root || root.getAttribute('data-admink-init') === '1') return;
    root.setAttribute('data-admink-init', '1');

    const compact = root.querySelector('[data-admink-compact]');
    const expanded = root.querySelector('[data-admink-expanded]');
    const toggle = root.querySelector('[data-admink-toggle]');
    const dashCard = root.closest('.dash_card');
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

    const setExpanded = (on) => {
      const isOn = !!on;
      if (compact) compact.classList.toggle('is-hidden', isOn);
      if (expanded) expanded.classList.toggle('is-hidden', !isOn);
      if (dashCard) dashCard.classList.toggle('is-expanded', isOn);
      if (toggle) {
        toggle.textContent = isOn ? '-' : '\u2197\u2199';
        toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
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

    setExpanded(false);
    setTab(readSavedTab());

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!isExpanded);
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

    const compact = root.querySelector('[data-zr-compact]');
    const expanded = root.querySelector('[data-zr-expanded]');
    const toggle = root.querySelector('[data-zr-toggle]');
    const dashCard = root.closest('.dash_card');

    const setExpanded = (on) => {
      const isOn = !!on;
      if (compact) compact.classList.toggle('is-hidden', isOn);
      if (expanded) expanded.classList.toggle('is-hidden', !isOn);
      if (dashCard) dashCard.classList.toggle('is-expanded', isOn);
      if (toggle) {
        toggle.textContent = isOn ? '-' : '\u2197\u2199';
        toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
      }
    };

    // Mock API values (zatim natvrdo pro vzhled).
    const setMockApiValues = () => {
      const map = {
        api_pocet_obj: '78',
        api_make_time: '13 min 24 s',
        api_zrusene_ks: '0',
        api_zrusene_castka: '0'
      };
      Object.keys(map).forEach((key) => {
        const el = root.querySelector('[name=\"' + key + '\"]');
        if (el instanceof HTMLInputElement) {
          el.value = map[key];
        }
      });
    };

    setExpanded(false);
    setMockApiValues();

    if (toggle) {
      toggle.addEventListener('click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        setExpanded(!isExpanded);
      });
    }
  }

  function getVyjMsgEl(root) {
    return root ? root.querySelector('.cb-admin-vyjimky-msg') : null;
  }

  function setVyjMsg(root, text, isErr) {
    const el = getVyjMsgEl(root);
    if (!el) return;
    el.textContent = String(text || '');
    el.style.color = isErr ? '#b91c1c' : '#166534';
  }

  function getVyjLogMsgEl(root) {
    return root ? root.querySelector('.cb-admin-vyjimky-log-msg') : null;
  }

  function setVyjLogMsg(root, text, isErr) {
    const el = getVyjLogMsgEl(root);
    if (!el) return;
    el.textContent = String(text || '');
    el.style.color = isErr ? '#b91c1c' : '#166534';
  }

  function getVyjLogState(root) {
    if (!root) return { page: 1, perPage: 50, pages: 1, total: 0 };
    if (!root.__cbVyjLogState || typeof root.__cbVyjLogState !== 'object') {
      root.__cbVyjLogState = { page: 1, perPage: 50, pages: 1, total: 0 };
    }
    return root.__cbVyjLogState;
  }

  function renderVyjLogPager(root) {
    if (!root) return;
    const st = getVyjLogState(root);
    const info = root.querySelector('[data-cb-vyj-log-pageinfo]');
    if (info) {
      info.textContent = 'Stranka ' + st.page + '/' + st.pages + ' (celkem: ' + st.total + ')';
    }
  }

  function modeFromLog(akce, aktivni) {
    if (toInt(aktivni, 0) !== 1) return 'role';
    const a = String(akce || '').toLowerCase();
    if (a === 'allow') return 'allow';
    if (a === 'deny') return 'deny';
    return 'role';
  }

  function modeBadge(mode) {
    const m = String(mode || 'role');
    if (m === 'allow') {
      return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:700;">allow</span>';
    }
    if (m === 'deny') {
      return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;">deny</span>';
    }
    return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#e5e7eb;color:#374151;font-weight:700;">role</span>';
  }

  function filterVyjLogItems(root, items) {
    const arr0 = Array.isArray(items) ? items : [];
    const inp = root?.querySelector('[data-cb-vyj-log-search]');
    const q = (inp instanceof HTMLInputElement) ? String(inp.value || '').trim().toLowerCase() : '';

    if (q === '') return arr0;

    return arr0.filter((it) => {
      const hay = [
        it.kdy, it.cil_jmeno, it.cil_email, it.provedl_jmeno, it.provedl_email,
        it.karta_nazev, it.karta_kod, it.poznamka, it.stara_akce, it.nova_akce
      ].map((v) => String(v || '').toLowerCase()).join(' ');
      return hay.includes(q);
    });
  }

  function csvCell(v) {
    const s = String(v ?? '');
    return '"' + s.replace(/"/g, '""') + '"';
  }

  function exportVyjLogCsv(root, items) {
    const arr = filterVyjLogItems(root, items);
    if (arr.length === 0) {
      setVyjLogMsg(root, 'Neni co exportovat (zadne zaznamy po filtru).', true);
      return;
    }

    const head = [
      'kdy', 'cilovy_user', 'cilovy_email', 'karta_nazev', 'karta_kod',
      'zmena_od', 'zmena_na', 'provedl', 'provedl_email', 'poznamka'
    ];

    const lines = [head.map(csvCell).join(';')];
    arr.forEach((it) => {
      const fromMode = modeFromLog(it.stara_akce, it.stara_aktivni);
      const toMode = modeFromLog(it.nova_akce, it.nova_aktivni);
      lines.push([
        it.kdy || '',
        it.cil_jmeno || ('ID ' + toInt(it.id_user_cil, 0)),
        it.cil_email || '',
        it.karta_nazev || '',
        it.karta_kod || '',
        fromMode,
        toMode,
        it.provedl_jmeno || ('ID ' + toInt(it.provedl_id_user, 0)),
        it.provedl_email || '',
        it.poznamka || ''
      ].map(csvCell).join(';'));
    });

    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const fn = 'vyjimky_log_' +
      now.getFullYear() +
      pad(now.getMonth() + 1) +
      pad(now.getDate()) + '_' +
      pad(now.getHours()) +
      pad(now.getMinutes()) +
      pad(now.getSeconds()) + '.csv';

    const content = '\uFEFF' + lines.join('\r\n');
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fn;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    setVyjLogMsg(root, 'CSV export vytvoren.', false);
  }

  async function refreshVyjUsers(root) {
    if (!root) return;

    const sel = root.querySelector('[data-cb-vyj-user]');
    if (!(sel instanceof HTMLSelectElement)) return;
    const selected = String(sel.value || '');

    try {
      setVyjMsg(root, 'Nacitam uzivatele...', false);
      const data = await apiGet(root, 'users');
      const items = Array.isArray(data.items) ? data.items : [];

      let html = '<option value="">Vyber uzivatele...</option>';
      items.forEach((u) => {
        const id = toInt(u.id_user, 0);
        const full = (String(u.prijmeni || '').trim() + ' ' + String(u.jmeno || '').trim()).trim();
        const email = String(u.email || '').trim();
        const role = toInt(u.id_role, 0);
        const label = (full !== '' ? full : ('ID ' + id)) + (email !== '' ? (' <' + email + '>') : '') + (' [role ' + role + ']');
        html += '<option value="' + esc(id) + '">' + esc(label) + '</option>';
      });

      sel.innerHTML = html;
      if (selected !== '') {
        const opts = Array.from(sel.options || []);
        const hasSelected = opts.some((o) => String(o.value) === selected);
        if (hasSelected) sel.value = selected;
      }

      setVyjMsg(root, 'Uzivatele jsou aktualni.', false);
    } catch (e) {
      setVyjMsg(root, e && e.message ? e.message : 'Nacteni uzivatelu se nepovedlo.', true);
    }
  }

  function renderVyjList(root, items) {
    const tbody = root?.querySelector('[data-cb-vyjimky-list]');
    if (!tbody) return;

    if (!Array.isArray(items) || items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">Zadne karty.</td></tr>';
      return;
    }

    const html = items.map((it) => {
      const idKarta = toInt(it.id_karta, 0);
      const roleAllowed = toInt(it.role_allowed, 0) === 1;
      const override = String(it.override || 'role');
      const effective = toInt(it.effective, 0) === 1;
      const disabled = toInt(it.karta_aktivni, 0) === 1 ? '' : ' disabled';

      return '' +
        '<tr data-id-karta="' + esc(idKarta) + '">' +
          '<td>' + esc(it.nazev || it.kod || ('ID ' + idKarta)) + '</td>' +
          '<td>' + esc(it.soubor || '') + '</td>' +
          '<td>' + (roleAllowed ? 'povoleno' : 'zakazano') + '</td>' +
          '<td>' + esc(override) + '</td>' +
          '<td>' + (effective ? 'uvidi' : 'neuvidi') + '</td>' +
          '<td>' +
            '<button type="button" data-cb-vyj-set="role"' + disabled + '>role</button> ' +
            '<button type="button" data-cb-vyj-set="allow"' + disabled + '>allow</button> ' +
            '<button type="button" data-cb-vyj-set="deny"' + disabled + '>deny</button>' +
          '</td>' +
        '</tr>';
    }).join('');

    tbody.innerHTML = html;
  }

  async function refreshVyjCards(root) {
    if (!root) return;
    const sel = root.querySelector('[data-cb-vyj-user]');
    const tbody = root.querySelector('[data-cb-vyjimky-list]');
    if (!(sel instanceof HTMLSelectElement) || !tbody) return;

    const idUser = toInt(sel.value, 0);
    if (idUser <= 0) {
      tbody.innerHTML = '<tr><td colspan="6">Vyber uzivatele.</td></tr>';
      return;
    }

    try {
      setVyjMsg(root, 'Nacitam vyjimky...', false);
      const data = await apiGet(root, 'vyjimky', { id_user: idUser });
      const items = Array.isArray(data.items) ? data.items : [];
      renderVyjList(root, items);
      setVyjMsg(root, 'Vyjimky jsou aktualni.', false);
    } catch (e) {
      setVyjMsg(root, e && e.message ? e.message : 'Nacteni vyjimek se nepovedlo.', true);
    }
  }

  function renderVyjLogList(root, items) {
    const tbody = root?.querySelector('[data-cb-vyjimky-log-list]');
    if (!tbody) return;

    const arr = filterVyjLogItems(root, items);

    if (arr.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">Zatim bez zaznamu.</td></tr>';
      return;
    }

    const html = arr.map((it) => {
      const cil = (String(it.cil_jmeno || '').trim() || ('ID ' + toInt(it.id_user_cil, 0))) +
        (String(it.cil_email || '').trim() ? (' <' + String(it.cil_email) + '>') : '');
      const provedl = (String(it.provedl_jmeno || '').trim() || ('ID ' + toInt(it.provedl_id_user, 0))) +
        (String(it.provedl_email || '').trim() ? (' <' + String(it.provedl_email) + '>') : '');
      const karta = String(it.karta_nazev || '').trim() !== ''
        ? String(it.karta_nazev) + ' (' + String(it.karta_kod || '') + ')'
        : ('ID ' + toInt(it.id_karta, 0));
      const fromMode = modeFromLog(it.stara_akce, it.stara_aktivni);
      const toMode = modeFromLog(it.nova_akce, it.nova_aktivni);
      const zmena = modeBadge(fromMode) + ' <span style="opacity:.65;">Ă˘â€ â€™</span> ' + modeBadge(toMode);

      return '' +
        '<tr>' +
          '<td>' + esc(it.kdy || '') + '</td>' +
          '<td>' + esc(cil) + '</td>' +
          '<td>' + esc(karta) + '</td>' +
          '<td>' + zmena + '</td>' +
          '<td>' + esc(provedl) + '</td>' +
          '<td>' + esc(it.poznamka || '') + '</td>' +
        '</tr>';
    }).join('');

    tbody.innerHTML = html;
  }

  async function refreshVyjLogCards(root) {
    if (!root) return;
    const sel = root.querySelector('[data-cb-vyj-log-karta]');
    if (!(sel instanceof HTMLSelectElement)) return;
    const selected = String(sel.value || '');

    try {
      const data = await apiGet(root, 'list');
      const items = Array.isArray(data.items) ? data.items : [];

      let html = '<option value="">Vsechny karty</option>';
      items.forEach((k) => {
        const id = toInt(k.id_karta, 0);
        const nazev = String(k.nazev || k.kod || ('ID ' + id));
        const kod = String(k.kod || '');
        html += '<option value="' + esc(id) + '">' + esc(nazev + (kod ? (' (' + kod + ')') : '')) + '</option>';
      });

      sel.innerHTML = html;
      if (selected !== '') {
        const opts = Array.from(sel.options || []);
        const hasSelected = opts.some((o) => String(o.value) === selected);
        if (hasSelected) sel.value = selected;
      }
    } catch (e) {
      // Neni kriticke, historie funguje i bez filtru karet.
    }
  }

  async function refreshVyjLog(root, filterUserId, filterCardId, forcePage) {
    if (!root) return;
    try {
      setVyjLogMsg(root, 'Nacitam historii...', false);
      const st = getVyjLogState(root);
      const selPer = root.querySelector('[data-cb-vyj-log-per-page]');
      if (selPer instanceof HTMLSelectElement) {
        const pp = toInt(selPer.value, 50);
        st.perPage = (pp === 20 || pp === 50 || pp === 100) ? pp : 50;
      }
      if (typeof forcePage !== 'undefined' && forcePage !== null) {
        st.page = Math.max(1, toInt(forcePage, 1));
      }

      const p = { page: st.page, per_page: st.perPage };
      const idUser = toInt(filterUserId, 0);
      const idKarta = toInt(filterCardId, 0);
      if (idUser > 0) p.id_user = idUser;
      if (idKarta > 0) p.id_karta = idKarta;

      const data = await apiGet(root, 'vyjimky_log', p);
      const items = Array.isArray(data.items) ? data.items : [];
      root.__cbVyjLogItems = items;
      st.page = Math.max(1, toInt(data.page, st.page));
      st.perPage = (toInt(data.per_page, st.perPage) || st.perPage);
      st.pages = Math.max(1, toInt(data.pages, 1));
      st.total = Math.max(0, toInt(data.total, 0));
      renderVyjLogList(root, items);
      renderVyjLogPager(root);
      setVyjLogMsg(root, 'Historie je aktualni.', false);
    } catch (e) {
      setVyjLogMsg(root, e && e.message ? e.message : 'Nacteni historie se nepovedlo.', true);
    }
  }

  function readRowPayload(tr) {
    const id = toInt(tr?.getAttribute('data-id'), 0);
    const n = tr?.querySelector('input[data-f="nazev"]')?.value ?? '';
    const s = tr?.querySelector('input[data-f="soubor"]')?.value ?? '';
    const r = toInt(tr?.querySelector('input[data-f="min_role"]')?.value, 9);
    const p = toInt(tr?.querySelector('input[data-f="poradi"]')?.value, 999);

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
      kod: String(fd.get('kod') || '').trim(),
      nazev: String(fd.get('nazev') || '').trim(),
      soubor: String(fd.get('soubor') || '').trim(),
      min_role: toInt(fd.get('min_role'), 2),
      poradi: toInt(fd.get('poradi'), 100)
    };

    try {
      setMsg(root, 'Ukladam novou kartu...', false);
      await apiPost(root, payload);
      form.reset();
      await refreshList(root);
      setMsg(root, 'Karta byla pridana.', false);
    } catch (e) {
      setMsg(root, e && e.message ? e.message : 'Pridani selhalo.', true);
    }
  }

  async function onClick(ev) {
    const btn = ev.target?.closest('button');
    if (!btn) return;

    const root = btn.closest('.cb-admin-karty');
    const vyjRoot = btn.closest('.cb-admin-vyjimky');

    if (root && btn.hasAttribute('data-cb-karty-refresh')) {
      await refreshList(root);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-refresh-users')) {
      await refreshVyjUsers(vyjRoot);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-refresh-cards')) {
      await refreshVyjCards(vyjRoot);
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-refresh')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || getVyjLogRoot();
      const selUser = document.querySelector('[data-cb-vyj-user]');
      const selKarta = document.querySelector('[data-cb-vyj-log-karta]');
      const userId = (selUser instanceof HTMLSelectElement) ? toInt(selUser.value, 0) : 0;
      const kartaId = (selKarta instanceof HTMLSelectElement) ? toInt(selKarta.value, 0) : 0;
      if (logRoot) {
        await refreshVyjLog(logRoot, userId, kartaId, 1);
      }
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-export')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || getVyjLogRoot();
      if (!logRoot) return;
      const items = Array.isArray(logRoot.__cbVyjLogItems) ? logRoot.__cbVyjLogItems : [];
      exportVyjLogCsv(logRoot, items);
      return;
    }

    if (btn.hasAttribute('data-cb-vyj-log-prev') || btn.hasAttribute('data-cb-vyj-log-next')) {
      const logRoot = btn.closest('.cb-admin-vyjimky-log') || getVyjLogRoot();
      if (!logRoot) return;
      const st = getVyjLogState(logRoot);
      const next = btn.hasAttribute('data-cb-vyj-log-next');
      const targetPage = next ? (st.page + 1) : (st.page - 1);
      const selUser = document.querySelector('[data-cb-vyj-user]');
      const selKarta = document.querySelector('[data-cb-vyj-log-karta]');
      const userId = (selUser instanceof HTMLSelectElement) ? toInt(selUser.value, 0) : 0;
      const kartaId = (selKarta instanceof HTMLSelectElement) ? toInt(selKarta.value, 0) : 0;
      await refreshVyjLog(logRoot, userId, kartaId, targetPage);
      return;
    }

    if (vyjRoot && btn.hasAttribute('data-cb-vyj-set')) {
      const tr = btn.closest('tr[data-id-karta]');
      const sel = vyjRoot.querySelector('[data-cb-vyj-user]');
      if (!tr || !(sel instanceof HTMLSelectElement)) return;

      const idUser = toInt(sel.value, 0);
      const idKarta = toInt(tr.getAttribute('data-id-karta'), 0);
      const mode = String(btn.getAttribute('data-cb-vyj-set') || '');
      if (idUser <= 0 || idKarta <= 0 || (mode !== 'role' && mode !== 'allow' && mode !== 'deny')) return;

      try {
        setVyjMsg(vyjRoot, 'Ukladam vyjimku...', false);
        await apiPost(vyjRoot, {
          action: 'set_vyjimka',
          id_user: idUser,
          id_karta: idKarta,
          mode: mode
        });
        await refreshVyjCards(vyjRoot);
        const logRoot = getVyjLogRoot();
        if (logRoot) {
          const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
          const kartaId = (selKarta instanceof HTMLSelectElement) ? toInt(selKarta.value, 0) : 0;
          await refreshVyjLog(logRoot, idUser, kartaId, 1);
        }
        setVyjMsg(vyjRoot, 'Vyjimka byla ulozena.', false);
      } catch (e) {
        setVyjMsg(vyjRoot, e && e.message ? e.message : 'Ulozeni vyjimky selhalo.', true);
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
        setMsg(root, 'Ukladam zmenu karty...', false);
        await apiPost(root, {
          action: 'update',
          id_karta: row.id_karta,
          nazev: row.nazev,
          soubor: row.soubor,
          min_role: row.min_role,
          poradi: row.poradi
        });
        await refreshList(root);
        setMsg(root, 'Zmena byla ulozena.', false);
        return;
      }

      if (action === 'toggle') {
        const id = toInt(tr.getAttribute('data-id'), 0);
        const val = toInt(btn.getAttribute('data-val'), 0) === 1 ? 1 : 0;
        setMsg(root, 'Ukladam stav karty...', false);
        await apiPost(root, {
          action: 'toggle',
          id_karta: id,
          aktivni: val
        });
        await refreshList(root);
        setMsg(root, 'Stav karty byl zmenen.', false);
        return;
      }

      if (action === 'move-up' || action === 'move-down') {
        const id = toInt(tr.getAttribute('data-id'), 0);
        const dir = (action === 'move-up') ? 'up' : 'down';
        setMsg(root, 'Meni poradi karty...', false);
        await apiPost(root, {
          action: 'move',
          id_karta: id,
          direction: dir
        });
        await refreshList(root);
        setMsg(root, 'Poradi bylo upraveno.', false);
        return;
      }
    } catch (e) {
      setMsg(root, e && e.message ? e.message : 'Akce se nepovedla.', true);
    }
  }

  function init() {
    const root = getRoot();
    if (root) {
      initAdminKartyCard(root);
    }
    if (root && root.getAttribute('data-init') !== '1') {
      // Znacka zabranici opakovane inicializaci stejneho DOM uzlu.
      root.setAttribute('data-init', '1');
      refreshList(root);
    }

    const zrRoot = document.querySelector('.cb-zadani-reportu');
    if (zrRoot) {
      initZadaniReportuCard(zrRoot);
    }

    const vyjRoot = getVyjRoot();
    if (vyjRoot && vyjRoot.getAttribute('data-init') !== '1') {
      vyjRoot.setAttribute('data-init', '1');
      refreshVyjUsers(vyjRoot);
    }

    const logRoot = getVyjLogRoot();
    if (logRoot && logRoot.getAttribute('data-init') !== '1') {
      logRoot.setAttribute('data-init', '1');
      refreshVyjLogCards(logRoot).then(() => {
        refreshVyjLog(logRoot, 0, 0, 1);
      });
    }
  }

  document.addEventListener('submit', onAddSubmit, true);
  document.addEventListener('click', onClick, true);
  document.addEventListener('change', (ev) => {
    const sel = ev.target;
    if (!(sel instanceof HTMLSelectElement)) return;
    if (!sel.hasAttribute('data-cb-vyj-user')) return;

    const vyjRoot = sel.closest('.cb-admin-vyjimky');
    if (!vyjRoot) return;
    refreshVyjCards(vyjRoot);

    const logRoot = getVyjLogRoot();
    if (logRoot) {
      const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
      const kartaId = (selKarta instanceof HTMLSelectElement) ? toInt(selKarta.value, 0) : 0;
      refreshVyjLog(logRoot, toInt(sel.value, 0), kartaId, 1);
    }
  }, true);
  document.addEventListener('change', (ev) => {
    const sel = ev.target;
    if (!(sel instanceof HTMLSelectElement)) return;
    if (!sel.hasAttribute('data-cb-vyj-log-karta')) return;

    const logRoot = sel.closest('.cb-admin-vyjimky-log');
    if (!logRoot) return;

    const selUser = document.querySelector('[data-cb-vyj-user]');
    const userId = (selUser instanceof HTMLSelectElement) ? toInt(selUser.value, 0) : 0;
    refreshVyjLog(logRoot, userId, toInt(sel.value, 0), 1);
  }, true);
  document.addEventListener('change', (ev) => {
    const sel = ev.target;
    if (!(sel instanceof HTMLSelectElement)) return;
    if (!sel.hasAttribute('data-cb-vyj-log-per-page')) return;

    const logRoot = sel.closest('.cb-admin-vyjimky-log');
    if (!logRoot) return;
    const selUser = document.querySelector('[data-cb-vyj-user]');
    const selKarta = logRoot.querySelector('[data-cb-vyj-log-karta]');
    const userId = (selUser instanceof HTMLSelectElement) ? toInt(selUser.value, 0) : 0;
    const kartaId = (selKarta instanceof HTMLSelectElement) ? toInt(selKarta.value, 0) : 0;
    refreshVyjLog(logRoot, userId, kartaId, 1);
  }, true);
  document.addEventListener('input', (ev) => {
    const el = ev.target;
    if (!(el instanceof HTMLInputElement)) return;
    if (!el.hasAttribute('data-cb-vyj-log-search')) return;

    const logRoot = el.closest('.cb-admin-vyjimky-log');
    if (!logRoot) return;
    const items = Array.isArray(logRoot.__cbVyjLogItems) ? logRoot.__cbVyjLogItems : [];
    renderVyjLogList(logRoot, items);
  }, true);
  document.addEventListener('cb:main-swapped', init);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }

  CB_ADMIN.init = init;

})(window);

// js/menu_ajax.js * Verze: V12 * Aktualizace: 07.03.2026 * Pocet radku: 805
// Konec souboru

