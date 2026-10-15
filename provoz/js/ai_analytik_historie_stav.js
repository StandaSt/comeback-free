/* Pamatuje otevrene audity a rozpracovany formular v karte; vysledky obnovuje z trvale DB historie. */
(function () {
  'use strict';

  // Oddeleni podle uzivatele a loginu zabrani prenosu rozpracovaneho pohledu mezi prihlasenimi.
  function key(root) { return 'cb-franta-view:' + root.dataset.historyUser + ':' + root.dataset.historyLogin; }
  // Nedostupne uloziste nema vliv na trvale ulozenou historii na serveru.
  function read(root) {
    try { return JSON.parse(sessionStorage.getItem(key(root)) || '{}'); } catch (error) { return {}; }
  }
  // Ukladame jen identifikatory pohledu a formular, nikoli kopie tabulek a grafu.
  function write(root, state) {
    try { sessionStorage.setItem(key(root), JSON.stringify(state)); } catch (error) { /* Zakazane uloziste nebrani praci s DB historii. */ }
  }

  // Vynechame soubory a skryta bezpecnostni pole, ktera po navratu dodava cerstva stranka.
  function saveDraft(root) {
    var form = root.querySelector('[data-ai-analytik-form]');
    if (!form) return;
    var state = read(root);
    state.draft = Array.from(form.querySelectorAll('input,textarea,select')).filter(function (field) {
      return field.type !== 'file' && field.name && field.type !== 'hidden';
    }).map(function (field) {
      return { name: field.name, value: field.value, checked: field.checked };
    });
    state.source = form.dataset.sourceAuditId || '';
    write(root, state);
  }

  // Zaznamename i prijaty audit pred dokoncenim; po navratu tak nezmizi chyba nebo preruseni.
  function remember(root, id, continuation) {
    if (!id) return;
    var state = read(root);
    state.ids = state.ids || [];
    if (state.ids.indexOf(id) < 0) state.ids.push(id);
    state.tokens = state.tokens || {};
    if (continuation) state.tokens[id] = continuation.token;
    write(root, state);
    saveDraft(root);
  }

  // Pamatujeme otevreny prompt a rozbalena pouziti i pri odchodu do HR.
  function select(root, id) { var state = read(root); state.selected = Number(id); write(root, state); }
  function open(root, id, value) {
    var state = read(root);
    state.opened = state.opened || {};
    state.opened[id] = value;
    write(root, state);
  }

  // Navrat do Franty pouziva pouze cteci endpointy, nikdy znovu nespousti prompt.
  function init() {
    var root = document.querySelector('[data-ai-analytik]');
    if (!root || root.dataset.historyInitialized) return;
    root.dataset.historyInitialized = '1';
    var state = read(root);
    var form = root.querySelector('[data-ai-analytik-form]');
    if (form && state.draft) {
      Array.from(form.querySelectorAll('input,textarea,select')).forEach(function (field) {
        var saved = state.draft.find(function (item) {
          return item.name === field.name && ((field.type !== 'checkbox' && field.type !== 'radio') || item.value === field.value);
        });
        if (!saved || field.type === 'file' || field.type === 'hidden') return;
        if (field.type === 'checkbox' || field.type === 'radio') field.checked = !!saved.checked;
        else field.value = saved.value;
      });
      form.dataset.sourceAuditId = state.source || '';
      form.dispatchEvent(new Event('change', { bubbles: true }));
    }
    root.addEventListener('input', function () { saveDraft(root); });
    root.addEventListener('change', function () { saveDraft(root); });
    if (state.selected) window.CB_AI_HISTORY.show(root, state.selected, true);
    var target = root.querySelector('[data-ai-analytik-results]');
    var chain = Promise.resolve();
    (state.ids || []).forEach(function (id) {
      chain = chain.then(function () { return window.CB_AI_HISTORY.detail(root, id, true); }).then(function (data) {
        if (!root.isConnected || root.dataset.aiViewChanged === '1') return;
        data.turns.forEach(function (turn) { window.CB_AI_HISTORY.renderTurn(root, turn, target); });
      }).catch(function (error) {
        if (!root.isConnected) return;
        var message = document.createElement('p');
        message.textContent = 'Obnovení výsledku: ' + error.message;
        target.appendChild(message);
      });
    });
  }

  window.CB_AI_HISTORY_STATE = {
    remember: remember, saveDraft: saveDraft, select: select,
    open: open, isOpen: function (root, id) { return !!(read(root).opened || {})[id]; },
    token: function (root, id) { return (read(root).tokens || {})[id] || ''; }
  };
  document.addEventListener('cb:main-swapped', init);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
