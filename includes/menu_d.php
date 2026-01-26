<?php
// includes/menu_d.php
// Verze: V6 – počet řádků: 254 – aktuální čas v ČR: 26.1.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) {
    return;
}
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (3 úrovně) – IS INCLUDE verze
 * - bez <html>, <head>, <body>
 * - CSS se načítá globálně v includes/hlavicka.php: style/menu.css
 * - Data jsou v: lib/menu_data.js
 * - Tento soubor renderuje jen menu + JS logiku
 * - Přepínač režimu: uloží volbu do URL parametru "menu" (dropdown|sidebar) a stránku reloadne
 */
?>

<div class="cb-menu cb-menu--dropdown">
    <div class="cb-dropdown-bar">
        <div class="cb-menu-top">
            <div class="dd-row" id="dropdown"></div>

            <!-- přepínač režimu (ikona, vpravo nahoře) -->
            <div class="cb-menu-switch">
                <button type="button" class="cb-menu-btn" id="cbMenuToSidebar" aria-label="Přepnout na sidebar">
                    <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// načíst menu data jen jednou (když bude současně existovat dropdown i sidebar volba)
if (!defined('COMEBACK_MENU_DATA_JS_INCLUDED')) {
    define('COMEBACK_MENU_DATA_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_data.js')) ?>"></script>
    <?php
}
?>

<script>
(function(){
  const MENU = window.MENU || [];

  const dropdownEl = document.getElementById('dropdown');
  if(!dropdownEl) return;

  const btnToSidebar = document.getElementById('cbMenuToSidebar');

  function setMenuModeInUrl(mode){
    try{
      const u = new URL(window.location.href);
      u.searchParams.set('menu', mode); // dropdown|sidebar
      window.location.href = u.toString();
    }catch(e){
      // fallback (velmi staré prohlížeče)
      window.location.search = '?menu=' + encodeURIComponent(mode);
    }
  }

  if(btnToSidebar){
    btnToSidebar.addEventListener('click', (e) => {
      e.preventDefault();
      setMenuModeInUrl('sidebar');
    });
  }

  function emitChosen(l1, l2, l3){
    document.dispatchEvent(new CustomEvent('comeback:menuChosen', {
      detail: { l1, l2, l3 }
    }));
  }

  function clearDropdownL2Active(panelEl){
    const btns = Array.from(panelEl.querySelectorAll('.dd-l2btn'));
    btns.forEach(b => b.classList.remove('active'));
  }

  function closeDropdownL3(panelEl){
    const l3p = panelEl.querySelector('.dd-l3panel');
    if(!l3p) return;
    l3p.classList.remove('open');
    l3p.innerHTML = '';
    l3p.style.top = '';
  }

  function closeAllDropdownL1(exceptEl){
    const l1 = Array.from(dropdownEl.querySelectorAll('.dd-l1'));
    l1.forEach(x => {
      if(x !== exceptEl){
        x.classList.remove('open');
        x.classList.remove('active');
        const p = x.querySelector('.dd-panel');
        if(p){
          clearDropdownL2Active(p);
          closeDropdownL3(p);
        }
      }
    });
  }

  function openDropdownL3(panelEl, anchorBtnEl, secLabel, gLabel, items){
    const l3p = panelEl.querySelector('.dd-l3panel');
    if(!l3p) return;

    // Zarovnat 1. položku bloku 3 na stejnou výšku jako zvolenou položku v bloku 2
    const panelRect = panelEl.getBoundingClientRect();
    const anchorRect = anchorBtnEl.getBoundingClientRect();
    const anchorTopInPanel = anchorRect.top - panelRect.top;

    const padTop = parseFloat(getComputedStyle(l3p).paddingTop) || 0;
    const top = Math.max(0, Math.round(anchorTopInPanel - padTop));
    l3p.style.top = top + 'px';

    l3p.innerHTML = '';
    items.forEach(itemName => {
      const it = document.createElement('button');
      it.type = 'button';
      it.className = 'dd-l3';
      it.textContent = itemName;

      it.addEventListener('click', (ev) => {
        ev.stopPropagation();
        emitChosen(secLabel, gLabel, itemName);
        closeAllDropdownL1();
      });

      l3p.appendChild(it);
    });

    l3p.classList.add('open');
  }

  function renderDropdown(){
    dropdownEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'dd-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.innerHTML = '<span>' + sec.label + '</span><span class="chev">▾</span>';

      const panel = document.createElement('div');
      panel.className = 'dd-panel';

      const col2 = document.createElement('div');
      col2.className = 'dd-col2';

      // 3. úroveň je uvnitř panelu (vpravo od 2. úrovně)
      const l3panel = document.createElement('div');
      l3panel.className = 'dd-l3panel';

      let hoverCloseTimer = null;
      function cancelHoverClose(){
        if(hoverCloseTimer){
          clearTimeout(hoverCloseTimer);
          hoverCloseTimer = null;
        }
      }
      function scheduleHoverClose(){
        cancelHoverClose();
        hoverCloseTimer = setTimeout(() => {
          clearDropdownL2Active(panel);
          closeDropdownL3(panel);
        }, 180);
      }

      // když přejedu myší do L3 panelu, nesmí se zavřít
      l3panel.addEventListener('mouseenter', cancelHoverClose);
      l3panel.addEventListener('mouseleave', scheduleHoverClose);

      sec.level2.forEach((g) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'dd-l2btn';
        b.textContent = g.label;

        // 3. úroveň se otevírá už přejetím myší (hover)
        b.addEventListener('mouseenter', () => {
          if(!l1.classList.contains('open')) return;
          cancelHoverClose();
          clearDropdownL2Active(panel);
          b.classList.add('active');
          openDropdownL3(panel, b, sec.label, g.label, g.level3);
        });

        // ponecháme i click (pro dotyk/uživatele bez myši)
        b.addEventListener('click', (e) => {
          e.stopPropagation();
          clearDropdownL2Active(panel);
          b.classList.add('active');
          openDropdownL3(panel, b, sec.label, g.label, g.level3);
        });

        col2.appendChild(b);
      });

      // pohyb myši v oblasti 2. úrovně: ruší zavírání
      col2.addEventListener('mouseenter', cancelHoverClose);
      col2.addEventListener('mouseleave', scheduleHoverClose);

      panel.appendChild(col2);
      panel.appendChild(l3panel);

      l1btn.addEventListener('click', (e) => {
        e.stopPropagation();

        const willOpen = !l1.classList.contains('open');
        closeAllDropdownL1(l1);

        if(willOpen){
          l1.classList.add('open');
          l1.classList.add('active');
          clearDropdownL2Active(panel);
          closeDropdownL3(panel);
        }else{
          l1.classList.remove('open');
          l1.classList.remove('active');
          clearDropdownL2Active(panel);
          closeDropdownL3(panel);
        }
      });

      l1.appendChild(l1btn);
      l1.appendChild(panel);
      dropdownEl.appendChild(l1);
    });
  }

  function wireGlobalCloseOnce(){
    if(window.__COMEBACK_DROPDOWN_WIRED__) return;
    window.__COMEBACK_DROPDOWN_WIRED__ = true;

    document.addEventListener('click', () => closeAllDropdownL1());
    document.addEventListener('keydown', (e) => {
      if(e.key === 'Escape') closeAllDropdownL1();
    });
  }

  renderDropdown();
  wireGlobalCloseOnce();
})();
</script>

<?php
/* includes/menu_d.php V6 – počet řádků: 254 – aktuální čas v ČR: 26.1.2026 */
?>
