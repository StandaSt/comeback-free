<?php
// pages/admin_ukazka_card.php * Verze: V3 * Aktualizace: 28.2.2026
declare(strict_types=1);

/*
 * Ukázky UI (jen vzhled)
 * - seskupené bloky (#01–#35)
 * - bez JS; prvky nemusí nic dělat
 * V3:
 * - odstraněn globální zásah do html/body (žádné přepisování overflow/height)
 * - odstraněn vnější echo '<div class="card">' wrapper (nezasahuje do systémového layoutu)
 */

$cards = [];

/* ===== KPI a přehledy ===== */

$cards[] = [
  'w' => 'w-50',
  'n' => '#01',
  't' => 'Denní tržba',
  's' => 'KPI + mini graf',
  'b' => <<<HTML
<div class="kpi3">
  <div class="kpi"><div class="muted sm">Dnes</div><div class="xl fw9">128&nbsp;540 Kč</div></div>
  <div class="kpi"><div class="muted sm">Včera</div><div class="lg fw9">119&nbsp;120 Kč</div></div>
  <div class="kpi"><div class="muted sm">Cíl</div><div class="lg fw9">140&nbsp;000 Kč</div></div>
</div>
<div class="bars" aria-hidden="true">
  <span style="height:26%"></span><span style="height:42%"></span><span style="height:36%"></span><span style="height:50%"></span><span style="height:60%"></span>
  <span style="height:46%"></span><span style="height:64%"></span><span class="hot" style="height:70%"></span><span style="height:54%"></span><span style="height:62%"></span>
</div>
<div class="row row-s">
  <span class="bdg ok">+8,4%</span>
  <span class="bdg soft">průměr 7 dní</span>
  <span class="bdg warn">špička 12:40</span>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#02',
  't' => 'Stav provozu',
  's' => 'semafor + štítky',
  'b' => <<<HTML
<div class="traffic">
  <div class="light ok"><div class="muted sm">Sklad</div><div class="lg fw9">OK</div></div>
  <div class="light warn"><div class="muted sm">Doručení</div><div class="lg fw9">Pozor</div></div>
  <div class="light bad"><div class="muted sm">Reklamace</div><div class="lg fw9">Řešit</div></div>
</div>
<div class="row">
  <span class="pill ok">SLA 95%</span>
  <span class="pill warn">zpoždění 18 min</span>
  <span class="pill bad">chyb 7</span>
</div>
<div class="callout">
  <div class="md fw9">Poznámka</div>
  <div class="sm muted">Krátký komentář k dnešku – co hlídat a proč.</div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#03',
  't' => 'Teploměr cíle',
  's' => 'progress',
  'b' => <<<HTML
<div class="thermo">
  <div class="thermo-col"><div class="thermo-fill" style="height:72%"></div></div>
  <div class="thermo-k">
    <div class="muted sm">Plnění</div>
    <div class="xl fw9">72%</div>
    <div class="muted sm">Zbývá</div>
    <div class="lg fw9">39&nbsp;460 Kč</div>
    <div class="row row-s"><span class="bdg ok">OK</span><span class="bdg soft">tempo drží</span></div>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#04',
  't' => 'Gauge',
  's' => 'půlkruh',
  'b' => <<<HTML
<div class="gauge" style="--p:78">
  <div class="gauge-in">
    <div class="xl fw9">78%</div>
    <div class="sm muted">Kuchyň</div>
  </div>
</div>
<div class="row row-s"><span class="pill soft">limit 85%</span><span class="pill warn">špička</span></div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#05',
  't' => 'Donut – podíl',
  's' => 'poměr',
  'b' => <<<HTML
<div class="donut" style="--p:64">
  <div class="donut-in">
    <div class="xl fw9">64%</div>
    <div class="sm muted">Rozvoz</div>
  </div>
</div>
<div class="legend">
  <div><i class="dot a"></i><span class="sm">Rozvoz</span><span class="sm fw9">64%</span></div>
  <div><i class="dot b"></i><span class="sm">Osobně</span><span class="sm fw9">36%</span></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-100',
  'n' => '#06',
  't' => 'Rychlý přehled (dlaždice)',
  's' => 'kompaktní',
  'b' => <<<HTML
<div class="tiles">
  <div class="tile"><div class="muted sm">Objednávky</div><div class="xl fw9">203</div><div class="mini">+12</div></div>
  <div class="tile"><div class="muted sm">Průměr</div><div class="xl fw9">521 Kč</div><div class="mini ok">OK</div></div>
  <div class="tile"><div class="muted sm">Zpoždění</div><div class="xl fw9">18 min</div><div class="mini warn">pozor</div></div>
  <div class="tile"><div class="muted sm">Chyby</div><div class="xl fw9">3</div><div class="mini bad">!</div></div>
  <div class="tile"><div class="muted sm">Sklad</div><div class="xl fw9">OK</div><div class="mini">6/6</div></div>
  <div class="tile"><div class="muted sm">Rezervace</div><div class="xl fw9">11</div><div class="mini">dnes</div></div>
</div>
HTML
];

