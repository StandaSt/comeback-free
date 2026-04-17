// js/ajax_karta_max.js * Verze: V2 * Aktualizace: 15.04.2026
'use strict';

(function (w) {
  function isTargetForm(form) {
    if (!(form instanceof HTMLFormElement)) return false;
    if (form.getAttribute('data-cb-ajax-dashboard') === '1') return true;
    return !!form.querySelector('input[name="admin_karty_action"]');
  }

  function isTargetSubmitter(el) {
    if (!(el instanceof HTMLElement)) return false;
    const tag = el.tagName.toUpperCase();
    if (tag !== 'BUTTON' && !(tag === 'INPUT' && String(el.getAttribute('type') || '').toLowerCase() === 'submit')) {
      return false;
    }
    if (el.getAttribute('form')) return true;
    return !!el.closest('form[data-cb-ajax-dashboard="1"], form:has(input[name="admin_karty_action"])');
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

  function isAdminKartyForm(form) {
    if (!(form instanceof HTMLFormElement)) return false;
    return !!form.querySelector('input[name="admin_karty_action"]');
  }

  function markCardRefreshOnClose(form) {
    if (!(form instanceof HTMLFormElement)) return;
    const root = form.closest('.card_shell');
    if (!(root instanceof HTMLElement)) return;
    root.setAttribute('data-card-refresh-on-close', '1');
  }

  function submitAjax(form, submitter) {
    if (!(form instanceof HTMLFormElement)) return;
    if (!(w.CB_AJAX && typeof w.CB_AJAX.submitFormAndRefresh === 'function')) {
      form.submit();
      return;
    }

    if (submitter instanceof HTMLElement && submitter.hasAttribute('name')) {
      let hidden = form.querySelector('input[data-cb-temp-submitter="1"]');
      if (!(hidden instanceof HTMLInputElement)) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.setAttribute('data-cb-temp-submitter', '1');
        form.appendChild(hidden);
      }
      hidden.name = String(submitter.getAttribute('name') || '');
      hidden.value = ('value' in submitter) ? String(submitter.value || '') : String(submitter.getAttribute('value') || '');
    }

    if (submitter instanceof HTMLElement) {
      submitter.setAttribute('disabled', 'disabled');
    }

    if (isAdminKartyForm(form)) {
      const reqUrl = String(form.action || w.location.href || 'index.php');
      const method = String(form.method || 'POST').toUpperCase();

      fetch(reqUrl, {
        method: method,
        body: new FormData(form),
        credentials: 'same-origin',
        redirect: 'follow',
        headers: {
          'X-Comeback-Admin-Karty': '1'
        }
      }).then((res) => {
        return res.text().then((text) => {
          let data = null;
          const raw = String(text || '').trim();
          if (raw !== '') {
            try {
              data = JSON.parse(raw);
            } catch (e) {
              data = null;
            }
          }

          if (!res.ok || (data && data.ok === false)) {
            const errMsg = data && typeof data.err === 'string' && data.err.trim() !== ''
              ? data.err.trim()
              : ('HTTP ' + res.status);
            throw new Error(errMsg);
          }

          markCardRefreshOnClose(form);
        });
      }).catch(function (err) {
        const msg = (err && typeof err.message === 'string' && err.message.trim() !== '')
          ? err.message.trim()
          : 'Odeslani formulare selhalo.';
        if (w.alert) {
          w.alert(msg);
        }
      }).finally(function () {
        const hidden = form.querySelector('input[data-cb-temp-submitter="1"]');
        if (hidden instanceof HTMLInputElement) {
          hidden.remove();
        }
        if (submitter instanceof HTMLElement) {
          submitter.removeAttribute('disabled');
        }
      });
      return;
    }

    w.CB_AJAX.submitFormAndRefresh(form, {
      loaderMode: 'dashboard',
      refreshMode: 'dashboard',
      keepLoading: false
    }).catch(function (err) {
      const msg = (err && typeof err.message === 'string' && err.message.trim() !== '')
        ? err.message.trim()
        : 'Odeslani formulare selhalo.';
      if (w.alert) {
        w.alert(msg);
      }
    }).finally(function () {
      const hidden = form.querySelector('input[data-cb-temp-submitter="1"]');
      if (hidden instanceof HTMLInputElement) {
        hidden.remove();
      }
      if (submitter instanceof HTMLElement) {
        submitter.removeAttribute('disabled');
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
    const target = event.target instanceof HTMLElement ? event.target : null;
    if (!(target instanceof HTMLInputElement)) return;
    if (String(target.type || '').toLowerCase() !== 'checkbox') return;
    if (String(target.getAttribute('data-cb-refresh-op') || '') !== '1') return;

    const form = target.form;
    if (!(form instanceof HTMLFormElement)) return;
    if (!isTargetForm(form)) return;

    event.preventDefault();
    event.stopPropagation();
    submitAjax(form, null);
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

// js/ajax_karta_max.js * Verze: V2 * Aktualizace: 15.04.2026
// Konec souboru
