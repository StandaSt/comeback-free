/* Otevira historii rovnou s poslednimi vysledky; archiv nevola AI a sdili renderer Franty. */
(function () {
  'use strict';

  // Vsechny historie prochazeji stejnym autorizovanym endpointem jako formular.
  function request(root, action, values) {
    return fetch(root.dataset.endpoint, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Comeback-AI-Analytik': '1' },
      body: JSON.stringify(Object.assign({ action: action, csrf: root.dataset.csrf }, values))
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data.ok) throw new Error(data.error || 'Historii se nepodařilo načíst.');
        return data;
      });
    });
  }

  // Text v historii vkladame jako textContent, nikdy jako HTML z ulozene odpovedi.
  function node(tag, text) {
    var element = document.createElement(tag);
    if (text !== undefined) element.textContent = String(text);
    return element;
  }

  // Tlacitka historie nesmeji odesilat hlavni formular promptu.
  function button(text, action) {
    var element = node('button', text);
    element.type = 'button';
    element.className = 'head_task_btn';
    element.addEventListener('click', action);
    return element;
  }

  // Databazovy cas zobrazime vcetne hodin a sekund pro rozliseni pouziti.
  function date(value) {
    var parsed = new Date(String(value).replace(' ', 'T'));
    return isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString('cs-CZ');
  }

  // Stav serveru se zobrazi i tehdy, kdy kolo nema vysledek (chyba, zruseni, stary audit).
  function renderTurn(root, turn, target) {
    if (!turn.data) {
      target.appendChild(node('p', 'Zadání: ' + turn.prompt));
      var message = turn.error || ({ bezi: 'Zpracování nebylo dokončeno; obnovte detail pro aktuální stav.',
        zruseno: 'Analýza byla zrušena.', preruseno: 'Analýza byla přerušena.' })[turn.status]
        || 'Toto kolo skončilo bez výsledku.';
      target.appendChild(node('p', message));
      return;
    }
    var data = turn.data;
    var meta = data.meta || {};
    var state = { history: data.processing || [], startedAt: 0, completedAt: 1,
      apiCalls: meta.api_calls || 0, sqlCount: meta.sql_count || 0, error: false, activeMessage: '' };
    window.CB_AI_ANALYTIK.renderResult(root, data, turn.prompt, state, target);
  }

  // Aktivni navazani obnovujeme jen na pracovni plose, archiv zustava pouze ke cteni.
  function detail(root, id, resume) {
    var state = window.CB_AI_HISTORY_STATE;
    return request(root, 'history_detail', { audit_id: Number(id),
      continuation_token: resume && state ? state.token(root, id) : '' });
  }

  // Posledni pouziti otevreme rovnou, starsi velke vysledky nacitame az na vyzadani.
  function usage(root, item, openImmediately) {
    var wrap = node('details');
    wrap.className = 'ai_historie_usage';
    var labels = { completed: 'dokončeno', awaiting_followup: 'výsledek / možnost navázat',
      awaiting_clarification: 'čeká na upřesnění', cancelled: 'zrušeno', connection_lost: 'přerušeno', error: 'chyba' };
    wrap.appendChild(node('summary', date(item.created_at) + ' · ' + item.model + ' · '
      + (labels[item.status] || item.status)));
    var content = node('div');
    wrap.appendChild(content);
    var busy = false;
    function load() {
      if (busy) return;
      busy = true;
      content.textContent = 'Načítám výsledky…';
      detail(root, item.id).then(function (data) {
        content.replaceChildren();
        if (!data.turns.length) content.appendChild(node('p', 'Výsledek nebyl archivován.'));
        // Stejne jako na pracovni plose zobrazujeme nejnovejsi upresneni jako prvni.
        data.turns.slice().reverse().forEach(function (turn) {
          var block = node('section');
          block.appendChild(node('h4', turn.order + '. ' + ({prompt: 'Zadání', upresneni: 'Upřesnění', navazani: 'Navázání'})[turn.kind]
            + ' · ' + date(turn.created_at)));
          var result = node('div');
          block.appendChild(result);
          content.appendChild(block);
          renderTurn(root, turn, result);
        });
        content.appendChild(button('Obnovit detail', load));
      }).catch(function (error) {
        content.textContent = error.message;
        content.appendChild(button('Zkusit znovu', load));
      }).finally(function () {
        busy = false;
        // Az vysledky vytvori skutecnou vysku stranky, muzeme je posunout uzivateli pred oci.
        if (openImmediately && wrap.open && wrap.isConnected) {
          var panel = wrap.closest('[data-ai-history-panel]');
          if (panel && !panel.hidden) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        openImmediately = false;
      });
    }
    wrap.addEventListener('toggle', function () {
      if (window.CB_AI_HISTORY_STATE) window.CB_AI_HISTORY_STATE.open(root, item.id, wrap.open);
      if (wrap.open && !content.childNodes.length) load();
    });
    if (openImmediately || (window.CB_AI_HISTORY_STATE && window.CB_AI_HISTORY_STATE.isOpen(root, item.id))) wrap.open = true;
    return wrap;
  }

  // Otevreni vybraneho promptu ma vlastni panel a strankovani jednotlivych pouziti.
  function show(root, id, restoring) {
    var panel = root.querySelector('[data-ai-history-panel]');
    if (!panel) return Promise.resolve();
    panel.hidden = false;
    panel.replaceChildren();
    var title = node('h2', 'Historie výsledků');
    panel.appendChild(title);
    panel.appendChild(button('Zavřít historii', function () {
      panel.hidden = true;
      if (window.CB_AI_HISTORY_STATE) window.CB_AI_HISTORY_STATE.select(root, 0);
    }));
    var list = node('div');
    panel.appendChild(list);
    if (window.CB_AI_HISTORY_STATE) window.CB_AI_HISTORY_STATE.select(root, id);
    // Kurzor je ID posledniho pouziti, takze nova analyza nerozhodi dalsi stranku.
    function page(before) {
      var loading = node('p', 'Načítám historii…');
      list.appendChild(loading);
      return request(root, 'history_uses', { audit_id: Number(id), before_id: before || 0 }).then(function (data) {
        loading.remove();
        title.textContent = 'Historie výsledků: ' + data.prompt;
        // Prime kliknuti vzdy ukaze posledni vysledky, i kdyz je uzivatel drive sbalil.
        data.uses.forEach(function (item, index) {
          list.appendChild(usage(root, item, !restoring && !before && index === 0));
        });
        if (data.next) {
          var more = button('Další použití', function () { more.remove(); page(data.next); });
          list.appendChild(more);
        }
      }).catch(function (error) {
        loading.textContent = error.message;
        list.appendChild(button('Zkusit znovu', function () { loading.remove(); page(before); }));
      });
    }
    return page(0);
  }

  window.CB_AI_HISTORY = {
    button: function (root, prompt) { return button('Historie výsledků', function () { show(root, prompt.id); }); },
    show: show, detail: detail, renderTurn: renderTurn
  };
})();