/* ===== Tabulky ===== */

$cards[] = [
  'w' => 'w-100',
  'n' => '#07',
  't' => 'Tabulka – přehled objednávek',
  's' => 'zebra + štítky',
  'b' => <<<HTML
<div class="toolbar">
  <div class="fake-input"><span class="ico">⌕</span><span class="sm muted">Hledat…</span></div>
  <div class="chips">
    <span class="chip on">Dnes</span><span class="chip">Týden</span><span class="chip">Měsíc</span><span class="chip">Vše</span>
  </div>
  <div class="right"><span class="pill soft">Filtr</span><span class="pill">Export</span></div>
</div>

<div class="tbl-wrap">
  <table class="tbl zebra">
    <thead>
    <tr>
      <th>Čas <span class="sort">▴</span></th>
      <th>Typ</th>
      <th class="num">Hodnota</th>
      <th>Stav</th>
      <th>Kurýr</th>
      <th>Pozn.</th>
    </tr>
    </thead>
    <tbody>
    <tr><td>11:05</td><td>Rozvoz</td><td class="num">640</td><td><span class="bdg ok">OK</span></td><td>Novák</td><td class="muted">hotově</td></tr>
    <tr><td>12:18</td><td>Osobně</td><td class="num">410</td><td><span class="bdg ok">OK</span></td><td>—</td><td class="muted">karta</td></tr>
    <tr><td>13:42</td><td>Rozvoz</td><td class="num">980</td><td><span class="bdg warn">Čeká</span></td><td>Svoboda</td><td class="muted">kuchyň</td></tr>
    <tr><td>14:09</td><td>Rozvoz</td><td class="num">720</td><td><span class="bdg bad">Problém</span></td><td>Novák</td><td class="muted">ověřit</td></tr>
    <tr><td>15:12</td><td>Osobně</td><td class="num">560</td><td><span class="bdg ok">OK</span></td><td>—</td><td class="muted">rychlé</td></tr>
    <tr><td>16:01</td><td>Rozvoz</td><td class="num">1&nbsp;120</td><td><span class="bdg warn">V běhu</span></td><td>Černý</td><td class="muted">okraj</td></tr>
    </tbody>
  </table>
</div>

<div class="footrow">
  <div class="sm muted">Zobrazeno 6 řádků</div>
  <div class="pager"><span class="pg on">1</span><span class="pg">2</span><span class="pg">3</span><span class="pg">…</span><span class="pg">12</span></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#08',
  't' => 'Tabulka – kompaktní',
  's' => 'husté řádky',
  'b' => <<<HTML
<div class="tbl-wrap tight">
  <table class="tbl">
    <thead>
    <tr><th>Pobočka</th><th class="num">Tržba</th><th class="num">Obj.</th><th>Trend</th></tr>
    </thead>
    <tbody>
    <tr><td>Centrum</td><td class="num">128&nbsp;540</td><td class="num">203</td><td><span class="bdg ok">↗</span></td></tr>
    <tr><td>Sever</td><td class="num">92&nbsp;100</td><td class="num">148</td><td><span class="bdg ok">↗</span></td></tr>
    <tr><td>Jih</td><td class="num">75&nbsp;300</td><td class="num">121</td><td><span class="bdg soft">→</span></td></tr>
    <tr><td>Západ</td><td class="num">63&nbsp;900</td><td class="num">97</td><td><span class="bdg warn">↘</span></td></tr>
    <tr><td>Východ</td><td class="num">59&nbsp;200</td><td class="num">88</td><td><span class="bdg bad">↘</span></td></tr>
    </tbody>
  </table>
</div>
<div class="row row-s"><span class="pill soft">sort: tržba</span><span class="pill">filtr: aktivní</span></div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#09',
  't' => 'Tabulka – sticky hlavička',
  's' => 'vnitřní okno',
  'b' => <<<HTML
<div class="window">
  <div class="window-h"><span class="sm fw9">Log akcí</span><span class="bdg soft">posledních 30</span></div>
  <div class="window-b">
    <table class="tbl zebra sticky">
      <thead><tr><th>Čas</th><th>Akce</th><th>Uživ.</th><th>Stav</th></tr></thead>
      <tbody>
      <tr><td>08:10</td><td>Otevření směny</td><td>Admin</td><td><span class="bdg ok">OK</span></td></tr>
      <tr><td>09:02</td><td>Import objednávek</td><td>Job</td><td><span class="bdg ok">OK</span></td></tr>
      <tr><td>09:40</td><td>API volání</td><td>Job</td><td><span class="bdg warn">Pomalu</span></td></tr>
      <tr><td>10:11</td><td>Uložení změn</td><td>Admin</td><td><span class="bdg ok">OK</span></td></tr>
      <tr><td>11:08</td><td>Reindex</td><td>Job</td><td><span class="bdg warn">Čeká</span></td></tr>
      <tr><td>12:03</td><td>Chybějící položka</td><td>Job</td><td><span class="bdg bad">Chyba</span></td></tr>
      <tr><td>12:06</td><td>Retry</td><td>Job</td><td><span class="bdg ok">OK</span></td></tr>
      </tbody>
    </table>
  </div>
</div>
HTML
];

