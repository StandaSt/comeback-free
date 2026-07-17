// js/karty_hlavicka.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  const MOVE_DEFAULT_TEXT = 'Přesunout kartu';
  const MOVE_HINT_TEXT = 'Zvol kartu pro prohození pořadí';
  const UNLOCK_ALL_CONFIRM_HTML = 'Pokoušíte se obnovit výchozí pořadí karet.<br><br>Barvy a ikony karet zůstanou zachované.';
  const UNLOCK_ALL_CONFIRM_TEXT = 'Pokoušíte se obnovit výchozí pořadí karet. Barvy a ikony karet zůstanou zachované.';

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

  function syncReportCardSubtitles(scope) {
    const root = (scope instanceof Element || scope instanceof Document) ? scope : document;

    root.querySelectorAll('[data-zr-card-subtitle-side]').forEach((node) => {
      if (!(node instanceof HTMLElement)) return;

      const sideText = String(node.getAttribute('data-zr-card-subtitle-side') || '').trim();

      const card = node.closest('.card_shell');
      if (!(card instanceof HTMLElement)) return;

      const subtitleSide = card.querySelector('[data-card-subtitle-side]');
      if (!(subtitleSide instanceof HTMLElement)) return;

      subtitleSide.textContent = sideText;
    });
  }

  function initKartyHlavicka() {
    let moveSource = null;
    const KPI_COLLAPSE_QUERY = '(max-width: 1559px)';
    const kpiCollapseMql = typeof w.matchMedia === 'function' ? w.matchMedia(KPI_COLLAPSE_QUERY) : null;

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

    function openSystemAlert(message) {
      const modal = getCardModeConfirmModal();
      const text = String(message || '').trim();
      if (text === '') return false;
      if (!modal) return false;

      modal.msg.textContent = text;
      modal.cancelBtn.textContent = 'Rozumím';
      modal.confirmBtn.textContent = 'Potvrdit';
      modal.confirmBtn.classList.add('is-hidden');
      modal.root.classList.remove('is-hidden');
      modal.root.setAttribute('aria-hidden', 'false');
      modal.cancelBtn.focus();
      return true;
    }

    function getHeaderRoot() {
      const root = document.querySelector('.head_box');
      return root instanceof HTMLElement ? root : null;
    }

    function getKpiToggleButton() {
      const btn = document.querySelector('[data-cb-kpi-toggle="1"]');
      return btn instanceof HTMLButtonElement ? btn : null;
    }

    function isKpiCollapseMode() {
      if (kpiCollapseMql) return !!kpiCollapseMql.matches;
      return (w.innerWidth || 0) <= 1559;
    }

    function syncKpiToggleUi() {
      const header = getHeaderRoot();
      const btn = getKpiToggleButton();
      if (!(header instanceof HTMLElement) || !(btn instanceof HTMLElement)) return;

      if (!isKpiCollapseMode()) {
        header.classList.remove('is-kpi-hidden');
        btn.textContent = 'Skrýt KPI';
        btn.classList.remove('is-kpi-show');
        btn.classList.add('is-kpi-hide');
        btn.setAttribute('aria-pressed', 'false');
        return;
      }

      const hidden = header.classList.contains('is-kpi-hidden');
      btn.textContent = hidden ? 'Zobrazit KPI' : 'Skrýt KPI';
      btn.classList.toggle('is-kpi-show', hidden);
      btn.classList.toggle('is-kpi-hide', !hidden);
      btn.setAttribute('aria-pressed', hidden ? 'true' : 'false');
    }

    function saveKpiState(kpiState) {
      const value = kpiState === 1 ? 1 : 0;
      w.fetch('index_is.php', {
        method: 'POST',
        headers: {
          'X-Comeback-KPI-Setting': '1',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ kpi: value })
      }).catch(() => {});
    }

    function toggleKpiVisibility() {
      if (!isKpiCollapseMode()) return;
      const header = getHeaderRoot();
      if (!(header instanceof HTMLElement)) return;
      header.classList.toggle('is-kpi-hidden');
      saveKpiState(header.classList.contains('is-kpi-hidden') ? 0 : 1);
      syncKpiToggleUi();
    }

    function applySavedKpiState() {
      if (!isKpiCollapseMode()) return;
      const header = getHeaderRoot();
      const btn = getKpiToggleButton();
      if (!(header instanceof HTMLElement) || !(btn instanceof HTMLElement)) return;
      const kpiState = String(btn.getAttribute('data-cb-kpi-state') || '1') === '0' ? 0 : 1;
      header.classList.toggle('is-kpi-hidden', kpiState === 0);
      syncKpiToggleUi();
    }

    function setDashboardLoading(on, text) {
      if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
        w.CB_AJAX.setDashboardLoading(!!on, 'cards', text);
      }
    }

    function traceAjax(event, data) {
      if (w.CB_AJAX && typeof w.CB_AJAX.trace === 'function') {
        w.CB_AJAX.trace(event, data);
      }
    }

    function logUserCardAction(actionId, cardId, success, errMsg, detail, source) {
      const idAkce = parseInt(String(actionId || '0'), 10);
      const idKarta = parseInt(String(cardId || '0'), 10);
      if (!Number.isFinite(idAkce) || idAkce <= 0 || !Number.isFinite(idKarta) || idKarta <= 0) {
        return;
      }

      const payload = {
        id_akce: idAkce,
        id_karta: idKarta,
        vysledek: success ? 1 : 0,
        err_msg: String(errMsg || '').trim(),
        zdroj: String(source || 'karty_hlavicka').trim() || 'karty_hlavicka'
      };
      if (detail && typeof detail === 'object') {
        payload.detail = detail;
      }

      w.fetch('index_is.php', {
        method: 'POST',
        headers: {
          'X-Comeback-User-Akce': '1',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      }).catch(() => {});
    }

    function logUserHeaderAction(actionId, detail, source) {
      const idAkce = parseInt(String(actionId || '0'), 10);
      if (!Number.isFinite(idAkce) || idAkce <= 0) {
        return;
      }

      const payload = {
        id_akce: idAkce,
        vysledek: 1,
        err_msg: '',
        zdroj: String(source || 'karty_hlavicka').trim() || 'karty_hlavicka'
      };
      if (detail && typeof detail === 'object') {
        payload.detail = detail;
      }

      w.fetch('index_is.php', {
        method: 'POST',
        headers: {
          'X-Comeback-User-Akce': '1',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      }).catch(() => {});
    }

    function logEmptyCardClick(target) {
      if (!(target instanceof Element)) return;
      if (target.closest('button, a, input, select, textarea, label, [role="button"], [data-card-pref-wrap], [contenteditable="true"]')) {
        return;
      }

      const root = target.closest('.card_shell[data-card-id]');
      if (!(root instanceof HTMLElement)) return;

      const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      if (!Number.isFinite(cardId) || cardId <= 0) return;

      logUserCardAction(20, cardId, true, '', {
        event: 'empty_card_click',
        mode: String(root.getAttribute('data-card-mode') || '').trim()
      }, 'karty_hlavicka');
    }

    function logEmptyHeaderClick(target) {
      if (!(target instanceof Element)) return;
      if (target.closest('button, a, input, select, textarea, label, [role="button"], [contenteditable="true"]')) {
        return;
      }

      const block = target.closest('.head_kpi, .head_user');
      if (!(block instanceof HTMLElement)) return;

      let blockName = '';
      if (block.classList.contains('head_kpi')) blockName = 'kpi';
      if (block.classList.contains('head_user')) blockName = 'user';
      if (blockName === '') return;

      logUserHeaderAction(20, {
        event: 'empty_header_click',
        blok: blockName
      }, 'karty_hlavicka');
    }

    applySavedKpiState();
    if (kpiCollapseMql && typeof kpiCollapseMql.addEventListener === 'function') {
      kpiCollapseMql.addEventListener('change', applySavedKpiState);
    } else if (kpiCollapseMql && typeof kpiCollapseMql.addListener === 'function') {
      kpiCollapseMql.addListener(applySavedKpiState);
    } else {
      w.addEventListener('resize', applySavedKpiState);
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

    function openUnlockAllConfirm(onYes, onNo) {
      const modal = getCardModeConfirmModal();
      if (!modal) return false;

      modal.msg.innerHTML = UNLOCK_ALL_CONFIRM_HTML;
      modal.cancelBtn.textContent = 'Jéminkote, sem se uklik';
      modal.confirmBtn.textContent = 'ANO, odemkni karty';
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

    function closeAllCardPrefMenus() {
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

    function clearCardPlacement(root) {
      if (!(root instanceof HTMLElement)) return;
      const section = root.closest('[data-cb-dash-card="1"]');
      const prefWrap = root.querySelector('[data-card-pref-wrap]');

      root.setAttribute('data-card-col', '0');
      root.setAttribute('data-card-line', '0');
      root.setAttribute('data-card-pos-locked', '0');
      if (prefWrap instanceof HTMLElement) {
        prefWrap.classList.remove('card_pref_wrap_pos_locked');
      }

      if (section instanceof HTMLElement) {
        section.style.gridColumn = '';
        section.style.gridRow = '';
      }
    }

    function clearAllCardPlacements() {
      document.querySelectorAll('.card_shell').forEach(function (root) {
        if (!(root instanceof HTMLElement)) return;
        if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') return;
        clearCardPlacement(root);
      });
    }

    function startMoveSource(fromBtn) {
      const wrap = fromBtn.closest('[data-card-pref-wrap]');
      const root = wrap ? wrap.closest('.card_shell') : null;
      if (!(root instanceof HTMLElement)) return false;
      const id = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      const title = String(root.getAttribute('data-card-title') || '').trim();
      if (!Number.isFinite(id) || id <= 0) {
        return false;
      }

      clearMoveSource();
      moveSource = { id, title, root, btn: fromBtn };
      traceAjax('card_move_start', {
        id: id,
        title: title
      });
      root.setAttribute('data-card-move-source', '1');
      setMoveButtonState(fromBtn, true);
      if (document.body) {
        document.body.setAttribute('data-card-move-active', '1');
      }
      return true;
    }

    function doMoveToTarget(targetRoot) {
      if (!(moveSource && moveSource.root instanceof HTMLElement)) return;
      const targetMode = String(targetRoot.getAttribute('data-card-mode') || '').trim();
      if (targetMode === 'nano') {
        if (!openSystemAlert('Nano kartu nelze použít jako cíl přesunu.')) {
          window.alert('Nano kartu nelze použít jako cíl přesunu.');
        }
        clearMoveSource();
        return;
      }
      const targetId = parseInt(String(targetRoot.getAttribute('data-card-id') || '0'), 10);
      if (!Number.isFinite(targetId) || targetId <= 0) {
        clearMoveSource();
        return;
      }
      if (targetId === moveSource.id) {
        traceAjax('card_move_same_target', {
          id: moveSource.id,
          targetId: targetId
        });
        clearMoveSource();
        return;
      }

      traceAjax('card_move_submit', {
        src_id: moveSource.id,
        tgt_id: targetId
      });
      setDashboardLoading(true, 'Přesouvám kartu ...');
      fetch('index_is.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Comeback-Set-Card-Position': '1'
        },
        body: JSON.stringify({
          src_id: moveSource.id,
          tgt_id: targetId
        })
      }).then((r) => r.json().catch(() => ({}))).then((data) => {
        if (data && data.ok) {
          traceAjax('card_move_ok', {
            src_id: moveSource.id,
            tgt_id: targetId
          });
          logUserCardAction(5, moveSource.id, true, '');
          clearMoveSource();
          closeAllCardPrefMenus();
          if (w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
            return Promise.resolve(w.CB_AJAX.refreshDashboard({
              force: true,
              loaderMode: 'dashboard'
            })).finally(() => {
              setDashboardLoading(false);
            });
          }
          if (w.CB_AJAX && typeof w.CB_AJAX.relayoutDashboard === 'function') {
            w.CB_AJAX.relayoutDashboard();
          }
          setDashboardLoading(false);
          return;
        }

        setDashboardLoading(false);
        const err = String((data && data.err) ? data.err : 'Přesun karty selhal.');
        traceAjax('card_move_error', {
          src_id: moveSource.id,
          tgt_id: targetId,
          message: err
        });
        logUserCardAction(5, moveSource.id, false, err);
        if (!openSystemAlert(err)) {
          window.alert(err);
        }
        clearMoveSource();
      }).catch(() => {
        setDashboardLoading(false);
        traceAjax('card_move_fetch_error', {
          src_id: moveSource ? moveSource.id : 0
        });
        if (moveSource && Number.isFinite(moveSource.id) && moveSource.id > 0) {
          logUserCardAction(5, moveSource.id, false, 'Presun karty selhal.');
        }
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
            doMoveToTarget(targetRoot);
            return;
          }
        }

        const openBtn = target.closest('[data-card-pref-open]');
        if (openBtn) {
          const openType = String(openBtn.getAttribute('data-card-pref-open') || '').trim();
          if (openType === 'color') {
            const wrapForLog = openBtn.closest('[data-card-pref-wrap]');
            const rootForLog = wrapForLog ? wrapForLog.closest('.card_shell') : null;
            const idForLog = rootForLog ? parseInt(String(rootForLog.getAttribute('data-card-id') || '0'), 10) : 0;
            if (Number.isFinite(idForLog) && idForLog > 0) {
              logUserCardAction(6, idForLog, true, '', { event: 'menu_open_color' }, 'karty_hlavicka');
            }
          }
          if (openType === 'ikon') {
            const wrapForLog = openBtn.closest('[data-card-pref-wrap]');
            const rootForLog = wrapForLog ? wrapForLog.closest('.card_shell') : null;
            const idForLog = rootForLog ? parseInt(String(rootForLog.getAttribute('data-card-id') || '0'), 10) : 0;
            if (Number.isFinite(idForLog) && idForLog > 0) {
              logUserCardAction(7, idForLog, true, '', { event: 'menu_open_icon' }, 'karty_hlavicka');
            }
          }
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
          if (!ok) {
            clearMoveSource();
            return;
          }
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
          return;
        }
        const unlockAllBtn = target.closest('[data-card-pref-unlock-all]');
        if (unlockAllBtn) {
          const runUnlockAll = function () {
            traceAjax('card_unlock_all_click', {});
            fetch('index_is.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-Comeback-Unlock-All-Card-Pos': '1'
              },
              body: JSON.stringify({})
            }).then((r) => r.json().catch(() => ({}))).then((data) => {
              if (data && data.ok) {
                traceAjax('card_unlock_all_ok', {});
                clearAllCardPlacements();
                clearMoveSource();
                closeAllCardPrefMenus();
                if (w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
                  return w.CB_AJAX.refreshDashboard({
                    force: true,
                    loaderMode: 'dashboard'
                  }).finally(() => {
                    setDashboardLoading(false);
                  });
                }
                if (w.CB_AJAX && typeof w.CB_AJAX.relayoutDashboard === 'function') {
                  w.CB_AJAX.relayoutDashboard();
                }
                setDashboardLoading(false);
                return;
              }
              const err = String((data && data.err) ? data.err : 'Obnovení výchozího pořadí karet selhalo.');
              traceAjax('card_unlock_all_error', { message: err });
              window.alert(err);
            }).catch(() => {
              traceAjax('card_unlock_all_fetch_error', {});
              window.alert('Obnovení výchozího pořadí karet selhalo.');
            });
          };

          if (!openUnlockAllConfirm(runUnlockAll, function () {})) {
            if (window.confirm(UNLOCK_ALL_CONFIRM_TEXT)) {
              runUnlockAll();
            }
          }
          return;
        }

        const kpiToggleBtn = target.closest('[data-cb-kpi-toggle="1"]');
        if (kpiToggleBtn) {
          if (isKpiCollapseMode()) {
            e.preventDefault();
            toggleKpiVisibility();
          }
          return;
        }

        const toggleBtn = target.closest('[data-card-pref-toggle]');
        if (toggleBtn) {
          const wrap = toggleBtn.closest('[data-card-pref-wrap]');
          const menu = wrap ? wrap.querySelector('[data-card-pref-menu]') : null;
          if (!menu) return;
          const openNow = menu.classList.contains('is-hidden');
          closeAllCardPrefMenus();

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
          closeAllCardPrefMenus();
        }

        logEmptyCardClick(target);
        logEmptyHeaderClick(target);
      });

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (moveSource) {
          clearMoveSource();
          return;
        }
        closeAllCardPrefMenus();
      });
    }

    syncBranchTitle();
    syncReportCardSubtitles(document);
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

  document.addEventListener('cb:card-swapped', function (e) {
    syncReportCardSubtitles(e.target instanceof Element ? e.target : document);
  });

  document.addEventListener('cb:card-max-loaded', function (e) {
    syncReportCardSubtitles(e.target instanceof Element ? e.target : document);
  });
}(window));
