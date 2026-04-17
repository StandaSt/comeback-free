// js/ajax_karta_max.js * Verze: V3 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  function isTargetForm(form) {
    return form instanceof HTMLFormElement && form.getAttribute('data-cb-ajax-dashboard') === '1';
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

  function setLoading(on) {
    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
      w.CB_AJAX.setDashboardLoading(!!on, 'cards');
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

  function submitAjax(form, submitter, options) {
    if (!(form instanceof HTMLFormElement)) return;
    const opts = (options && typeof options === 'object') ? options : {};
    const skipSubmitterName = !!opts.skipSubmitterName;

    const reqUrl = String(form.action || w.location.href || 'index.php');
    const method = String(form.method || 'POST').toUpperCase();
    const tempNodes = [];

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

    setLoading(true);

    fetch(reqUrl, {
      method: method,
      body: new FormData(form),
      credentials: 'same-origin',
      redirect: 'follow',
      headers: {
        'X-Comeback-Max-Form': '1'
      }
    }).then((res) => {
      const contentType = String(res.headers.get('content-type') || '').toLowerCase();
      if (!res.ok) {
        return res.text().then((text) => {
          let errMsg = 'Odeslani formulare selhalo.';
          const raw = String(text || '').trim();
          if (raw !== '' && contentType.includes('application/json')) {
            try {
              const data = JSON.parse(raw);
              if (data && typeof data.err === 'string' && data.err.trim() !== '') {
                errMsg = data.err.trim();
              }
            } catch (e) {}
          }
          throw new Error(errMsg + ' HTTP ' + res.status);
        });
      }

      if (contentType.includes('application/json')) {
        return res.json().then((data) => {
          if (data && data.ok === false) {
            throw new Error(String(data.err || 'Odeslani formulare selhalo.'));
          }
        }).then(() => {
          const cardId = getCardIdFromForm(form);
          if (cardId > 0 && w.CB_AJAX && typeof w.CB_AJAX.refreshCard === 'function') {
            return w.CB_AJAX.refreshCard(cardId, {
              force: true,
              keepLoading: true,
              loaderMode: 'cards',
              loadMax: true
            });
          }
          return null;
        });
      }

      return res.text().then(() => {
        const cardId = getCardIdFromForm(form);
        if (cardId > 0 && w.CB_AJAX && typeof w.CB_AJAX.refreshCard === 'function') {
          return w.CB_AJAX.refreshCard(cardId, {
            force: true,
            keepLoading: true,
            loaderMode: 'cards',
            loadMax: true
          });
        }
        return null;
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
      setLoading(false);
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
    const target = event.target instanceof HTMLElement ? event.target : null;
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
  }

  wireOnce();
})(window);

// js/ajax_karta_max.js * Verze: V3 * Aktualizace: 17.04.2026
// Konec souboru