/* ===== Grafy / vizualizace ===== */

$cards[] = [
  'w' => 'w-100',
  'n' => '#10',
  't' => '3 mini grafy v řádku',
  's' => 'dashboard blok',
  'b' => <<<HTML
<div class="pan3">
  <div class="pan">
    <div class="pan-h"><div class="md fw9">Tržba</div><div class="sm muted">7 dní</div></div>
    <div class="sparkbar" aria-hidden="true">
      <span style="height:22%"></span><span style="height:44%"></span><span style="height:38%"></span><span style="height:52%"></span><span style="height:61%"></span><span class="hot" style="height:49%"></span>
    </div>
    <div class="pan-f"><div class="lg fw9">128k</div><span class="bdg ok">+6%</span></div>
  </div>

  <div class="pan">
    <div class="pan-h"><div class="md fw9">Objednávky</div><div class="sm muted">dnes</div></div>
    <div class="sparkbar" aria-hidden="true">
      <span style="height:18%"></span><span style="height:26%"></span><span style="height:40%"></span><span style="height:34%"></span><span style="height:46%"></span><span class="hot" style="height:52%"></span>
    </div>
    <div class="pan-f"><div class="lg fw9">203</div><span class="bdg warn">špička</span></div>
  </div>

  <div class="pan">
    <div class="pan-h"><div class="md fw9">SLA</div><div class="sm muted">týden</div></div>
    <div class="sparkbar" aria-hidden="true">
      <span style="height:55%"></span><span style="height:58%"></span><span style="height:57%"></span><span style="height:60%"></span><span style="height:62%"></span><span class="hot" style="height:61%"></span>
    </div>
    <div class="pan-f"><div class="lg fw9">95%</div><span class="bdg ok">OK</span></div>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#11',
  't' => 'Heatmapa',
  's' => 'aktivita',
  'b' => <<<HTML
<div class="heat">
  <div class="heat-r"><span class="c s0"></span><span class="c s1"></span><span class="c s2"></span><span class="c s3"></span><span class="c s1"></span><span class="c s2"></span><span class="c s0"></span></div>
  <div class="heat-r"><span class="c s1"></span><span class="c s2"></span><span class="c s2"></span><span class="c s1"></span><span class="c s3"></span><span class="c s2"></span><span class="c s1"></span></div>
  <div class="heat-r"><span class="c s0"></span><span class="c s1"></span><span class="c s3"></span><span class="c s2"></span><span class="c s2"></span><span class="c s1"></span><span class="c s0"></span></div>
  <div class="heat-r"><span class="c s1"></span><span class="c s1"></span><span class="c s2"></span><span class="c s3"></span><span class="c s1"></span><span class="c s2"></span><span class="c s1"></span></div>
  <div class="heat-r"><span class="c s2"></span><span class="c s2"></span><span class="c s1"></span><span class="c s0"></span><span class="c s1"></span><span class="c s3"></span><span class="c s2"></span></div>
  <div class="heat-r"><span class="c s0"></span><span class="c s1"></span><span class="c s0"></span><span class="c s1"></span><span class="c s2"></span><span class="c s1"></span><span class="c s0"></span></div>
</div>
<div class="row row-s"><span class="bdg soft">nižší</span><span class="bdg ok">vyšší</span><span class="bdg warn">špička</span></div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#12',
  't' => 'Čárový mini graf',
  's' => 'sparklines',
  'b' => <<<HTML
<div class="sparks">
  <div class="spark">
    <div class="spark-h"><span class="sm muted">7 dní</span><span class="bdg ok">+8%</span></div>
    <svg viewBox="0 0 120 32" class="spark-svg" aria-hidden="true"><polyline points="0,22 15,20 30,24 45,16 60,18 75,10 90,14 105,8 120,12" /></svg>
    <div class="spark-f"><span class="lg fw9">+8,4%</span><span class="sm muted">trend</span></div>
  </div>

  <div class="spark">
    <div class="spark-h"><span class="sm muted">30 dní</span><span class="bdg warn">kolísá</span></div>
    <svg viewBox="0 0 120 32" class="spark-svg" aria-hidden="true"><polyline points="0,18 15,22 30,20 45,24 60,18 75,16 90,20 105,14 120,10" /></svg>
    <div class="spark-f"><span class="lg fw9">+2,1%</span><span class="sm muted">trend</span></div>
  </div>
</div>
HTML
];

