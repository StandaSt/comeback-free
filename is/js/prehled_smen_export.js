// js/prehled_smen_export.js * Verze: V4 * Aktualizace: 04.06.2026
'use strict';

(function () {
  function getModal(id) {
    const modal = document.getElementById(String(id || ''));
    return modal instanceof HTMLElement ? modal : null;
  }

  function openModal(modal) {
    resetExportState(modal);
    modal.classList.remove('is-hidden');
    modal.setAttribute('aria-hidden', 'false');

    const pdf = modal.querySelector('input[name="ps_export_format"][value="pdf"]');
    const summary = modal.querySelector('input[name="ps_export_scope"][value="summary"]');
    if (pdf instanceof HTMLInputElement) pdf.checked = true;
    if (summary instanceof HTMLInputElement) summary.checked = true;

    const submit = modal.querySelector('[data-ps-export-submit]');
    if (submit instanceof HTMLElement) submit.focus();
  }

  function closeModal(modal) {
    resetExportState(modal);
    modal.classList.add('is-hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  function resetExportState(modal) {
    modal.removeAttribute('aria-busy');

    const status = modal.querySelector('[data-ps-export-status]');
    if (status instanceof HTMLElement) {
      status.classList.add('is-hidden');
    }

    const submit = modal.querySelector('[data-ps-export-submit]');
    if (submit instanceof HTMLButtonElement) {
      submit.disabled = false;
    }
  }

  function showPreparing(modal) {
    modal.setAttribute('aria-busy', 'true');

    const status = modal.querySelector('[data-ps-export-status]');
    if (status instanceof HTMLElement) {
      status.classList.remove('is-hidden');
    }

    const submit = modal.querySelector('[data-ps-export-submit]');
    if (submit instanceof HTMLButtonElement) {
      submit.disabled = true;
    }
  }

  function selectedValue(modal, name) {
    const input = modal.querySelector('input[name="' + name + '"]:checked');
    return input instanceof HTMLInputElement ? String(input.value || '') : '';
  }

  function exportUrl(modal, format) {
    if (format === 'xlsx') return String(modal.getAttribute('data-xlsx-url') || '');
    if (format === 'txt') return String(modal.getAttribute('data-txt-url') || '');
    return String(modal.getAttribute('data-pdf-url') || '');
  }

  function addScope(url, scope) {
    if (url === '') return '';

    const separator = url.indexOf('?') === -1 ? '?' : '&';
    return url + separator + 'ps_scope=' + encodeURIComponent(scope);
  }

  function filenameFromDisposition(disposition) {
    const header = String(disposition || '');
    const utfMatch = header.match(/filename\*=UTF-8''([^;]+)/i);
    if (utfMatch && utfMatch[1]) {
      return decodeURIComponent(utfMatch[1].trim().replace(/^"|"$/g, ''));
    }

    const plainMatch = header.match(/filename="?([^";]+)"?/i);
    return plainMatch && plainMatch[1] ? plainMatch[1].trim() : 'export';
  }

  function triggerDownload(blob, filename) {
    const objectUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(objectUrl);
  }

  async function downloadExport(modal, url) {
    showPreparing(modal);

    try {
      const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin'
      });

      if (!response.ok) {
        const message = await response.text();
        throw new Error(message || 'Export se nepodařilo připravit.');
      }

      const blob = await response.blob();
      const filename = filenameFromDisposition(response.headers.get('Content-Disposition'));
      triggerDownload(blob, filename);
    } catch (error) {
      alert(error instanceof Error ? error.message : 'Export se nepodařilo připravit.');
    } finally {
      resetExportState(modal);
    }
  }

  document.addEventListener('click', function (event) {
    const opener = event.target instanceof Element
      ? event.target.closest('[data-ps-export-open]')
      : null;
    if (opener instanceof HTMLElement) {
      const modal = getModal(opener.getAttribute('data-ps-export-open'));
      if (modal) {
        event.preventDefault();
        openModal(modal);
      }
      return;
    }

    const close = event.target instanceof Element
      ? event.target.closest('[data-ps-export-close]')
      : null;
    if (close instanceof HTMLElement) {
      const modal = close.closest('[data-ps-export-modal]');
      if (modal instanceof HTMLElement) {
        event.preventDefault();
        closeModal(modal);
      }
      return;
    }

    const submit = event.target instanceof Element
      ? event.target.closest('[data-ps-export-submit]')
      : null;
    if (submit instanceof HTMLElement) {
      const modal = submit.closest('[data-ps-export-modal]');
      if (!(modal instanceof HTMLElement)) return;

      event.preventDefault();
      const scope = selectedValue(modal, 'ps_export_scope') === 'detail' ? 'detail' : 'summary';
      const url = addScope(exportUrl(modal, selectedValue(modal, 'ps_export_format')), scope);
      if (url !== '') {
        downloadExport(modal, url);
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('[data-ps-export-modal]:not(.is-hidden)').forEach(function (modal) {
      if (modal instanceof HTMLElement) closeModal(modal);
    });
  });
})();

// js/prehled_smen_export.js * Verze: V4 * Aktualizace: 04.06.2026
// Konec souboru
