// js/karty_hlavicka.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  const MOVE_DEFAULT_TEXT = 'Přesunout na pozici';
  const MOVE_HINT_TEXT = 'Klikni na nové umístění karty';

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
    let moveSource = null;

    function getCardModeConfirmModal() {
      const root = document.getElementById('cbCardModeModal');
      if (!(root instanceof HTMLElement)) return null;
      const msg = root.querySelector('[data-cb-cardmode-msg]');
      const cancelBtn = root.querySelector('[data-cb-cardmode-close]');
      const confirmBtn = root.querySelector('[data-cb-cardmode-confirm]');
      if (!(msg instanceof HTMLElement) || !(cancelBtn instanceof HTMLElement) || !(confirmBtn instanceof HTMLElement)) {
        return null;
      }
      return { root, msg, cancelBtn, confirmBtn };
    }

    function closeCardModeConfirmModal() {
      const modal = getCardModeConfirmModal();
      if (!modal) return false;
      modal.root.classList.add('is-hidden');
      modal.root.setAttribute('aria-hidden', 'true');
      modal.msg.textContent = '';
      modal.cancelBtn.textContent = 'Rozumím';
      modal.confirmBtn.textContent = 'Potvrdit';
      modal.confirmBtn.classList.add('is-hidden');
      return true;
    }

    function openMoveUnlockConfirm(onYes, onNo) {
      const modal = getCardModeConfirmModal();
      if (!modal) return false;

      modal.msg.innerHTML = 'Cílová pozice je obsazena dříve umístěnou kartou.<br><br>Pokud trváš na přesunu karty,<br>bude karta na cílové pozici uvolněna z pozice.';
      modal.cancelBtn.textContent = 'Jéminkote, netrvám na tom';
      modal.confirmBtn.textContent = 'Trvám na přesunu';
      modal.confirmBtn.classList.remove('is-hidden');
      modal.root.classList.remove('is-hidden');
      modal.root.setAttribute('aria-hidden', 'false');
      modal.confirmBtn.focus();

      const handleYes = function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        closeCardModeConfirmModal();
        if (typeof onYes === 'function') {
          onYes();
        }
      };
      const handleNo = function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        closeCardModeConfirmModal();
        if (typeof onNo === 'function') {
          onNo();
        }
      };

      modal.confirmBtn.addEventListener('click', handleYes, { once: true });
      modal.cancelBtn.addEventListener('click', handleNo, { once: true });
      return true;
    }

    function setMoveButtonState(btn, isHint) {
      if (!(btn instanceof HTMLElement)) return;
      if (isHint) {
        btn.textContent = MOVE_HINT_TEXT;
        btn.classList.add('card_pref_item_move_hint');
      } else {
        btn.textContent = MOVE_DEFAULT_TEXT;
        btn.classList.remove('card_pref_item_move_hint');
      }
    }

    function clearMoveSource() {
      if (moveSource && moveSource.root instanceof HTMLElement) {
        moveSource.root.removeAttribute('data-card-move-source');
      }
      if (moveSource && moveSource.btn instanceof HTMLElement) {
        setMoveButtonState(moveSource.btn, false);
      }
      moveSource = null;
      if (document.body) {
        document.body.removeAttribute('data-card-move-active');
      }
    }

    function startMoveSource(fromBtn) {
      const wrap = fromBtn.closest('[data-card-pref-wrap]');
      const root = wrap ? wrap.closest('.card_shell') : null;
      if (!(root instanceof HTMLElement)) return false;
      const id = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      const col = parseInt(String(root.getAttribute('data-card-col') || '0'), 10);
      const line = parseInt(String(root.getAttribute('data-card-line') || '0'), 10);
      const title = String(root.getAttribute('data-card-title') || '').trim();
      if (!Number.isFinite(id) || id <= 0 || !Number.isFinite(col) || col <= 0 || !Number.isFinite(line) || line <= 0) {
        return false;
      }

      clearMoveSource();
      moveSource = { id, col, line, title, root, btn: fromBtn };
      root.setAttribute('data-card-move-source', '1');
      setMoveButtonState(fromBtn, true);
      if (document.body) {
        document.body.setAttribute('data-card-move-active', '1');
      }
      return true;
    }

    function doMoveToTarget(targetRoot, forceUnlock) {
      if (!(moveSource && moveSource.root instanceof HTMLElement)) return;
      const targetId = parseInt(String(targetRoot.getAttribute('data-card-id') || '0'), 10);
      const targetCol = parseInt(String(targetRoot.getAttribute('data-card-col') || '0'), 10);
      const targetLine = parseInt(String(targetRoot.getAttribute('data-card-line') || '0'), 10);
      const targetLocked = String(targetRoot.getAttribute('data-card-pos-locked') || '0') === '1';
      if (!Number.isFinite(targetId) || targetId <= 0 || !Number.isFinite(targetCol) || targetCol <= 0 || !Number.isFinite(targetLine) || targetLine <= 0) {
        clearMoveSource();
        return;
      }
      if (targetId === moveSource.id) {
        clearMoveSource();
        return;
      }

      fetch('index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Comeback-Set-Card-Position': '1'
        },
        body: JSON.stringify({
          src_id: moveSource.id,
          src_col: moveSource.col,
          src_line: moveSource.line,
          tgt_id: targetId,
          tgt_col: targetCol,
          tgt_line: targetLine,
          target_locked: targetLocked ? 1 : 0,
          force_unlock: forceUnlock ? 1 : 0
        })
      }).then((r) => r.json().catch(() => ({}))).then((data) => {
        if (data && data.ok) {
          clearMoveSource();
          window.location.reload();
          return;
        }

        if (data && data.needs_confirm) {
          if (!openMoveUnlockConfirm(function () {
            doMoveToTarget(targetRoot, true);
          }, function () {
            clearMoveSource();
          })) {
            if (window.confirm('Cílová pozice je obsazena zamčenou kartou. Pokud trváš na přesunu, bude karta uvolněna z pozice.')) {
              doMoveToTarget(targetRoot, true);
            } else {
              clearMoveSource();
            }
          }
          return;
        }

        const err = String((data && data.err) ? data.err : 'Přesun karty selhal.');
        window.alert(err);
        clearMoveSource();
      }).catch(() => {
        window.alert('Přesun karty selhal.');
        clearMoveSource();
      });
    }

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

        if (moveSource && !(target.closest('[data-card-pref-wrap]'))) {
          const targetRoot = target.closest('.card_shell');
          if (targetRoot instanceof HTMLElement) {
            e.preventDefault();
            e.stopPropagation();
            doMoveToTarget(targetRoot, false);
            return;
          }
        }

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

        const moveBtn = target.closest('[data-card-pref-move]');
        if (moveBtn) {
          const ok = startMoveSource(moveBtn);
          const wrap = moveBtn.closest('[data-card-pref-wrap]');
          const menu = wrap ? wrap.querySelector('[data-card-pref-menu]') : null;
          const frame = menu ? menu.querySelector('[data-card-pref-frame]') : null;
          if (menu instanceof HTMLElement) {
            menu.classList.remove('is-hidden');
            menu.classList.remove('card_pref_menu_frame');
          }
          if (frame instanceof HTMLIFrameElement) {
            frame.classList.add('is-hidden');
            frame.removeAttribute('src');
          }
          const toggle = wrap ? wrap.querySelector('[data-card-pref-toggle]') : null;
          if (toggle instanceof HTMLElement) {
            toggle.setAttribute('aria-expanded', 'true');
          }
          if (!ok) {
            clearMoveSource();
          }
          return;
        }

        const unlockAllBtn = target.closest('[data-card-pref-unlock-all]');
        if (unlockAllBtn) {
          fetch('index.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-Comeback-Unlock-All-Card-Pos': '1'
            },
            body: JSON.stringify({})
          }).then((r) => r.json().catch(() => ({}))).then((data) => {
            if (data && data.ok) {
              clearMoveSource();
              window.location.reload();
              return;
            }
            const err = String((data && data.err) ? data.err : 'Odemknutí pozic karet selhalo.');
            window.alert(err);
          }).catch(() => {
            window.alert('Odemknutí pozic karet selhalo.');
          });
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
        if (moveSource) {
          clearMoveSource();
          return;
        }
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