/* ===== Filtry a formuláře ===== */

$cards[] = [
  'w' => 'w-50',
  'n' => '#13',
  't' => 'Panel filtrů',
  's' => '2 sloupce',
  'b' => <<<HTML
<div class="form">
  <div class="field"><div class="lbl">Pobočka</div><div class="ctrl select">Centrum ▾</div></div>
  <div class="field"><div class="lbl">Stav</div><div class="ctrl select">Vše ▾</div></div>
  <div class="field"><div class="lbl">Datum od</div><div class="ctrl">28.02.2026</div></div>
  <div class="field"><div class="lbl">Datum do</div><div class="ctrl">28.02.2026</div></div>
  <div class="field span2"><div class="lbl">Vyhledat</div><div class="ctrl">např. #12458, telefon, poznámka…</div></div>
</div>
<div class="row"><button class="btn primary" type="button">Použít</button><button class="btn" type="button">Reset</button><span class="pill soft">uložit filtr</span></div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#14',
  't' => 'Rychlé filtry',
  's' => 'chips + přepínače',
  'b' => <<<HTML
<div class="row">
  <span class="chip on">Dnes</span><span class="chip">Včera</span><span class="chip">Týden</span><span class="chip">Měsíc</span><span class="chip">Rok</span>
</div>
<div class="switches">
  <div class="sw on"><span class="d"></span><span>jen aktivní</span></div>
  <div class="sw"><span class="d"></span><span>bez storen</span></div>
  <div class="sw on"><span class="d"></span><span>jen rozvoz</span></div>
  <div class="sw"><span class="d"></span><span>problémy</span></div>
</div>
<div class="row row-s"><span class="pill soft">sort: čas</span><span class="pill">sklad: zapnout</span><span class="pill warn">pozor: SLA</span></div>
HTML
];

/* ===== Ouška ===== */

$cards[] = [
  'w' => 'w-100',
  'n' => '#15',
  't' => 'Ouška – 3 sekce',
  's' => 'bez JS (radio)',
  'b' => <<<HTML
<div class="tabs">
  <input type="radio" name="t1" id="t1a" checked>
  <input type="radio" name="t1" id="t1b">
  <input type="radio" name="t1" id="t1c">

  <div class="tabbar">
    <label class="tab" for="t1a">Přehled</label>
    <label class="tab" for="t1b">Detail</label>
    <label class="tab" for="t1c">Historie</label>
  </div>

  <div class="panes">
    <section class="pane a">
      <div class="tiles small">
        <div class="tile"><div class="muted sm">Fronta</div><div class="xl fw9">12</div><div class="mini warn">min</div></div>
        <div class="tile"><div class="muted sm">SLA</div><div class="xl fw9">95%</div><div class="mini ok">OK</div></div>
        <div class="tile"><div class="muted sm">Chyby</div><div class="xl fw9">3</div><div class="mini bad">!</div></div>
        <div class="tile"><div class="muted sm">Online</div><div class="xl fw9">6/6</div><div class="mini">—</div></div>
      </div>
    </section>

    <section class="pane b">
      <div class="split2">
        <div class="panel">
          <div class="md fw9">Poznámky</div>
          <ul class="ul">
            <li>Na „Jih“ hlídat zpoždění.</li>
            <li>Kontrola skladových položek.</li>
            <li>Po 18:00 snížit limity na rozvoz.</li>
          </ul>
        </div>
        <div class="panel soft">
          <div class="md fw9">Rychlé akce</div>
          <div class="row"><button class="btn primary" type="button">Otevřít log</button><button class="btn" type="button">Znovu načíst</button><button class="btn" type="button">Export</button></div>
        </div>
      </div>
    </section>

    <section class="pane c">
      <div class="timeline">
        <div class="tr"><span class="tm">08:10</span><span class="tx">Otevřeno</span><span class="bdg ok">OK</span></div>
        <div class="tr"><span class="tm">11:32</span><span class="tx">Špička</span><span class="bdg warn">SLA</span></div>
        <div class="tr"><span class="tm">14:05</span><span class="tx">Krátký výpadek</span><span class="bdg bad">ERR</span></div>
        <div class="tr"><span class="tm">16:20</span><span class="tx">Naskladnění</span><span class="bdg soft">OK</span></div>
      </div>
    </section>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#16',
  't' => 'Ouška – 5 položek',
  's' => 'kompaktní',
  'b' => <<<HTML
<div class="tabbar compact">
  <span class="tab on">Denní</span><span class="tab">Týden</span><span class="tab">Měsíc</span><span class="tab">Rok</span><span class="tab">Vše</span>
</div>
<div class="panel">
  <div class="row row-s"><span class="pill ok">OK</span><span class="pill warn">Pozor</span><span class="pill bad">Řešit</span></div>
  <div class="sm muted">Pod oušky bude vždy obsah. Tady je jen ukázka hustoty a stylu.</div>
  <div class="bars slim" aria-hidden="true">
    <span style="height:35%"></span><span style="height:52%"></span><span style="height:40%"></span><span class="hot" style="height:68%"></span>
    <span style="height:44%"></span><span style="height:56%"></span><span style="height:48%"></span><span style="height:61%"></span>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#17',
  't' => 'Ouška – „adresáře“',
  's' => 'výraznější akcent',
  'b' => <<<HTML
<div class="tabbar accent">
  <span class="tab on">Sklad</span><span class="tab">Doprava</span><span class="tab">Finance</span>
</div>
<div class="panel accent">
  <div class="row row-s"><span class="bdg ok">Zelené</span><span class="bdg warn">Oranžové</span><span class="bdg bad">Červené</span></div>
  <div class="sm muted">Tady je prostor pro obsah – seznam položek, tabulka nebo graf.</div>
  <div class="list">
    <div class="li"><span class="k">Mozzarella</span><span class="v">18 ks</span></div>
    <div class="li"><span class="k">Šunka</span><span class="v">6 ks</span></div>
    <div class="li"><span class="k">Těsto</span><span class="v">24 ks</span></div>
  </div>
</div>
HTML
];

