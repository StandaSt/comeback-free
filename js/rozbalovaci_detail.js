// js/rozbalovaci_detail.js * Verze: V6 * Aktualizace: 04.06.2026
'use strict';

(function () {
  function adjustViewport(rows, toggle) {
    const visibleRows = Array.from(rows || []).filter(function (row) {
      return row instanceof HTMLElement && !row.hidden;
    });
    if (visibleRows.length === 0) return;

    window.requestAnimationFrame(function () {
      const scrollParent = getScrollParent(visibleRows[0]);
      const mainRow = toggle instanceof HTMLElement ? toggle.closest('tr') : null;
      if (!(scrollParent instanceof HTMLElement) || !(mainRow instanceof HTMLElement)) return;

      const margin = 16;
      const scrollRect = scrollParent.getBoundingClientRect();
      const mainRect = mainRow.getBoundingClientRect();
      const firstDetailRect = visibleRows[0].getBoundingClientRect();
      const lastRect = visibleRows[visibleRows.length - 1].getBoundingClientRect();
      const table = mainRow.closest('table');
      const stickyBottom = getStickyHeaderBottom(table, scrollRect);
      const topLimit = Math.max(scrollRect.top, stickyBottom);
      const bottomLimit = scrollRect.bottom - margin;
      const titleDelta = firstDetailRect.bottom - bottomLimit;
      const endDelta = lastRect.bottom - bottomLimit;
      const neededDelta = Math.max(0, titleDelta, endDelta);
      const maxDelta = mainRect.top - topLimit;
      if (maxDelta < 0) {
        scrollDetailBy(scrollParent, maxDelta);
        return;
      }

      const delta = Math.max(0, Math.min(neededDelta, maxDelta));
      scrollDetailBy(scrollParent, delta);
    });
  }

  function getScrollParent(element) {
    let current = element instanceof HTMLElement ? element.parentElement : null;
    while (current instanceof HTMLElement && current !== document.body) {
      const style = window.getComputedStyle(current);
      const overflowY = style.overflowY;
      const canScroll = (overflowY === 'auto' || overflowY === 'scroll') && current.scrollHeight > current.clientHeight;
      if (canScroll) return current;
      current = current.parentElement;
    }
    return null;
  }

  function getStickyHeaderBottom(table, scrollRect) {
    if (!(table instanceof HTMLElement)) return scrollRect.top;

    let bottom = scrollRect.top;
    table.querySelectorAll('thead th').forEach(function (cell) {
      if (!(cell instanceof HTMLElement)) return;

      const rect = cell.getBoundingClientRect();
      const isVisible = rect.bottom > scrollRect.top && rect.top < scrollRect.bottom;
      if (isVisible) {
        bottom = Math.max(bottom, rect.bottom);
      }
    });

    return bottom;
  }

  function scrollDetailBy(scrollParent, delta) {
    if (!Number.isFinite(delta) || Math.abs(delta) < 1) return;
    if (!(scrollParent instanceof HTMLElement)) return;

    scrollParent.scrollBy({
      top: delta,
      behavior: 'smooth'
    });
  }

  function setToggleOpen(toggle, open) {
    if (!(toggle instanceof HTMLElement)) return;

    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.textContent = open ? 'skrýt' : 'detail';
    if (open) {
      toggle.style.background = '#ffdada';
      toggle.style.borderColor = '#e6a4a4';
      toggle.style.color = '#8a1f1f';
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

    const rows = document.querySelectorAll('[data-row-detail="' + CSS.escape(detailId) + '"]');
    if (rows.length === 0) return;

    event.preventDefault();
    event.stopPropagation();

    const opening = Array.from(rows).some(function (row) {
      return row instanceof HTMLElement && row.hidden;
    });
    if (opening) {
      closeOtherDetails(null, toggle);
    }

    rows.forEach(function (row) {
      if (row instanceof HTMLElement) {
        row.hidden = !opening;
      }
    });
    setToggleOpen(toggle, opening);

    if (opening) {
      adjustViewport(rows, toggle);
    }
  }, true);
})();

// js/rozbalovaci_detail.js * Verze: V6 * Aktualizace: 04.06.2026
// Konec souboru
