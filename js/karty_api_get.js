// js/karty_api_get.js * Verze: V1 * Aktualizace: 09.03.2026
'use strict';

(function (w) {
  const K = w.CB_KARTY || (w.CB_KARTY = {});

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
      throw new Error((data && data.err) ? String(data.err) : 'Nacteni selhalo.');
    }
    return data;
  }

  async function apiGetList(root) {
    return apiGet(root, 'list');
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

  K.esc = esc;
  K.toInt = toInt;
  K.getRoot = getRoot;
  K.getVyjRoot = getVyjRoot;
  K.getVyjLogRoot = getVyjLogRoot;
  K.setMsg = setMsg;
  K.setVyjMsg = setVyjMsg;
  K.setVyjLogMsg = setVyjLogMsg;
  K.apiGet = apiGet;
  K.apiGetList = apiGetList;
  K.apiPost = apiPost;
})(window);

// js/karty_api_get.js * Verze: V1 * Aktualizace: 09.03.2026
// Konec souboru