/* ===== Menu varianty ===== */

$cards[] = [
  'w' => 'w-100',
  'n' => '#18',
  't' => 'Menu – segmented',
  's' => 'spojené prvky',
  'b' => <<<HTML
<div class="menu seg">
  <span class="m on">Přehled</span><span class="m">Objednávky</span><span class="m">Sklad</span><span class="m">HR</span><span class="m">Admin</span>
</div>
<div class="note"><div class="sm muted">Pod menu může být cokoli: tabulka, graf, filtr.</div></div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#19',
  't' => 'Menu – underline',
  's' => 'aktivní podtržení',
  'b' => <<<HTML
<div class="menu under">
  <span class="m on">Denní</span><span class="m">Týden</span><span class="m">Měsíc</span><span class="m">Rok</span>
</div>
<div class="panel"><div class="row row-s"><span class="pill soft">výběr období</span><span class="pill">filtr pobočky</span></div><div class="sm muted">Tady bude obsah k vybrané položce.</div></div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#20',
  't' => 'Menu – pill',
  's' => 'sytější akcent',
  'b' => <<<HTML
<div class="menu pill">
  <span class="m on">Top</span><span class="m">Reporty</span><span class="m">Porovnání</span><span class="m">Sklady</span><span class="m">Nastavení</span>
</div>
<div class="callout soft"><div class="md fw9">Ukázka</div><div class="sm muted">Menu může být i jen „řádek tlačítek“ uprostřed stránky.</div></div>
HTML
];

/* ===== Speciální bloky ===== */

$cards[] = [
  'w' => 'w-50',
  'n' => '#21',
  't' => 'Kanban (mini)',
  's' => '3 sloupce',
  'b' => <<<HTML
<div class="kanban">
  <div class="col">
    <div class="h">Nové</div>
    <div class="kc"><div class="t">Chybí položka</div><div class="s muted">Sklad</div></div>
    <div class="kc"><div class="t">Zpoždění</div><div class="s muted">Doručení</div></div>
  </div>
  <div class="col">
    <div class="h">V řešení</div>
    <div class="kc warn"><div class="t">API pomalé</div><div class="s muted">Restia</div></div>
    <div class="kc"><div class="t">Kontrola cen</div><div class="s muted">Menu</div></div>
  </div>
  <div class="col">
    <div class="h">Hotovo</div>
    <div class="kc ok"><div class="t">Import</div><div class="s muted">OK</div></div>
    <div class="kc ok"><div class="t">Sync</div><div class="s muted">OK</div></div>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#22',
  't' => 'Strom (navigace)',
  's' => 'hierarchie',
  'b' => <<<HTML
<div class="tree">
  <div class="node on"><span class="k">Admin</span><span class="v muted">5</span></div>
  <div class="ch">
    <div class="node"><span class="k">Chyby</span><span class="v muted">3</span></div>
    <div class="node"><span class="k">Logy</span><span class="v muted">120</span></div>
    <div class="node on"><span class="k">Uživatelé</span><span class="v muted">14</span></div>
    <div class="ch">
      <div class="node"><span class="k">Role</span><span class="v muted">7</span></div>
      <div class="node"><span class="k">Pobočky</span><span class="v muted">6</span></div>
      <div class="node"><span class="k">Práva</span><span class="v muted">28</span></div>
    </div>
    <div class="node"><span class="k">Nastavení</span><span class="v muted">—</span></div>
  </div>
</div>
<div class="row row-s"><span class="pill soft">klik – jen vzhled</span></div>
HTML
];

