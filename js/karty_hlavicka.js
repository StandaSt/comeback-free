// js/karty_hlavicka.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  function findCardToggle(cardId) {
    const cid = String(cardId || '').trim();
    if (cid === '') return null;
    const root = document.querySelector('.card_shell[data-card-id="' + cid + '"]');
    if (!(root instanceof HTMLElement)) return null;
    const wrap = root.querySelector('[data-card-pref-wrap]');
    if (!(wrap instanceof HTMLElement)) return null;
    const toggle = wrap.querySelector('[data-card-pref-toggle]');
    return toggle instanceof HTMLElement ? toggle : null;
  }

  function setCardIconPreview(cardId, src) {
    const toggle = findCardToggle(cardId);
    const iconSrc = String(src || '').trim();
    if (!(toggle instanceof HTMLElement) || iconSrc === '') return;
    if (!toggle.hasAttribute('data-preview-icon-backup')) {
      toggle.setAttribute('data-preview-icon-backup', String(toggle.innerHTML || ''));
    }
    toggle.setAttribute('data-preview-icon-dirty', '1');
    toggle.innerHTML = '<span class="card_pref_icon"><img src="' + iconSrc.replace(/"/g, '&quot;') + '" class="card_pref_icon_img" alt=""></span>';
  }

  function setCardIconDotsPreview(cardId) {
    const toggle = findCardToggle(cardId);
    if (!(toggle instanceof HTMLElement)) return;
    if (!toggle.hasAttribute('data-preview-icon-backup')) {
      toggle.setAttribute('data-preview-icon-backup', String(toggle.innerHTML || ''));
    }
    toggle.setAttribute('data-preview-icon-dirty', '1');
    toggle.innerHTML = '<span class="card_pref_dots txt_seda">&#8942;</span>';
  }

  function commitCardIconPreview(cardId) {
    const toggle = findCardToggle(cardId);
    if (!(toggle instanceof HTMLElement)) return;
    toggle.setAttribute('data-preview-icon-backup', String(toggle.innerHTML || ''));
    toggle.removeAttribute('data-preview-icon-dirty');
  }

  function getBranchSelect() {
    const branchSelect = document.querySelector('[data-cb-branch-select="1"]');
    return branchSelect instanceof HTMLSelectElement ? branchSelect : null;
  }

  function setBranchSelectDisabledForRoot(root, isDisabled) {
    if (!(root instanceof Element) || !root.classList.contains('cb-zadani-reportu')) {
      return;
    }

    const branchSelect = getBranchSelect();
    if (!branchSelect) {
      return;
    }

    branchSelect.disabled = !!isDisabled;
  }

  function syncBranchTitle() {
    const branchSelect = getBranchSelect();
    let branchName = '';

    if (branchSelect) {
      const option = branchSelect.selectedOptions && branchSelect.selectedOptions[0]
        ? branchSelect.selectedOptions[0]
        : branchSelect.options[branchSelect.selectedIndex] || null;
      branchName = option ? String(option.textContent || '').trim() : '';
    }

    document.querySelectorAll('.cb-zadani-reportu [data-zr-card-title], .dash_maxi_card [data-zr-card-title]').forEach((titleEl) => {
      if (!(titleEl instanceof HTMLElement)) return;
      const base = String(titleEl.getAttribute('data-zr-card-title-base') || '').trim();
      if (base === '') return;
      titleEl.textContent = branchName !== '' ? (base + ' - ' + branchName) : base;
    });
  }

  function initKartyHlavicka() {
    const branchSelect = getBranchSelect();
    if (branchSelect && branchSelect.getAttribute('data-zr-title-bound') !== '1') {
      branchSelect.setAttribute('data-zr-title-bound', '1');
      branchSelect.addEventListener('change', syncBranchTitle);
    }

    function restoreUnsavedPreviewByMenu(menu) {
      if (!(menu instanceof HTMLElement)) return;
      const wrap = menu.closest('[data-card-pref-wrap]');
      const root = wrap ? wrap.closest('.card_shell') : null;
      const head = root ? root.querySelector('.card_top') : null;
      if (!(head instanceof HTMLElement)) return;
      if (head.getAttribute('data-preview-dirty') !== '1') return;

      const backup = String(head.getAttribute('data-preview-backup') || '');
      if (backup === '') {
        head.removeAttribute('style');
      } else {
        head.setAttribute('style', backup);
      }
      head.removeAttribute('data-preview-dirty');

      const toggle = wrap ? wrap.querySelector('[data-card-pref-toggle]') : null;
      if (toggle instanceof HTMLElement && toggle.getAttribute('data-preview-icon-dirty') === '1') {
        const iconBackup = String(toggle.getAttribute('data-preview-icon-backup') || '');
        if (iconBackup !== '') {
          toggle.innerHTML = iconBackup;
        }
        toggle.removeAttribute('data-preview-icon-dirty');
      }
    }

    if (document.body && document.body.getAttribute('data-card-pref-bound') !== '1') {
      document.body.setAttribute('data-card-pref-bound', '1');

      document.addEventListener('click', function (e) {
        const target = e.target instanceof Element ? e.target : null;
        if (!target) return;

        const openBtn = target.closest('[data-card-pref-open]');
        if (openBtn) {
          const wrap = openBtn.closest('[data-card-pref-wrap]');
          const menu = wrap ? wrap.querySelector('[data-card-pref-menu]') : null;
          const frame = menu ? menu.querySelector('[data-card-pref-frame]') : null;
          const url = String(openBtn.getAttribute('data-card-pref-url') || '').trim();
          if (menu && frame && url !== '') {
            menu.classList.remove('is-hidden');
            menu.classList.add('card_pref_menu_frame');
            frame.setAttribute('src', url);
            frame.classList.remove('is-hidden');
          }
          return;
        }

        const toggleBtn = target.closest('[data-card-pref-toggle]');
        if (toggleBtn) {
          const wrap = toggleBtn.closest('[data-card-pref-wrap]');
          const menu = wrap ? wrap.querySelector('[data-card-pref-menu]') : null;
          if (!menu) return;
          const openNow = menu.classList.contains('is-hidden');

          document.querySelectorAll('[data-card-pref-menu]').forEach(function (m) {
            if (!(m instanceof HTMLElement)) return;
            if (m !== menu) {
              restoreUnsavedPreviewByMenu(m);
              m.classList.add('is-hidden');
              m.classList.remove('card_pref_menu_frame');
              const f = m.querySelector('[data-card-pref-frame]');
              if (f instanceof HTMLIFrameElement) {
                f.classList.add('is-hidden');
                f.removeAttribute('src');
              }
            }
          });

          if (openNow) {
            menu.classList.remove('is-hidden');
            menu.classList.remove('card_pref_menu_frame');
            toggleBtn.setAttribute('aria-expanded', 'true');
          } else {
            restoreUnsavedPreviewByMenu(menu);
            menu.classList.add('is-hidden');
            menu.classList.remove('card_pref_menu_frame');
            toggleBtn.setAttribute('aria-expanded', 'false');
            const frame = menu.querySelector('[data-card-pref-frame]');
            if (frame instanceof HTMLIFrameElement) {
              frame.classList.add('is-hidden');
              frame.removeAttribute('src');
            }
          }
          return;
        }

        if (!target.closest('[data-card-pref-wrap]')) {
          document.querySelectorAll('[data-card-pref-menu]').forEach(function (m) {
            if (!(m instanceof HTMLElement)) return;
            restoreUnsavedPreviewByMenu(m);
            m.classList.add('is-hidden');
            m.classList.remove('card_pref_menu_frame');
            const frame = m.querySelector('[data-card-pref-frame]');
            if (frame instanceof HTMLIFrameElement) {
                frame.classList.add('is-hidden');
              frame.removeAttribute('src');
            }
          });
          document.querySelectorAll('[data-card-pref-toggle]').forEach(function (btn) {
            if (btn instanceof HTMLElement) {
              btn.setAttribute('aria-expanded', 'false');
            }
          });
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('[data-card-pref-menu]').forEach(function (m) {
          if (!(m instanceof HTMLElement)) return;
          restoreUnsavedPreviewByMenu(m);
          m.classList.add('is-hidden');
          m.classList.remove('card_pref_menu_frame');
          const frame = m.querySelector('[data-card-pref-frame]');
          if (frame instanceof HTMLIFrameElement) {
            frame.classList.add('is-hidden');
            frame.removeAttribute('src');
          }
        });
        document.querySelectorAll('[data-card-pref-toggle]').forEach(function (btn) {
          if (btn instanceof HTMLElement) {
            btn.setAttribute('aria-expanded', 'false');
          }
        });
      });
    }

    syncBranchTitle();
  }

  w.cbSetBranchSelectDisabledForRoot = setBranchSelectDisabledForRoot;
  w.cbSetCardIconPreview = setCardIconPreview;
  w.cbSetCardIconDotsPreview = setCardIconDotsPreview;
  w.cbCommitCardIconPreview = commitCardIconPreview;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyHlavicka);
  } else {
    initKartyHlavicka();
  }
}(window));
