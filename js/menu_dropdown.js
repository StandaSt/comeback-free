// js/menu_dropdown.js * Verze: V4 * Aktualizace: 17.2.2026
'use strict';

/*
 * menu_dropdown.js
 * - render a obsluha dropdown menu
 *
 * Změny chování:
 * 1) klik na U2 => hned zavře dropdown
 * 2) klik na U1 s U2:
 *    - desktop priorita: ignorovat klik (otevírá jen hoverem)
 *    - touch zatím neřešíme (ignoruje se také)
 *
 * V4:
 * - úklid opakované logiky (U1/U2 stavy), bez ukládání pomocných dat na DOM prvky
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  function getU1Els(rootEl) {
    return rootEl ? Array.from(rootEl.querySelectorAll('.menu-u1')) : [];
  }

  function setU1State(l1, isOpen) {
    if (!l1) return;
    if (isOpen) {
      l1.classList.add('open');
      l1.classList.add('active');
    } else {
      l1.classList.remove('open');
      l1.classList.remove('active');
    }
  }

  function clearU2Active(containerEl) {
    if (!containerEl) return;
    containerEl.querySelectorAll('.menu-u2btn').forEach((b) => b.classList.remove('active'));
  }

  function closeOne(l1, col2) {
    setU1State(l1, false);
    clearU2Active(col2);
  }

  function closeAll(rootEl) {
    getU1Els(rootEl).forEach((l1) => {
      setU1State(l1, false);
      const panel = l1.querySelector('.menu-panel');
      if (panel) {
        clearU2Active(panel);
      }
    });
  }

  function renderDropdown(rootEl, opts) {
    const MENU = (CB_MENU.safeMenu) ? CB_MENU.safeMenu() : [];
    const closeDelay = opts.closeDelay;

    rootEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'menu-u1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.innerHTML =
        '<span>' + sec.label + '</span>' +
        ((CB_MENU.hasL2 && CB_MENU.hasL2(sec)) ? '<span class="chev">▾</span>' : '');

      l1.appendChild(l1btn);

      const hasL2 = (CB_MENU.hasL2 && CB_MENU.hasL2(sec));

      let col2 = null;
      let closeTimer = null;

      if (hasL2) {
        const panel = document.createElement('div');
        panel.className = 'menu-panel';

        col2 = document.createElement('div');
        col2.className = 'menu-col2';
        panel.appendChild(col2);

        sec.level2.forEach((g) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'menu-u2btn';
          b.textContent = g.label;
          col2.appendChild(b);

          b.addEventListener('mouseenter', () => {
            if (closeTimer) closeTimer.cancel();
            clearU2Active(col2);
            b.classList.add('active');
          });

          b.addEventListener('click', (e) => {
            e.stopPropagation();
            // 1) po výběru U2 hned zavřít
            closeOne(l1, col2);
            CB_MENU.goPage(g.page);
          });
        });

        l1.appendChild(panel);
      }

      closeTimer = CB_MENU.makeCloseTimer(() => {
        closeOne(l1, col2);
      }, closeDelay);

      function openThis() {
        closeAll(rootEl);
        if (!hasL2) return;
        setU1State(l1, true);
        clearU2Active(col2);
      }

      l1.addEventListener('mouseenter', () => {
        closeTimer.cancel();
        openThis();
      });

      l1.addEventListener('mouseleave', () => {
        if (!hasL2) return;
        closeTimer.schedule();
      });

      if (hasL2 && col2) {
        col2.addEventListener('mouseenter', () => closeTimer.cancel());
        col2.addEventListener('mouseleave', () => closeTimer.schedule());
      }

      l1btn.addEventListener('click', (e) => {
        e.stopPropagation();

        // L1 bez L2 zůstává klikací (navigace)
        if (!hasL2) {
          closeAll(rootEl);
          CB_MENU.goPage(sec.page);
          return;
        }

        // 2) U1 s U2: klik ignorovat vždy (otevírá se jen hoverem)
        e.preventDefault();
        return;
      });

      rootEl.appendChild(l1);
    });

    return {
      closeAll: () => closeAll(rootEl)
    };
  }

  CB_MENU.initDropdown = function initDropdown(rootEl, opts) {
    if (!rootEl) return;
    const o = (opts && typeof opts === 'object') ? opts : {};
    const closeDelay = Number.isFinite(o.closeDelay) ? o.closeDelay : 180;

    const api = renderDropdown(rootEl, { closeDelay: closeDelay });

    CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_DROPDOWN_WIRED__',
      closeAll: api.closeAll,
      ignoreSelector: '.menu-dropdown'
    });
  };

})(window);

// js/menu_dropdown.js * Verze: V4 * Aktualizace: 17.2.2026 * počet řádků: 177
// konec souboru