$cards[] = [
  'w' => 'w-100',
  'n' => '#23',
  't' => 'Mřížka práv',
  's' => 'role × oblasti',
  'b' => <<<HTML
<div class="matrix">
  <div class="mr head"><div></div><div>Admin</div><div>HR</div><div>Objednávky</div><div>Sklad</div><div>Reporty</div></div>
  <div class="mr"><div class="who">Správce</div><div class="cell on">✓</div><div class="cell on">✓</div><div class="cell on">✓</div><div class="cell on">✓</div><div class="cell on">✓</div></div>
  <div class="mr"><div class="who">Vedoucí</div><div class="cell">—</div><div class="cell on">✓</div><div class="cell on">✓</div><div class="cell on">✓</div><div class="cell on">✓</div></div>
  <div class="mr"><div class="who">Pokladna</div><div class="cell">—</div><div class="cell">—</div><div class="cell on">✓</div><div class="cell">—</div><div class="cell on">✓</div></div>
  <div class="mr"><div class="who">Skladník</div><div class="cell">—</div><div class="cell">—</div><div class="cell">—</div><div class="cell on">✓</div><div class="cell">—</div></div>
</div>
<div class="note"><div class="sm muted">Hodí se na přehled práv/rolí. Reálně pak může být editovatelná.</div></div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#24',
  't' => 'Informační karta',
  's' => 'klíč/hodnota',
  'b' => <<<HTML
<div class="info">
  <div class="ir"><span class="k">Server</span><span class="v fw9">ASUSTUF</span></div>
  <div class="ir"><span class="k">DB</span><span class="v fw9">comeback</span></div>
  <div class="ir"><span class="k">Dotazů</span><span class="v fw9">652</span></div>
  <div class="ir"><span class="k">Paměť</span><span class="v fw9">826 MB</span></div>
</div>
<div class="row row-s"><span class="bdg soft">info</span><span class="bdg ok">OK</span></div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#25',
  't' => 'Notifikace',
  's' => '3 typy',
  'b' => <<<HTML
<div class="msg info"><div class="tt">INFO</div><div class="bb">Synchronizace proběhla. Zapsáno 120 řádků.</div></div>
<div class="msg warn"><div class="tt">POZOR</div><div class="bb">Průměr doručení přesáhl 40 min na „Jih“.</div></div>
<div class="msg bad"><div class="tt">CHYBA</div><div class="bb">Timeout při volání API. Doporučeno zkontrolovat stav.</div></div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#26',
  't' => 'Žebříček',
  's' => 'top 5',
  'b' => <<<HTML
<ol class="rank">
  <li><span class="t">Centrum</span><span class="v">128k</span></li>
  <li><span class="t">Sever</span><span class="v">92k</span></li>
  <li><span class="t">Jih</span><span class="v">75k</span></li>
  <li><span class="t">Západ</span><span class="v">64k</span></li>
  <li><span class="t">Východ</span><span class="v">59k</span></li>
</ol>
HTML
];

