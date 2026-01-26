<?php
// includes/menu_s.php
// Verze: V9 – počet řádků: 336 – aktuální čas v ČR: 26.1.2026 01:10
declare(strict_types=1);

if (defined('COMEBACK_MENU_S_RENDERED')) {
    return;
}
define('COMEBACK_MENU_S_RENDERED', true);

/*
 * Sidebar menu (3 úrovně) – IS INCLUDE verze
 * - bez <html>, <head>, <body>
 * - CSS se načítá globálně v includes/hlavicka.php: style/menu.css
 * - Data jsou v: lib/menu_data.js
 * - Tento soubor renderuje jen menu + JS logiku
 * - Přepínač režimu: uloží volbu do URL parametru "menu" (dropdown|sidebar) a stránku reloadne
 *
 * ZMĚNA V6:
 * - 2. úroveň (L2) se otevírá doprava jako overlay (podobně jako L3)
 * - 3. úroveň (L3) se otevírá při přejetí myší po L2 (mouseenter) + stále funguje i klik
 *
 * ZMĚNA V7:
 * - L3 se vždy otevírá doprava až za celý L2 panel (nezachází do něj).
 * - Přidaná mezera (GAP) mezi panely pro “profi” vzhled.
 *
 * ZMĚNA V8:
 * - Zarovnání: první položka L2 začíná ve stejné výšce jako horní hrana tlačítka L1
 *   (kompenzace paddingu panelu, aby obsah nezačínal níž).
 *
 * ZMĚNA V9:
 * - L3 se znovu zarovnává podle aktivní (hover/click) položky L2 (klesá/stoupá),
 *   ale pořád kompenzuje padding L3 panelu, aby první položka L3 seděla přesně na výšku položky L2.
 */

?>

