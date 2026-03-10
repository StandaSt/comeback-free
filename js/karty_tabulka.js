// js/karty_tabulka.js * Verze: V1 * Aktualizace: 09.03.2026
'use strict';

(function (w) {
  const K = w.CB_KARTY || (w.CB_KARTY = {});
  const esc = K.esc;
  const toInt = K.toInt;

  function renderList(root, items) {
    const tbodies = root ? root.querySelectorAll('[data-cb-karty-list]') : [];
    if (!tbodies || tbodies.length === 0) return;

    if (!Array.isArray(items) || items.length === 0) {
      tbodies.forEach((tbody) => {
        tbody.innerHTML = '<tr><td colspan="7">Zatim nejsou zadne karty.</td></tr>';
      });
      return;
    }

    const html = items.map((it) => {
      const id = toInt(it.id_karta, 0);
      const aktivni = toInt(it.aktivni, 0) === 1;
      return '' +
        '<tr data-id="' + esc(id) + '">' +
          '<td>' + esc(id) + '</td>' +
          '<td><input type="text" data-f="nazev" value="' + esc(it.nazev) + '" maxlength="120"></td>' +
          '<td><input type="text" data-f="soubor" value="' + esc(it.soubor) + '" maxlength="80"></td>' +
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
      K.setMsg(root, 'Nacitam seznam karet...', false);
      const data = await K.apiGetList(root);
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

      K.setMsg(root, 'Seznam je aktualni.', false);
    } catch (e) {
      K.setMsg(root, e && e.message ? e.message : 'Nacteni se nepovedlo.', true);
    }
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
    if (m === 'allow') return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:700;">allow</span>';
    if (m === 'deny') return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;">deny</span>';
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
        it.karta_nazev, it.poznamka, it.stara_akce, it.nova_akce
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
      K.setVyjLogMsg(root, 'Neni co exportovat (zadne zaznamy po filtru).', true);
      return;
    }

    const head = [
      'kdy', 'cilovy_user', 'cilovy_email', 'karta_nazev',
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

    K.setVyjLogMsg(root, 'CSV export vytvoren.', false);
  }

  async function refreshVyjUsers(root) {
    if (!root) return;
    const sel = root.querySelector('[data-cb-vyj-user]');
    if (!(sel instanceof HTMLSelectElement)) return;
    const selected = String(sel.value || '');

    try {
      K.setVyjMsg(root, 'Nacitam uzivatele...', false);
      const data = await K.apiGet(root, 'users');
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
        if (opts.some((o) => String(o.value) === selected)) sel.value = selected;
      }

      K.setVyjMsg(root, 'Uzivatele jsou aktualni.', false);
    } catch (e) {
      K.setVyjMsg(root, e && e.message ? e.message : 'Nacteni uzivatelu se nepovedlo.', true);
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
          '<td>' + esc(it.nazev || ('ID ' + idKarta)) + '</td>' +
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
      K.setVyjMsg(root, 'Nacitam vyjimky...', false);
      const data = await K.apiGet(root, 'vyjimky', { id_user: idUser });
      renderVyjList(root, Array.isArray(data.items) ? data.items : []);
      K.setVyjMsg(root, 'Vyjimky jsou aktualni.', false);
    } catch (e) {
      K.setVyjMsg(root, e && e.message ? e.message : 'Nacteni vyjimek se nepovedlo.', true);
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
      const karta = String(it.karta_nazev || '').trim() !== '' ? String(it.karta_nazev) : ('ID ' + toInt(it.id_karta, 0));
      const fromMode = modeFromLog(it.stara_akce, it.stara_aktivni);
      const toMode = modeFromLog(it.nova_akce, it.nova_aktivni);
      const zmena = modeBadge(fromMode) + ' <span style="opacity:.65;">-></span> ' + modeBadge(toMode);

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
      const data = await K.apiGet(root, 'list');
      const items = Array.isArray(data.items) ? data.items : [];

      let html = '<option value="">Vsechny karty</option>';
      items.forEach((k) => {
        const id = toInt(k.id_karta, 0);
        const nazev = String(k.nazev || ('ID ' + id));
        html += '<option value="' + esc(id) + '">' + esc(nazev) + '</option>';
      });

      sel.innerHTML = html;
      if (selected !== '') {
        const opts = Array.from(sel.options || []);
        if (opts.some((o) => String(o.value) === selected)) sel.value = selected;
      }
    } catch (e) {
      // Neni kriticke, historie funguje i bez filtru karet.
    }
  }

  async function refreshVyjLog(root, filterUserId, filterCardId, forcePage) {
    if (!root) return;
    try {
      K.setVyjLogMsg(root, 'Nacitam historii...', false);
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

      const data = await K.apiGet(root, 'vyjimky_log', p);
      const items = Array.isArray(data.items) ? data.items : [];
      root.__cbVyjLogItems = items;
      st.page = Math.max(1, toInt(data.page, st.page));
      st.perPage = (toInt(data.per_page, st.perPage) || st.perPage);
      st.pages = Math.max(1, toInt(data.pages, 1));
      st.total = Math.max(0, toInt(data.total, 0));
      renderVyjLogList(root, items);
      renderVyjLogPager(root);
      K.setVyjLogMsg(root, 'Historie je aktualni.', false);
    } catch (e) {
      K.setVyjLogMsg(root, e && e.message ? e.message : 'Nacteni historie se nepovedlo.', true);
    }
  }

  K.renderList = renderList;
  K.refreshList = refreshList;
  K.getVyjLogState = getVyjLogState;
  K.renderVyjLogPager = renderVyjLogPager;
  K.modeFromLog = modeFromLog;
  K.filterVyjLogItems = filterVyjLogItems;
  K.exportVyjLogCsv = exportVyjLogCsv;
  K.refreshVyjUsers = refreshVyjUsers;
  K.renderVyjList = renderVyjList;
  K.refreshVyjCards = refreshVyjCards;
  K.renderVyjLogList = renderVyjLogList;
  K.refreshVyjLogCards = refreshVyjLogCards;
  K.refreshVyjLog = refreshVyjLog;
})(window);

// js/karty_tabulka.js * Verze: V1 * Aktualizace: 09.03.2026
// Konec souboru