$cards[] = [
  'w' => 'w-100',
  'n' => '#27',
  't' => 'Karta se dvěma sloupci',
  's' => 'víc obsahu',
  'b' => <<<HTML
<div class="split2">
  <div class="panel">
    <div class="md fw9">Poznámky k dnešku</div>
    <ul class="ul">
      <li>Po 12:00 posílit rozvoz (vyšší zatížení).</li>
      <li>Kontrola nejprodávanějších položek a dostupnosti.</li>
      <li>Včas uzavřít reklamace a doplnit důvody do poznámek.</li>
      <li>Po 18:00 zkontrolovat průměr a SLA.</li>
    </ul>
    <div class="row row-s"><span class="pill soft">pobočka: Centrum</span><span class="pill">směna: 1</span></div>
  </div>

  <div class="panel soft">
    <div class="md fw9">Rychlý checklist</div>
    <div class="checks">
      <div class="ck on"><span class="bx"></span><span>Sklad zkontrolován</span></div>
      <div class="ck on"><span class="bx"></span><span>Kurýři online</span></div>
      <div class="ck"><span class="bx"></span><span>Vytisknout rekapitulaci</span></div>
      <div class="ck"><span class="bx"></span><span>Uzávěrka pokladny</span></div>
    </div>
    <div class="row"><button class="btn primary" type="button">Otevřít</button><button class="btn" type="button">Zavřít</button></div>
  </div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#28',
  't' => 'Karta „statistiky“',
  's' => 'meter + text',
  'b' => <<<HTML
<div class="meters">
  <div class="m"><div class="mh"><span>CPU</span><span class="fw9">46%</span></div><div class="mb"><i style="width:46%"></i></div></div>
  <div class="m"><div class="mh"><span>RAM</span><span class="fw9">71%</span></div><div class="mb"><i class="warn" style="width:71%"></i></div></div>
  <div class="m"><div class="mh"><span>Disk</span><span class="fw9">88%</span></div><div class="mb"><i class="bad" style="width:88%"></i></div></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#29',
  't' => 'Karta „inbox“',
  's' => 'seznam zpráv',
  'b' => <<<HTML
<div class="inbox">
  <div class="im on"><div class="ih"><span class="fw9">Sklad</span><span class="bdg warn">nové</span></div><div class="ib muted">Chybí 2 položky • doplnit do 14:00</div></div>
  <div class="im"><div class="ih"><span class="fw9">Objednávky</span><span class="bdg soft">info</span></div><div class="ib muted">Výrazně vyšší špička mezi 12–13 h</div></div>
  <div class="im"><div class="ih"><span class="fw9">HR</span><span class="bdg ok">OK</span></div><div class="ib muted">Docházka uzavřena</div></div>
  <div class="im"><div class="ih"><span class="fw9">Admin</span><span class="bdg bad">chyba</span></div><div class="ib muted">Timeout u API • prověřit log</div></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-100',
  'n' => '#30',
  't' => 'Rozšířená tabulka s „hlavičkou filtru“',
  's' => 'filtr/sort jen vzhled',
  'b' => <<<HTML
<div class="filterrow">
  <div class="f"><span class="lbl">Typ</span><span class="ctrl select">Vše ▾</span></div>
  <div class="f"><span class="lbl">Stav</span><span class="ctrl select">Aktivní ▾</span></div>
  <div class="f"><span class="lbl">Sort</span><span class="ctrl select">Čas ▾</span></div>
  <div class="f grow"><span class="lbl">Hledat</span><span class="ctrl">číslo, poznámka…</span></div>
  <div class="f"><span class="pill soft">Použít</span></div>
</div>

<div class="tbl-wrap">
  <table class="tbl zebra">
    <thead><tr><th>ID</th><th>Pobočka</th><th>Kanál</th><th class="num">Hodnota</th><th>Stav</th><th>Vytvořeno</th></tr></thead>
    <tbody>
    <tr><td>#12841</td><td>Centrum</td><td>Web</td><td class="num">980</td><td><span class="bdg ok">OK</span></td><td>11:05</td></tr>
    <tr><td>#12842</td><td>Centrum</td><td>Telefon</td><td class="num">640</td><td><span class="bdg warn">Čeká</span></td><td>11:12</td></tr>
    <tr><td>#12843</td><td>Sever</td><td>Web</td><td class="num">410</td><td><span class="bdg ok">OK</span></td><td>11:18</td></tr>
    <tr><td>#12844</td><td>Jih</td><td>Web</td><td class="num">1&nbsp;120</td><td><span class="bdg warn">V běhu</span></td><td>11:26</td></tr>
    <tr><td>#12845</td><td>Západ</td><td>Telefon</td><td class="num">720</td><td><span class="bdg bad">Problém</span></td><td>11:31</td></tr>
    <tr><td>#12846</td><td>Východ</td><td>Web</td><td class="num">560</td><td><span class="bdg ok">OK</span></td><td>11:38</td></tr>
    <tr><td>#12847</td><td>Centrum</td><td>Osobně</td><td class="num">380</td><td><span class="bdg soft">—</span></td><td>11:44</td></tr>
    <tr><td>#12848</td><td>Sever</td><td>Web</td><td class="num">910</td><td><span class="bdg ok">OK</span></td><td>11:49</td></tr>
    </tbody>
  </table>
</div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#31',
  't' => 'Stavy tlačítek',
  's' => 'jednotné třídy',
  'b' => <<<HTML
<div class="row">
  <button class="btn primary" type="button">Primární</button>
  <button class="btn" type="button">Normální</button>
  <button class="btn ghost" type="button">Ghost</button>
  <button class="btn" type="button" disabled>Disabled</button>
</div>
<div class="row row-s"><span class="pill soft">malé</span><span class="pill">střední</span><span class="pill warn">pozor</span></div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#32',
  't' => 'Tagy',
  's' => 'rychlé označení',
  'b' => <<<HTML
<div class="tags">
  <span class="tag ok">OK</span><span class="tag warn">Pozor</span><span class="tag bad">Chyba</span><span class="tag soft">Info</span>
  <span class="tag">Nové</span><span class="tag">Hotovo</span><span class="tag">V běhu</span><span class="tag">VIP</span>
</div>
<div class="sm muted" style="padding:0 10px 10px 10px;">Použitelné v tabulkách i v kartách.</div>
HTML
];

