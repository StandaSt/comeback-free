// js/nezadane_reporty_export.js * Odeslani PDF s nezadanymi dennimi reporty
'use strict';

(function () {
  function getModal() {
    const modal = document.querySelector('[data-nezadane-export-modal]');
    return modal instanceof HTMLElement ? modal : null;
  }

  function setMessage(modal, message, isError) {
    const status = modal.querySelector('[data-nezadane-export-message]');
    if (!(status instanceof HTMLElement)) return;

    status.textContent = String(message || '');
    status.classList.toggle('is-hidden', message === '');
    status.classList.toggle('is-error', Boolean(isError));
    status.classList.toggle('is-success', message !== '' && !isError);
  }

  function setBusy(modal, busy) {
    modal.setAttribute('aria-busy', busy ? 'true' : 'false');
    const send = modal.querySelector('[data-nezadane-export-send]');
    if (send instanceof HTMLButtonElement) send.disabled = busy;
  }

  function closeModal(modal) {
    if (modal.getAttribute('aria-busy') === 'true') return;
    modal.classList.add('is-hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('data-scope');
    setMessage(modal, '', false);
  }

  function resetSent(modal) {
    const sent = modal.querySelector('[data-nezadane-export-sent]');
    if (sent instanceof HTMLElement) {
      sent.replaceChildren();
      sent.classList.add('is-hidden');
    }

    const send = modal.querySelector('[data-nezadane-export-send]');
    if (send instanceof HTMLButtonElement) send.textContent = 'Odeslat PDF';
  }

  function appendSent(modal, email) {
    const sent = modal.querySelector('[data-nezadane-export-sent]');
    if (!(sent instanceof HTMLElement)) return;

    const row = document.createElement('div');
    row.className = 'provoz_nezadane_sent_row';
    row.textContent = 'PDF odesláno: ' + String(email || '');
    sent.appendChild(row);
    sent.classList.remove('is-hidden');

    const send = modal.querySelector('[data-nezadane-export-send]');
    if (send instanceof HTMLButtonElement) send.textContent = 'Odeslat dalšímu uživateli';
  }

  function openModal(modal, scope) {
    modal.setAttribute('data-scope', scope);
    modal.classList.remove('is-hidden');
    modal.setAttribute('aria-hidden', 'false');
    setBusy(modal, false);
    setMessage(modal, '', false);
    resetSent(modal);

    const period = modal.querySelector('[data-nezadane-export-period]');
    if (period instanceof HTMLElement) {
      period.textContent = scope === 'previous'
        ? 'Rozsah: celý minulý měsíc'
        : 'Rozsah: od 1. dne aktuálního měsíce do dneška';
    }

    const recipient = modal.querySelector('[data-nezadane-export-recipient]');
    if (recipient instanceof HTMLSelectElement) {
      recipient.value = '';
      recipient.focus();
    }
  }

  async function sendExport(modal) {
    const recipient = modal.querySelector('[data-nezadane-export-recipient]');
    if (!(recipient instanceof HTMLSelectElement) || recipient.value === '') {
      setMessage(modal, 'Vyberte e-mail příjemce.', true);
      return;
    }

    setBusy(modal, true);
    setMessage(modal, 'Připravuji PDF a odesílám e-mail…', false);

    try {
      const response = await fetch(String(modal.getAttribute('data-endpoint') || ''), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          csrf: String(modal.getAttribute('data-csrf') || ''),
          scope: String(modal.getAttribute('data-scope') || ''),
          id_recipient: Number(recipient.value)
        })
      });
      const result = await response.json();
      if (!response.ok || !result || result.ok !== true) {
        throw new Error(result && result.message ? String(result.message) : 'PDF se nepodařilo odeslat.');
      }
      appendSent(modal, String(result.recipient_email || ''));
      recipient.value = '';
      recipient.focus();
      setMessage(modal, '', false);
    } catch (error) {
      setMessage(modal, error instanceof Error ? error.message : 'PDF se nepodařilo odeslat.', true);
    } finally {
      setBusy(modal, false);
    }
  }

  document.addEventListener('click', function (event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    const opener = target.closest('[data-nezadane-export-open]');
    if (opener instanceof HTMLButtonElement) {
      const modal = getModal();
      if (modal) openModal(modal, String(opener.getAttribute('data-nezadane-export-open') || 'current'));
      return;
    }

    const close = target.closest('[data-nezadane-export-close]');
    if (close instanceof HTMLElement) {
      const modal = close.closest('[data-nezadane-export-modal]');
      if (modal instanceof HTMLElement) closeModal(modal);
      return;
    }

    const send = target.closest('[data-nezadane-export-send]');
    if (send instanceof HTMLElement) {
      const modal = send.closest('[data-nezadane-export-modal]');
      if (modal instanceof HTMLElement) sendExport(modal);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    const modal = getModal();
    if (modal && !modal.classList.contains('is-hidden')) closeModal(modal);
  });
})();

// js/nezadane_reporty_export.js * Konec souboru
