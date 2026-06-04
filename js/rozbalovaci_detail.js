// js/rozbalovaci_detail.js * Verze: V2 * Aktualizace: 04.06.2026
'use strict';

(function () {
  function adjustViewport(row) {
    if (!(row instanceof HTMLElement) || row.hidden) return;

    window.requestAnimationFrame(function () {
      const rect = row.getBoundingClientRect();
      const margin = 16;
      const viewHeight = window.innerHeight || document.documentElement.clientHeight || 0;
      if (viewHeight <= 0) return;

      if (rect.height + (margin * 2) > viewHeight) {
        window.scrollBy({
          top: rect.top - margin,
          behavior: 'smooth'
        });
        return;
      }

      if (rect.bottom > viewHeight - margin) {
        window.scrollBy({
          top: rect.bottom - viewHeight + margin,
          behavior: 'smooth'
        });
      } else if (rect.top < margin) {
        window.scrollBy({
          top: rect.top - margin,
          behavior: 'smooth'
        });
      }
    });
  }

  function setToggleOpen(toggle, open) {
    if (!(toggle instanceof HTMLElement)) return;

    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.textContent = open ? 'skrýt' : 'detail';
    if (open) {
      toggle.style.background = 'var(--clr_cervena,#c00000)';
      toggle.style.borderColor = 'var(--clr_cervena_3,#a00000)';
      toggle.style.color = 'var(--clr_bila,#ffffff)';
    } else {
      toggle.style.background = '';
      toggle.style.borderColor = '';
      toggle.style.color = '';
    }
  }

  function closeOtherDetails(activeRow, activeToggle) {
    document.querySelectorAll('[data-row-detail]').forEach(function (row) {
      if (!(row instanceof HTMLElement) || row === activeRow || row.hidden) return;

      row.hidden = true;
      const detailId = String(row.getAttribute('data-row-detail') || '').trim();
      if (detailId === '') return;

      document.querySelectorAll('[data-row-detail-toggle="' + CSS.escape(detailId) + '"]').forEach(function (toggle) {
        if (!(toggle instanceof HTMLElement) || toggle === activeToggle) return;
        setToggleOpen(toggle, false);
      });
    });
  }

  document.addEventListener('click', function (event) {
    const toggle = event.target instanceof Element
      ? event.target.closest('[data-row-detail-toggle]')
      : null;
    if (!(toggle instanceof HTMLElement)) return;

    const detailId = String(toggle.getAttribute('data-row-detail-toggle') || '').trim();
    if (detailId === '') return;

    const row = document.querySelector('[data-row-detail="' + CSS.escape(detailId) + '"]');
    if (!(row instanceof HTMLElement)) return;

    event.preventDefault();
    event.stopPropagation();

    const opening = row.hidden;
    if (opening) {
      closeOtherDetails(row, toggle);
    }

    row.hidden = !opening;
    setToggleOpen(toggle, opening);

    if (opening) {
      adjustViewport(row);
    }
  }, true);
})();

// js/rozbalovaci_detail.js * Verze: V2 * Aktualizace: 04.06.2026
// Konec souboru