$cards[] = [
  'w' => 'w-33',
  'n' => '#33',
  't' => 'Progress list',
  's' => 'víc obsahu',
  'b' => <<<HTML
<div class="plist">
  <div class="pr"><div class="ph"><span class="fw9">Import objednávek</span><span class="muted">12:02</span></div><div class="pbar"><i style="width:100%"></i></div></div>
  <div class="pr"><div class="ph"><span class="fw9">Synchronizace DB</span><span class="muted">12:10</span></div><div class="pbar"><i class="ok" style="width:78%"></i></div></div>
  <div class="pr"><div class="ph"><span class="fw9">Zpracování skladu</span><span class="muted">12:18</span></div><div class="pbar"><i class="warn" style="width:52%"></i></div></div>
  <div class="pr"><div class="ph"><span class="fw9">Kontrola chyb</span><span class="muted">12:24</span></div><div class="pbar"><i class="bad" style="width:30%"></i></div></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#34',
  't' => 'Časová osa',
  's' => 'timeline',
  'b' => <<<HTML
<div class="timeline">
  <div class="tr"><span class="tm">08:10</span><span class="tx">Otevření</span><span class="bdg ok">OK</span></div>
  <div class="tr"><span class="tm">09:02</span><span class="tx">Import</span><span class="bdg ok">OK</span></div>
  <div class="tr"><span class="tm">11:32</span><span class="tx">Špička</span><span class="bdg warn">SLA</span></div>
  <div class="tr"><span class="tm">14:05</span><span class="tx">Výpadek</span><span class="bdg bad">ERR</span></div>
  <div class="tr"><span class="tm">16:20</span><span class="tx">Naskladnění</span><span class="bdg soft">OK</span></div>
</div>
HTML
];

$cards[] = [
  'w' => 'w-50',
  'n' => '#35',
  't' => 'Rychlý souhrn',
  's' => 'kompaktní',
  'b' => <<<HTML
<div class="sum">
  <div class="sum-l">
    <div class="muted sm">Pobočka</div><div class="lg fw9">Centrum</div>
    <div class="muted sm">Směna</div><div class="lg fw9">1</div>
  </div>
  <div class="sum-r">
    <div class="row row-s"><span class="bdg ok">OK</span><span class="bdg warn">SLA</span><span class="bdg soft">info</span></div>
    <div class="sm muted">Krátký text k souhrnu. Tady můžou být 2–3 věty, aby karta nebyla prázdná.</div>
    <div class="row"><button class="btn primary" type="button">Detail</button><button class="btn" type="button">Akce</button></div>
  </div>
</div>
HTML
];

?>
<link rel="stylesheet" href="style/1/pages/admin_ukazka.css">

<section class="auk-root">
  <header class="head">
    <div>
      <h2 class="h1">Ukázky UI – bloky</h2>
      <div class="sub muted sm">Seskupeno podle typu. Vše jen vzhled.</div>
    </div>
    <div class="head-r">
      <span class="pill soft">#01–#35</span>
      <span class="pill">Admin</span>
    </div>
  </header>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">KPI a přehledy</div>
      <div class="sec-s muted sm">dlaždice, semafory, stavové štítky</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 0, 6) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Tabulky</div>
      <div class="sec-s muted sm">varianty, pruhování, hlavičky</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 6, 3) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Grafy a vizualizace</div>
      <div class="sec-s muted sm">mini grafy bez knihoven</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 9, 3) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Filtry a formuláře</div>
      <div class="sec-s muted sm">jen vzhled</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 12, 2) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Ouška</div>
      <div class="sec-s muted sm">přepínací vzhled</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 14, 3) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Menu varianty</div>
      <div class="sec-s muted sm">různé styly</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 17, 3) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sec">
    <div class="sec-h">
      <div class="sec-t">Speciální bloky</div>
      <div class="sec-s muted sm">věci pro admin</div>
    </div>
    <div class="grid">
      <?php foreach (array_slice($cards, 20) as $c): ?>
        <article class="card <?= htmlspecialchars($c['w']) ?>">
          <header class="card-h">
            <div class="numtag"><?= htmlspecialchars($c['n']) ?></div>
            <div class="titles">
              <div class="tt"><?= htmlspecialchars($c['t']) ?></div>
              <div class="ts muted sm"><?= htmlspecialchars($c['s']) ?></div>
            </div>
            <div class="actions">
              <button class="ic" type="button" aria-label="Zavřít">×</button>
              <button class="ic" type="button" aria-label="Maximalizovat">⤢</button>
              <button class="ic" type="button" aria-label="Detail">⋯</button>
            </div>
          </header>
          <?= $c['b'] ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

  <footer class="end">
    <div class="sm muted">© 2026 Comeback</div>
    <div class="sm muted">verze 0.1 (test)</div>
  </footer>
</section>

<?php
/* pages/admin_ukazka_card.php * Verze: V3 * Aktualizace: 28.2.2026 * Konec souboru */