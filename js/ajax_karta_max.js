// js/ajax_karta_max.js * Verze: V3 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  function isTargetForm(form) {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.getAttribute('data-cb-max-form') === '1') return true;

    const shell = form.closest('.card_shell');
    if (!(shell instanceof HTMLElement)) return false;
    if (shell.getAttribute('data-card-max-loaded') !== '1') return false;

    const expanded = form.closest('[data-card-expanded]');
    return expanded instanceof HTMLElement;
  }

  function resolveFormFromSubmitter(submitter) {
    if (!(submitter instanceof HTMLElement)) return null;
    const formAttr = String(submitter.getAttribute('form') || '').trim();
    if (formAttr !== '') {
      const direct = document.getElementById(formAttr);
      if (direct instanceof HTMLFormElement) return direct;
    }
    const parent = submitter.closest('form');
    return parent instanceof HTMLFormElement ? parent : null;
  }

  function markCardRefreshOnClose(form) {
    if (!(form instanceof HTMLFormElement)) return;
    const root = form.closest('.card_shell');
    if (!(root instanceof HTMLElement)) return;
    root.setAttribute('data-card-refresh-on-close', '1');
  }

  function getCardIdFromForm(form) {
    if (!(form instanceof HTMLFormElement)) return 0;
    const root = form.closest('.card_shell');
    if (!(root instanceof HTMLElement)) return 0;
    const id = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
    return Number.isFinite(id) && id > 0 ? id : 0;
  }

  function findCardWrapperFromHtml(html, cardId) {
    const id = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(id) || id <= 0) return null;

    const wrap = document.createElement('div');
    wrap.innerHTML = String(html || '').trim();

    const shellSelector = '[data-cb-dash-card="1"] .card_shell[data-card-id="' + String(id).replace(/"/g, '') + '"]';
    const shell = wrap.querySelector(shellSelector);
    if (!(shell instanceof HTMLElement)) return null;

    const card = shell.closest('[data-cb-dash-card="1"]');
    return card instanceof HTMLElement ? card : null;
  }

  function swapCardFromHtml(cardId, html) {
    const id = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(id) || id <= 0) return false;

    const currentShell = document.querySelector('[data-cb-dash-card="1"] .card_shell[data-card-id="' + String(id).replace(/"/g, '') + '"]');
    if (!(currentShell instanceof HTMLElement)) return false;

    const currentCard = currentShell.closest('[data-cb-dash-card="1"]');
    if (!(currentCard instanceof HTMLElement)) return false;

    const nextCard = findCardWrapperFromHtml(html, id);
    if (!(nextCard instanceof HTMLElement)) return false;

    currentCard.replaceWith(nextCard);
    document.dispatchEvent(new CustomEvent('cb:card-swapped', {
      detail: {
        cardId: id,
        card: nextCard
      }
    }));
    return true;
  }

  function swapCardExpandedFromHtml(cardId, html) {
    const id = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(id) || id <= 0) return false;

    const currentShell = document.querySelector('[data-cb-dash-card="1"] .card_shell[data-card-id="' + String(id).replace(/"/g, '') + '"]');
    if (!(currentShell instanceof HTMLElement)) return false;

    const currentCard = currentShell.closest('[data-cb-dash-card="1"]');
    if (!(currentCard instanceof HTMLElement)) return false;

    const currentExpanded = currentCard.querySelector('[data-card-expanded]');
    if (!(currentExpanded instanceof HTMLElement)) return false;

    const wrap = document.createElement('div');
    wrap.innerHTML = String(html || '').trim();

    const nextExpanded = wrap.querySelector('[data-card-expanded]');
    const nextContent = nextExpanded instanceof HTMLElement ? nextExpanded.innerHTML : String(html || '').trim();
    if (nextContent === '') return false;

    currentExpanded.innerHTML = nextContent;
    document.dispatchEvent(new CustomEvent('cb:card-swapped', {
      detail: {
        cardId: id,
        card: currentCard
      }
    }));
    return true;
  }

  function setLoading(on, text, mode) {
    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
      w.CB_AJAX.setDashboardLoading(!!on, String(mode || 'cards'), text);
    }
  }

  function addTempHiddenInput(form, name, value) {
    if (!(form instanceof HTMLFormElement)) return null;
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.setAttribute('data-cb-temp-submitter', '1');
    hidden.name = String(name || '');
    hidden.value = String(value || '');
    form.appendChild(hidden);
    return hidden;
  }

  function getLoaderTextFromSubmitter(submitter, form) {
    if (!(submitter instanceof HTMLElement)) return '';
    const submitterText = String(submitter.getAttribute('data-cb-loader-text') || '').trim();
    if (submitterText !== '') {
      return submitterText;
    }

    if (form instanceof HTMLFormElement) {
      return String(form.getAttribute('data-cb-loader-text') || '').trim();
    }

    return '';
  }

  function isDashboardRefreshForm(form) {
    return form instanceof HTMLFormElement
      && String(form.getAttribute('data-cb-refresh-dashboard-on-save') || '') === '1';
  }

  function getUserSettingValue(form, name) {
    if (!(form instanceof HTMLFormElement)) return '';
    const selector = 'select[name="' + String(name || '').replace(/"/g, '') + '"]';
    const field = form.querySelector(selector);
    if (!(field instanceof HTMLSelectElement)) return '';
    return String(field.value || '');
  }

  function syncUserSettingForm(form) {
    if (!(form instanceof HTMLFormElement)) return;
    if (String(form.getAttribute('data-cb-user-setting-form') || '') !== '1') return;

    const saveBtn = form.querySelector('[data-cb-user-setting-save="1"]');
    if (!(saveBtn instanceof HTMLButtonElement)) return;

    const initialCols = String(form.getAttribute('data-cb-user-setting-initial-pocet-sl') || '');
    const initialNano = String(form.getAttribute('data-cb-user-setting-initial-nano-kde') || '');
    const initialPismo = String(form.getAttribute('data-cb-user-setting-initial-pismo') || '');
    const initialDark = String(form.getAttribute('data-cb-user-setting-initial-dark') || '');

    const currentCols = getUserSettingValue(form, 'us_pocet_sl');
    const currentNano = getUserSettingValue(form, 'us_nano_kde');
    const currentPismo = getUserSettingValue(form, 'us_pismo');
    const currentDark = getUserSettingValue(form, 'us_dark');

    const isDirty = (
      currentCols !== initialCols
      || currentNano !== initialNano
      || currentPismo !== initialPismo
      || currentDark !== initialDark
    );

    saveBtn.disabled = !isDirty;
    saveBtn.setAttribute('aria-disabled', isDirty ? 'false' : 'true');
    form.setAttribute('data-cb-user-setting-dirty', isDirty ? '1' : '0');
  }

  function initUserSettingForms() {
    document.querySelectorAll('form[data-cb-user-setting-form="1"]').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      syncUserSettingForm(form);
    });
  }

  function submitAjax(form, submitter, options) {
    if (!(form instanceof HTMLFormElement)) return;
    const opts = (options && typeof options === 'object') ? options : {};
    const skipSubmitterName = !!opts.skipSubmitterName;
    const refreshDashboardOnSave = isDashboardRefreshForm(form);
    const useGlobalLoader = refreshDashboardOnSave;

    const reqUrl = String(form.action || w.location.href || 'index.php');
    const method = String(form.method || 'POST').toUpperCase();
    const tempNodes = [];
    const cardId = getCardIdFromForm(form);

    if (submitter instanceof HTMLElement && !skipSubmitterName) {
      const submitName = String(submitter.getAttribute('name') || submitter.getAttribute('data-cb-submit-name') || '').trim();
      const submitValue = String(submitter.getAttribute('value') || submitter.getAttribute('data-cb-submit-value') || '').trim();
      if (submitName !== '') {
        const hidden = addTempHiddenInput(form, submitName, submitValue);
        if (hidden instanceof HTMLInputElement) {
          tempNodes.push(hidden);
        }
      }
      submitter.setAttribute('disabled', 'disabled');
    }

    if (cardId > 0) {
      const hiddenCardId = addTempHiddenInput(form, 'cb_card_id', String(cardId));
      if (hiddenCardId instanceof HTMLInputElement) {
        tempNodes.push(hiddenCardId);
      }
    }

    const loaderText = getLoaderTextFromSubmitter(submitter, form);
    if (useGlobalLoader) {
      setLoading(true, loaderText, 'dashboard');
    }

    fetch(reqUrl, {
      method: method,
      body: new FormData(form),
      credentials: 'same-origin',
      redirect: 'follow',
      headers: {
        'X-Comeback-Max-Form': '1'
      }
    }).then((res) => {
      return res.text().then((text) => {
        const raw = String(text || '').trim();
        let data = null;
        if (raw !== '') {
          try {
            data = JSON.parse(raw);
          } catch (e) {
            data = null;
          }
        }

        if (!res.ok) {
          const errMsg = String((data && data.err) ? data.err : 'Odeslani formulare selhalo.').trim();
          throw new Error(errMsg + ' HTTP ' + res.status);
        }

        if (!data || typeof data !== 'object' || data.ok !== true || typeof data.cardHtml !== 'string') {
          throw new Error(String((data && data.err) ? data.err : 'Max karta vrátila neplatný JSON obsah.'));
        }

        if (refreshDashboardOnSave && w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
          document.dispatchEvent(new CustomEvent('cb:maxi-close-request'));
          return w.CB_AJAX.refreshDashboard({
            force: true,
            loaderMode: 'dashboard'
          });
        }

        if (cardId > 0 && swapCardExpandedFromHtml(cardId, data.cardHtml)) {
          return { ok: true, cardId: cardId };
        }

        if (cardId > 0 && swapCardFromHtml(cardId, data.cardHtml)) {
          return { ok: true, cardId: cardId };
        }

        throw new Error('Max karta vrátila neplatný HTML obsah.');
      });
    }).catch((err) => {
      const msg = (err && typeof err.message === 'string' && err.message.trim() !== '')
        ? err.message.trim()
        : 'Odeslani formulare selhalo.';
      if (w.alert) {
        w.alert(msg);
      }
    }).finally(() => {
      tempNodes.forEach((node) => {
        if (node instanceof HTMLInputElement) {
          node.remove();
        }
      });
      if (submitter instanceof HTMLElement) {
        submitter.removeAttribute('disabled');
      }
      if (useGlobalLoader) {
        setLoading(false);
      }
    });
  }

  function handleClick(event) {
    const target = event.target instanceof Element ? event.target.closest('button, input[type="submit"]') : null;
    if (!(target instanceof HTMLElement)) return;
    const form = resolveFormFromSubmitter(target);
    if (!(form instanceof HTMLFormElement)) return;
    if (!isTargetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();
    submitAjax(form, target);
  }

  function handleSubmit(event) {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!(form instanceof HTMLFormElement)) return;
    if (!isTargetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();
    submitAjax(form, event.submitter instanceof HTMLElement ? event.submitter : null);
  }

  function handleChange(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const settingForm = target.closest('form[data-cb-user-setting-form="1"]');
    if (settingForm instanceof HTMLFormElement && target instanceof HTMLSelectElement) {
      syncUserSettingForm(settingForm);
      return;
    }
    if (!(target instanceof HTMLInputElement)) return;
    if (String(target.getAttribute('data-cb-submit-on-change') || '') !== '1') return;

    const form = target.form;
    if (!(form instanceof HTMLFormElement)) return;
    if (!isTargetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();

    let hidden = form.querySelector('input[data-cb-temp-action="1"]');
    if (!(hidden instanceof HTMLInputElement)) {
      hidden = addTempHiddenInput(form, 'admin_karty_action', 'save');
      if (hidden instanceof HTMLInputElement) {
        hidden.setAttribute('data-cb-temp-action', '1');
      }
    } else {
      hidden.name = 'admin_karty_action';
      hidden.value = 'save';
    }

    submitAjax(form, target, { skipSubmitterName: true });
  }

  function wireOnce() {
    if (w.__CB_AJAX_KARTA_MAX_WIRED__) return;
    w.__CB_AJAX_KARTA_MAX_WIRED__ = true;

    document.addEventListener('click', handleClick, true);
    document.addEventListener('submit', handleSubmit, true);
    document.addEventListener('change', handleChange, true);
    document.addEventListener('cb:main-swapped', initUserSettingForms);
    document.addEventListener('cb:card-swapped', initUserSettingForms);
  }

  wireOnce();

  initUserSettingForms();
})(window);

// js/ajax_karta_max.js * Verze: V3 * Aktualizace: 17.04.2026
// Konec souboru
