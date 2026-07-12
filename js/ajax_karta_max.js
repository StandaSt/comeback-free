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

  function getTableFilterPrefix(form) {
    if (!(form instanceof HTMLFormElement)) return '';
    const el = form.querySelector('input.filter-input[name*="_f["]');
    const name = el instanceof HTMLInputElement ? String(el.name || '') : '';
    const m = name.match(/^([a-z0-9_]+)_f\[/i);
    return m ? String(m[1] || '').toLowerCase() : '';
  }

  function isTableFilterForm(form) {
    return form instanceof HTMLFormElement
      && String(form.method || 'get').toLowerCase() === 'get'
      && getTableFilterPrefix(form) !== '';
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
    return nextCard;
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
    return currentCard;
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
    if (submitter instanceof HTMLElement) {
      if (submitter.id === 'cb_start_import_btn') {
        return '';
      }
      const submitterText = String(submitter.getAttribute('data-cb-loader-text') || '').trim();
      if (submitterText !== '') {
        return submitterText;
      }
    }

    if (form instanceof HTMLFormElement) {
      return String(form.getAttribute('data-cb-loader-text') || '').trim();
    }

    return '';
  }

  function shouldBypassAjaxSubmit(form) {
    return false;
  }

  function getRestiaImportMode(form) {
    if (!(form instanceof HTMLFormElement)) return '';
    const historyField = form.querySelector('input[name="run_restia_obj"]');
    if (historyField instanceof HTMLInputElement && String(historyField.value || '') === '1') {
      return 'history';
    }

    return '';
  }

  function finishRestiaReload(card) {
    if (!(card instanceof HTMLElement)) {
      setLoading(false, '', 'dashboard');
      return;
    }

    initRestiaAutoResume(card);
    if (!card.querySelector('#cb_restia_continue_btn[data-cb-restia-auto-resume="1"]')) {
      setLoading(false, '', 'dashboard');
    }
  }

  function reloadRestiaImportMax(cardId, attempt, mode) {
    const id = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(id) || id <= 0) return;
    const tries = Number.isFinite(attempt) ? Number(attempt) : 0;
    const reloadMode = 'history';

    const url = 'index.php?cb_card_id=' + encodeURIComponent(String(id)) + '&cb_restia_import_max=1';
    fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Restia-Import-Max': '1'
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

        if (!res.ok || !data || typeof data !== 'object' || data.ok !== true || typeof data.cardHtml !== 'string') {
          throw new Error('Max karta se nepodarila nacist.');
        }

        const expandedCard = swapCardExpandedFromHtml(id, data.cardHtml);
        if (expandedCard instanceof HTMLElement) {
          finishRestiaReload(expandedCard);
          return;
        }

        const swappedCard = swapCardFromHtml(id, data.cardHtml);
        finishRestiaReload(swappedCard instanceof HTMLElement ? swappedCard : null);
      });
    }).catch(() => {
      if (tries >= 3) {
        setLoading(false, '', 'dashboard');
        return;
      }
      w.setTimeout(() => reloadRestiaImportMax(id, tries + 1, reloadMode), 5000);
    });
  }

  function setMaxFormSubmitting(form, submitter, on, text) {
    if (!(form instanceof HTMLFormElement)) return;

    const buttons = Array.from(form.querySelectorAll('button, input[type="submit"]')).filter((button) => {
      return button instanceof HTMLButtonElement || button instanceof HTMLInputElement;
    });

    buttons.forEach((button) => {
      if (on) {
        if (!button.hasAttribute('data-cb-submit-original-disabled')) {
          button.setAttribute('data-cb-submit-original-disabled', button.disabled ? '1' : '0');
        }
        button.disabled = true;
        button.style.pointerEvents = 'none';
      } else {
        const wasDisabled = button.getAttribute('data-cb-submit-original-disabled') === '1';
        button.disabled = wasDisabled;
        button.style.pointerEvents = '';
        button.removeAttribute('data-cb-submit-original-disabled');
      }
    });

    if (!(submitter instanceof HTMLElement)) return;

    const target = (submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) ? submitter : null;
    if (!target) return;

    if (on) {
      if (!target.hasAttribute('data-cb-submit-original-text')) {
        target.setAttribute('data-cb-submit-original-text', target instanceof HTMLInputElement ? target.value : target.textContent || '');
      }
      if (!target.hasAttribute('data-cb-submit-original-style')) {
        target.setAttribute('data-cb-submit-original-style', target.getAttribute('style') || '');
      }

      if (target.id === 'cb_start_import_btn') {
        target.style.cursor = 'wait';
        target.style.opacity = '1';
        target.setAttribute('aria-disabled', 'true');
        return;
      }

      const runningText = String(text || '').trim() || 'Probíhá akce ...';
      if (target instanceof HTMLInputElement) {
        target.value = runningText;
      } else {
        target.textContent = runningText;
      }
      target.style.background = 'var(--clr_cervena)';
      target.style.borderColor = 'var(--clr_cervena_3)';
      target.style.color = 'var(--clr_bila)';
      target.style.cursor = 'wait';
      target.style.opacity = '1';
      target.setAttribute('aria-disabled', 'true');
      return;
    }

    const originalText = target.getAttribute('data-cb-submit-original-text');
    if (originalText !== null) {
      if (target instanceof HTMLInputElement) {
        target.value = originalText;
      } else {
        target.textContent = originalText;
      }
      target.removeAttribute('data-cb-submit-original-text');
    }

    const originalStyle = target.getAttribute('data-cb-submit-original-style');
    if (originalStyle !== null) {
      if (originalStyle === '') {
        target.removeAttribute('style');
      } else {
        target.setAttribute('style', originalStyle);
      }
      target.removeAttribute('data-cb-submit-original-style');
    }
    target.removeAttribute('aria-disabled');
  }

  function getUserSettingValue(form, name) {
    if (!(form instanceof HTMLFormElement)) return '';
    const selector = 'select[name="' + String(name || '').replace(/"/g, '') + '"]';
    const field = form.querySelector(selector);
    if (!(field instanceof HTMLSelectElement)) return '';
    return String(field.value || '');
  }

  function clearUserSettingSavedMessages(form) {
    if (!(form instanceof HTMLFormElement)) return;
    form.querySelectorAll('[data-cb-user-setting-saved-msg="1"]').forEach((node) => {
      if (node instanceof HTMLElement) {
        node.remove();
      }
    });
  }

  function getUserSettingGroupFromSubmitter(submitter) {
    if (!(submitter instanceof HTMLElement)) return '';
    return String(submitter.getAttribute('data-cb-user-setting-save') || '').trim();
  }

  function showUserSettingSavedMessage(card, group) {
    if (!(card instanceof HTMLElement)) return;
    const shell = card.querySelector('.card_shell[data-card-id]');
    const cardId = shell instanceof HTMLElement ? String(shell.getAttribute('data-card-id') || '').replace(/"/g, '') : '';
    const overlayForm = cardId !== ''
      ? document.querySelector('[data-cb-maxi-clone="1"] .card_shell[data-card-id="' + cardId + '"] form[data-cb-user-setting-form="1"]')
      : null;
    const form = overlayForm instanceof HTMLFormElement
      ? overlayForm
      : card.querySelector('form[data-cb-user-setting-form="1"]');
    if (!(form instanceof HTMLFormElement)) return;

    const activeGroup = String(group || '').trim();
    if (activeGroup !== '') {
      const tab = form.querySelector('#cb_user_tab_' + activeGroup);
      if (tab instanceof HTMLInputElement) {
        tab.checked = true;
      }
    }

    clearUserSettingSavedMessages(form);

    const panel = activeGroup !== ''
      ? form.querySelector('.cb_user_panel_' + activeGroup)
      : form.querySelector('.cb_user_panel');
    if (!(panel instanceof HTMLElement)) return;

    const msg = document.createElement('p');
    msg.className = 'card_text txt_cervena text_tucny odstup_vnejsi_0';
    msg.setAttribute('data-cb-user-setting-saved-msg', '1');
    msg.textContent = 'Změny byly uloženy';

    const title = panel.querySelector('.card_section_title');
    if (title instanceof HTMLElement && title.nextSibling) {
      panel.insertBefore(msg, title.nextSibling);
    } else {
      panel.insertBefore(msg, panel.firstChild);
    }
  }

  function syncUserSettingForm(form) {
    if (!(form instanceof HTMLFormElement)) return;
    if (String(form.getAttribute('data-cb-user-setting-form') || '') !== '1') return;

    const saveButtons = form.querySelectorAll('[data-cb-user-setting-save]');
    if (!saveButtons.length) return;

    const initialProdleva = String(form.getAttribute('data-cb-user-setting-initial-prodleva') || '');
    const initialPismo = String(form.getAttribute('data-cb-user-setting-initial-pismo') || '');
    const initialDark = String(form.getAttribute('data-cb-user-setting-initial-dark') || '');
    const initialLogoutLimit = String(form.getAttribute('data-cb-user-setting-initial-logout-limit') || '');

    const currentProdleva = getUserSettingValue(form, 'us_prodleva');
    const currentPismo = getUserSettingValue(form, 'us_pismo');
    const currentDark = getUserSettingValue(form, 'us_dark');
    const currentLogoutLimit = getUserSettingValue(form, 'us_logout_limit');

    const dirtyByGroup = {
      dashboard: currentProdleva !== initialProdleva,
      vzhled: (
        currentPismo !== initialPismo
        || currentDark !== initialDark
      ),
      manager: currentLogoutLimit !== initialLogoutLimit
    };

    let isDirty = false;
    saveButtons.forEach((button) => {
      if (!(button instanceof HTMLButtonElement)) return;
      const group = String(button.getAttribute('data-cb-user-setting-save') || '');
      const groupDirty = !!dirtyByGroup[group];
      isDirty = isDirty || groupDirty;
      button.disabled = !groupDirty;
      button.setAttribute('aria-disabled', groupDirty ? 'false' : 'true');
    });
    form.setAttribute('data-cb-user-setting-dirty', isDirty ? '1' : '0');
  }

  function initUserSettingForms() {
    document.querySelectorAll('form[data-cb-user-setting-form="1"]').forEach((form) => {
      if (!(form instanceof HTMLFormElement)) return;
      syncUserSettingForm(form);
    });
  }

  function initRestiaAutoResume(scope) {
    const root = scope instanceof HTMLElement || scope instanceof Document ? scope : document;
    const submitter = root.querySelector('#cb_restia_continue_btn[data-cb-restia-auto-resume="1"]');
    if (!(submitter instanceof HTMLElement)) return;
    if (submitter.getAttribute('data-cb-restia-auto-resume-armed') === '1') return;
    submitter.setAttribute('data-cb-restia-auto-resume-armed', '1');

    const delayMs = parseInt(String(submitter.getAttribute('data-cb-restia-auto-resume-delay') || '500'), 10);
    const waitMs = Number.isFinite(delayMs) && delayMs >= 0 ? delayMs : 500;

    w.setTimeout(() => {
      if (!document.body.contains(submitter)) return;
      const form = submitter.closest('form');
      if (!(form instanceof HTMLFormElement)) return;

      if (typeof w.cbResetIdleLogout === 'function') {
        w.cbResetIdleLogout();
      }

      submitAjax(form, submitter instanceof HTMLElement ? submitter : null);
    }, waitMs);
  }

  function initCardAutoContinue(scope) {
      const root = scope instanceof HTMLElement || scope instanceof Document ? scope : document;
      const marker = root.querySelector('[data-cb-auto-continue="1"]');
    if (!(marker instanceof HTMLElement)) return;
    if (marker.getAttribute('data-cb-auto-continue-armed') === '1') return;
    marker.setAttribute('data-cb-auto-continue-armed', '1');

    const delayMs = parseInt(String(marker.getAttribute('data-cb-auto-continue-delay') || '2000'), 10);
    const waitMs = Number.isFinite(delayMs) && delayMs >= 0 ? delayMs : 2000;
    const formSelector = String(marker.getAttribute('data-cb-auto-continue-form') || '').trim();
    const buttonSelector = String(marker.getAttribute('data-cb-auto-continue-button') || '').trim();
    const loaderText = String(marker.getAttribute('data-cb-auto-continue-loader-text') || '').trim();

    const card = marker.closest('[data-cb-dash-card="1"]');
    if (!(card instanceof HTMLElement)) return;

    let form = null;
    if (formSelector !== '') {
      try {
        form = card.querySelector(formSelector);
      } catch (e) {
        form = null;
      }
    }
    if (!(form instanceof HTMLFormElement)) {
      form = card.querySelector('form[data-cb-max-form="1"]');
    }
    if (!(form instanceof HTMLFormElement)) return;

    let submitter = null;
    if (buttonSelector !== '') {
      try {
        submitter = card.querySelector(buttonSelector);
      } catch (e) {
        submitter = null;
      }
    }
    if (!(submitter instanceof HTMLElement)) {
      submitter = form.querySelector('button[type="submit"], input[type="submit"]');
    }

    if (loaderText !== '') {
      form.setAttribute('data-cb-loader-text', loaderText);
      if (submitter instanceof HTMLElement) {
        submitter.setAttribute('data-cb-loader-text', loaderText);
      }
      setLoading(true, loaderText, 'dashboard');
    }

    w.setTimeout(() => {
      if (!document.body.contains(marker) || !document.body.contains(form)) {
        setLoading(false, '', 'dashboard');
        return;
      }
      submitAjax(form, submitter instanceof HTMLElement ? submitter : null);
    }, waitMs);
  }

  function submitAjax(form, submitter, options) {
    if (!(form instanceof HTMLFormElement)) return;
    const opts = (options && typeof options === 'object') ? options : {};
    const skipSubmitterName = !!opts.skipSubmitterName;
    const restiaImportMode = getRestiaImportMode(form);
    const userSettingSaveGroup = getUserSettingGroupFromSubmitter(submitter);

    const method = String(form.method || 'POST').toUpperCase();
    const reqUrl = String(form.action || w.location.href || 'index.php');
    const tempNodes = [];
    const cardId = getCardIdFromForm(form);
    let fetchUrl = reqUrl;
    const requestInit = {
      method: method,
      credentials: 'same-origin',
      redirect: 'follow',
      headers: {
        'X-Comeback-Max-Form': '1'
      }
    };

    const loaderText = getLoaderTextFromSubmitter(submitter, form);

    if (submitter instanceof HTMLElement && !skipSubmitterName) {
      const submitName = String(submitter.getAttribute('name') || submitter.getAttribute('data-cb-submit-name') || '').trim();
      const submitValue = String(submitter.getAttribute('value') || submitter.getAttribute('data-cb-submit-value') || '').trim();
      if (submitName !== '') {
        const hidden = addTempHiddenInput(form, submitName, submitValue);
        if (hidden instanceof HTMLInputElement) {
          tempNodes.push(hidden);
        }
      }
      if (submitter.id === 'cb_start_import_btn') {
        submitter.textContent = 'Importuji';
        submitter.style.background = 'var(--clr_zelena_2)';
        submitter.style.borderColor = 'var(--clr_zelena_1)';
        submitter.style.color = '#fff';
        submitter.style.opacity = '1';
        submitter.style.cursor = 'wait';
        submitter.style.pointerEvents = 'none';
        submitter.setAttribute('aria-disabled', 'true');
      }
    }

    setMaxFormSubmitting(form, submitter, true, loaderText);

    if (cardId > 0) {
      const hiddenCardId = addTempHiddenInput(form, 'cb_card_id', String(cardId));
      if (hiddenCardId instanceof HTMLInputElement) {
        tempNodes.push(hiddenCardId);
      }
    }

    const formData = new FormData(form);

    const useGlobalLoader = (loaderText !== '' && restiaImportMode === '');
    if (useGlobalLoader) {
      setLoading(true, loaderText, 'dashboard');
    }

    if (method === 'GET') {
      requestInit.headers['X-Comeback-Card'] = '1';
      const url = new URL(reqUrl, w.location.href);
      url.search = new URLSearchParams(formData).toString();
      fetchUrl = url.toString();
    } else {
      requestInit.body = formData;
    }

    let keepRestiaLoader = false;
    let keepGlobalLoader = false;

    fetch(fetchUrl, requestInit).then((res) => {
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
          const err = new Error(errMsg + ' HTTP ' + res.status);
          err.status = res.status;
          throw err;
        }

        if (!data || typeof data !== 'object' || data.ok !== true || typeof data.cardHtml !== 'string') {
          throw new Error(String((data && data.err) ? data.err : 'Max karta vrátila neplatný JSON obsah.'));
        }

        if (cardId > 0) {
          const expandedCard = swapCardExpandedFromHtml(cardId, data.cardHtml);
          if (expandedCard instanceof HTMLElement) {
            showUserSettingSavedMessage(expandedCard, userSettingSaveGroup);
            initCardAutoContinue(expandedCard);
            keepGlobalLoader = !!expandedCard.querySelector('[data-cb-auto-continue="1"]');
            return { ok: true, cardId: cardId };
          }
        }

        if (cardId > 0) {
          const swappedCard = swapCardFromHtml(cardId, data.cardHtml);
          if (swappedCard instanceof HTMLElement) {
            showUserSettingSavedMessage(swappedCard, userSettingSaveGroup);
            initCardAutoContinue(swappedCard);
            keepGlobalLoader = !!swappedCard.querySelector('[data-cb-auto-continue="1"]');
            return { ok: true, cardId: cardId };
          }
        }

        throw new Error('Max karta vrátila neplatný HTML obsah.');
      });
    }).catch((err) => {
      const status = err && typeof err.status === 'number' ? err.status : 0;
      if (restiaImportMode !== '' && status === 504 && cardId > 0) {
        keepRestiaLoader = true;
        w.setTimeout(() => reloadRestiaImportMax(cardId, 0, restiaImportMode), 5000);
        return;
      }

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
      setMaxFormSubmitting(form, submitter, false, '');
      if (keepRestiaLoader || keepGlobalLoader) {
        return;
      }
      if (useGlobalLoader || restiaImportMode !== '') {
        setLoading(false, '', 'dashboard');
      }
    });
  }

  function handleClick(event) {
    const target = event.target instanceof Element ? event.target.closest('button, input[type="submit"]') : null;
    if (!(target instanceof HTMLElement)) return;
    if (target instanceof HTMLButtonElement && String(target.type || '').toLowerCase() !== 'submit') return;
    const form = resolveFormFromSubmitter(target);
    if (!(form instanceof HTMLFormElement)) return;
    if (isTableFilterForm(form)) return;
    if (!isTargetForm(form)) return;
    if (shouldBypassAjaxSubmit(form)) return;

    event.preventDefault();
    event.stopPropagation();
    submitAjax(form, target);
  }

  function handleSubmit(event) {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!(form instanceof HTMLFormElement)) return;
    if (isTableFilterForm(form)) return;
    if (!isTargetForm(form)) return;
    if (shouldBypassAjaxSubmit(form)) return;

    event.preventDefault();
    event.stopPropagation();
    submitAjax(form, event.submitter instanceof HTMLElement ? event.submitter : null);
  }

  function handleChange(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const settingForm = target.closest('form[data-cb-user-setting-form="1"]');
    if (settingForm instanceof HTMLFormElement && target instanceof HTMLInputElement && String(target.name || '') === 'cb_user_tab') {
      clearUserSettingSavedMessages(settingForm);
      return;
    }
    if (settingForm instanceof HTMLFormElement && target instanceof HTMLSelectElement) {
      syncUserSettingForm(settingForm);
      return;
    }
    if (!(target instanceof HTMLInputElement) && !(target instanceof HTMLSelectElement)) return;
    if (String(target.getAttribute('data-cb-submit-on-change') || '') !== '1') return;

    const form = target.form;
    if (!(form instanceof HTMLFormElement)) return;
    if (!isTargetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();

    const submitName = String(target.getAttribute('data-cb-submit-name') || '').trim();
    if (submitName !== '') {
      const submitValue = String(target.getAttribute('data-cb-submit-value') || '').trim();
      let hidden = form.querySelector('input[data-cb-temp-action="1"]');
      if (!(hidden instanceof HTMLInputElement)) {
        hidden = addTempHiddenInput(form, submitName, submitValue);
        if (hidden instanceof HTMLInputElement) {
          hidden.setAttribute('data-cb-temp-action', '1');
        }
      } else {
        hidden.name = submitName;
        hidden.value = submitValue;
      }
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
    document.addEventListener('cb:card-max-loaded', initUserSettingForms);
    document.addEventListener('cb:main-swapped', () => initRestiaAutoResume(document));
    document.addEventListener('cb:card-swapped', (event) => {
      const card = event && event.detail ? event.detail.card : null;
      initRestiaAutoResume(card instanceof HTMLElement ? card : document);
      initCardAutoContinue(card instanceof HTMLElement ? card : document);
    });
    document.addEventListener('cb:card-max-loaded', (event) => {
      const card = event && event.detail ? event.detail.card : null;
      initRestiaAutoResume(card instanceof HTMLElement ? card : document);
      initCardAutoContinue(card instanceof HTMLElement ? card : document);
    });
  }

  wireOnce();

  initUserSettingForms();
  initRestiaAutoResume(document);
  initCardAutoContinue(document);
})(window);

// js/ajax_karta_max.js * Verze: V3 * Aktualizace: 17.04.2026
// Konec souboru