<div class="cb-menu cb-menu--sidebar">
    <div class="cb-sidebar-area">
        <div id="sidebar"></div>

        <!-- přepínač režimu (ikona, vlevo dole) -->
        <div class="cb-menu-switch">
            <button type="button" class="cb-menu-btn" id="cbMenuToDropdown" aria-label="Přepnout na dropdown">
                <img src="<?= h(cb_url('img/icons/dropdown.svg')) ?>" alt="">
            </button>
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
(function () {
  const MENU = window.MENU || [];

  const sidebarEl = document.getElementById('sidebar');
  if (!sidebarEl) return;

  const btnToDropdown = document.getElementById('cbMenuToDropdown');

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

  if(btnToDropdown){
    btnToDropdown.addEventListener('click', (e) => {
      e.preventDefault();
      setMenuModeInUrl('dropdown');
    });
  }

  // mezera mezi panely (L1->L2 a L2->L3)
  const GAP = 8;

  function getPadTop(el){
    const v = parseFloat(getComputedStyle(el).paddingTop);
    return Number.isFinite(v) ? v : 0;
  }

  // Overlay panel pro blok 2 (L2) – otevře se doprava od L1
  let l2Overlay = document.getElementById('comeback-l2overlay');
  if (!l2Overlay) {
    l2Overlay = document.createElement('div');
    l2Overlay.id = 'comeback-l2overlay';
    l2Overlay.className = 'sb-l2panel';
    l2Overlay.style.position = 'fixed';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
    l2Overlay.style.zIndex = '998';
    document.body.appendChild(l2Overlay);
  }

  // Overlay panel pro blok 3 (L3) – aby se nikdy neořezal (overflow)
  let l3Overlay = document.getElementById('comeback-l3overlay');
  if (!l3Overlay) {
    l3Overlay = document.createElement('div');
    l3Overlay.id = 'comeback-l3overlay';
    l3Overlay.className = 'dd-l3panel';
    l3Overlay.style.position = 'fixed';
    l3Overlay.style.left = '0';
    l3Overlay.style.top = '0';
    l3Overlay.style.zIndex = '999';
    document.body.appendChild(l3Overlay);
  }

  function emitChosen(l1, l2, l3){
    document.dispatchEvent(new CustomEvent('comeback:menuChosen', {
      detail: { l1, l2, l3 }
    }));
  }

  function closeSidebarL3(){
    l3Overlay.classList.remove('open');
    l3Overlay.innerHTML = '';
    l3Overlay.style.left = '0';
    l3Overlay.style.top = '0';
  }

  function closeSidebarL2(){
    l2Overlay.classList.remove('open');
    l2Overlay.innerHTML = '';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
    closeSidebarL3();
  }

  function closeAllSidebarL1(exceptEl){
    const l1 = Array.from(sidebarEl.querySelectorAll('.sb-l1'));
    l1.forEach(x => {
      if(x !== exceptEl){
        x.classList.remove('open');
        x.classList.remove('active');
      }
    });
    closeSidebarL2();
  }

  function clearL2Active(){
    const btns = Array.from(l2Overlay.querySelectorAll('.sb-l2 > button'));
    btns.forEach(b => b.classList.remove('active'));
  }

  function openSidebarL3(anchorBtnEl, secLabel, gLabel, items){
    const anchorRect = anchorBtnEl.getBoundingClientRect();

    l3Overlay.innerHTML = '';
    items.forEach(itemName => {
      const it = document.createElement('button');
      it.type = 'button';
      it.className = 'dd-l3';
      it.textContent = itemName;

      it.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        emitChosen(secLabel, gLabel, itemName);
        closeAllSidebarL1(); // po volbě zavřít vše (jako u dropdownu)
      });

      it.addEventListener('mousedown', (ev) => ev.preventDefault());
      it.addEventListener('pointerdown', (ev) => ev.preventDefault());

      l3Overlay.appendChild(it);
    });

    // vždy doprava až za celý L2 panel + mezera (GAP)
    const l2r = l2Overlay.getBoundingClientRect();
    const left = Math.round(l2r.right + GAP);

    // L3 má klesat podle aktivní položky L2, ale kompenzovat padding panelu
    const padTopL3 = getPadTop(l3Overlay);
    const top = Math.round(anchorRect.top - padTopL3);

    l3Overlay.style.left = left + 'px';
    l3Overlay.style.top  = top + 'px';
    l3Overlay.classList.add('open');

    // korekce mimo obrazovku (vpravo / dole)
    requestAnimationFrame(() => {
      const r = l3Overlay.getBoundingClientRect();
      let nx = r.left, ny = r.top;

      if(r.right > window.innerWidth - 6){
        nx = Math.max(6, window.innerWidth - r.width - 6);
      }
      if(r.bottom > window.innerHeight - 6){
        ny = Math.max(6, window.innerHeight - r.height - 6);
      }

      l3Overlay.style.left = Math.round(nx) + 'px';
      l3Overlay.style.top  = Math.round(ny) + 'px';
    });
  }

  function openSidebarL2(anchorBtnEl, sec){
    const anchorRect = anchorBtnEl.getBoundingClientRect();

    l2Overlay.innerHTML = '';

    sec.level2.forEach((g) => {
      const wrap = document.createElement('div');
      wrap.className = 'sb-l2';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = g.label;

      // hover: otevřít L3 bez kliknutí
      btn.addEventListener('mouseenter', () => {
        clearL2Active();
        btn.classList.add('active');
        openSidebarL3(btn, sec.label, g.label, g.level3);
      });

      // klik: stále funguje (když někdo nechce/nezvládne hover)
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        clearL2Active();
        btn.classList.add('active');
        openSidebarL3(btn, sec.label, g.label, g.level3);
        btn.blur();
      });

      // při držení myši zabránit focus/outline
      btn.addEventListener('mousedown', (e) => e.preventDefault());
      btn.addEventListener('pointerdown', (e) => e.preventDefault());

      wrap.appendChild(btn);
      l2Overlay.appendChild(wrap);
    });

    const left = Math.round(anchorRect.right + GAP);

    // L2: první položka má začínat ve stejné výšce jako L1 → kompenzace padding-top panelu
    const padTopL2 = getPadTop(l2Overlay);
    const top = Math.round(anchorRect.top - padTopL2);

    l2Overlay.style.left = left + 'px';
    l2Overlay.style.top  = top + 'px';
    l2Overlay.classList.add('open');

    // korekce mimo obrazovku (vpravo / dole)
    requestAnimationFrame(() => {
      const r = l2Overlay.getBoundingClientRect();
      let nx = r.left, ny = r.top;

      if(r.right > window.innerWidth - 6){
        nx = Math.max(6, window.innerWidth - r.width - 6);
      }
      if(r.bottom > window.innerHeight - 6){
        ny = Math.max(6, window.innerHeight - r.height - 6);
      }

      l2Overlay.style.left = Math.round(nx) + 'px';
      l2Overlay.style.top  = Math.round(ny) + 'px';
    });
  }

  function renderSidebar(){
    sidebarEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'sb-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.textContent = sec.label;

      // zabránit zobrazení focus/outline při držení myši na 1. úrovni
      l1btn.addEventListener('mousedown', (e) => e.preventDefault());
      l1btn.addEventListener('pointerdown', (e) => e.preventDefault());

      l1btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const willOpen = !l1.classList.contains('open');

        closeAllSidebarL1(l1);

        if(willOpen){
          l1.classList.add('open');
          l1.classList.add('active');
          openSidebarL2(l1btn, sec);
        }else{
          l1.classList.remove('open');
          l1.classList.remove('active');
          closeSidebarL2();
        }

        l1btn.blur();
      });

      l1.appendChild(l1btn);
      sidebarEl.appendChild(l1);
    });
  }

  function wireGlobalCloseOnce(){
    if(window.__COMEBACK_SIDEBAR_WIRED__) return;
    window.__COMEBACK_SIDEBAR_WIRED__ = true;

    document.addEventListener('click', () => closeAllSidebarL1());
    document.addEventListener('keydown', (e) => {
      if(e.key === 'Escape') closeAllSidebarL1();
    });
    window.addEventListener('resize', () => closeAllSidebarL1());
    window.addEventListener('scroll', () => closeAllSidebarL1(), true);
  }

  renderSidebar();
  wireGlobalCloseOnce();
})();
</script>

<?php
/* includes/menu_s.php V9 – počet řádků: 336 – aktuální čas v ČR: 26.1.2026 01:10 */
?>
