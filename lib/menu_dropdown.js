// lib/menu_dropdown.js * Verze: V2 * Aktualizace: 13.2.2026
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
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  function closeAllDropdown(rootEl) {
    Array.from(rootEl.querySelectorAll('.menu-u1')).forEach((l1) => {
      l1.classList.remove('open');
      l1.classList.remove('active');
      const panel = l1.querySelector('.menu-panel');
      if (panel) {
        Array.from(panel.querySelectorAll('.menu-u2btn')).forEach(b => b.classList.remove('active'));
      }
    });
  }

  function closeThisDropdown(l1, col2) {
    l1.classList.remove('open');
    l1.classList.remove('active');
    if (col2) {
      Array.from(col2.querySelectorAll('.menu-u2btn')).forEach(x => x.classList.remove('active'));
    }
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

      const panel = document.createElement('div');
      panel.className = 'menu-panel';

      const col2 = document.createElement('div');
      col2.className = 'menu-col2';
      panel.appendChild(col2);

      const hasL2 = (CB_MENU.hasL2 && CB_MENU.hasL2(sec));

      if (hasL2) {
        sec.level2.forEach((g) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'menu-u2btn';
          b.textContent = g.label;
          col2.appendChild(b);

          const timerRef = { timer: null };

          b.addEventListener('mouseenter', () => {
            if (timerRef.timer) timerRef.timer.cancel();
            Array.from(col2.querySelectorAll('.menu-u2btn')).forEach(x => x.classList.remove('active'));
            b.classList.add('active');
          });

          b.addEventListener('click', (e) => {
            e.stopPropagation();
            // 1) po výběru U2 hned zavřít
            closeThisDropdown(l1, col2);
            CB_MENU.goPage(g.page);
          });

          // uložíme timerRef až po vytvoření timeru (níže)
          b.__cbTimerRef = timerRef;
        });

        l1.appendChild(panel);
      }

      const timer = CB_MENU.makeCloseTimer(() => {
        closeThisDropdown(l1, col2);
      }, closeDelay);

      // doplníme timerRef do U2 tlačítek
      if (hasL2) {
        Array.from(col2.querySelectorAll('.menu-u2btn')).forEach((b) => {
          if (b.__cbTimerRef) b.__cbTimerRef.timer = timer;
        });
      }

      function openThis() {
        closeAllDropdown(rootEl);
        if (!hasL2) return;
        l1.classList.add('open');
        l1.classList.add('active');
        Array.from(col2.querySelectorAll('.menu-u2btn')).forEach(x => x.classList.remove('active'));
      }

      l1.addEventListener('mouseenter', () => {
        timer.cancel();
        openThis();
      });

      l1.addEventListener('mouseleave', () => {
        if (!hasL2) return;
        timer.schedule();
      });

      if (hasL2) {
        col2.addEventListener('mouseenter', () => timer.cancel());
        col2.addEventListener('mouseleave', () => timer.schedule());
      }

      l1btn.addEventListener('click', (e) => {
        e.stopPropagation();

        // L1 bez L2 zůstává klikací (navigace)
        if (!hasL2) {
          closeAllDropdown(rootEl);
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
      closeAll: () => closeAllDropdown(rootEl)
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

// lib/menu_dropdown.js * Verze: V2 * počet řádků 178 * Aktualizace: 13.2.2026
// konec souboru