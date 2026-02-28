<?php
// pages/a_dashboard_engine_demo.php
declare(strict_types=1);
?>
<section class="card">

<!--
  DASHBOARD ENGINE DEMO (maketa, ale klikací)
  - drag&drop bloků (přetahování za hlavičku)
  - minimalizace do spodní lišty, skrytí, sbalení, maximalizace
  - volba šířky bloku (1/4, 1/3, 1/2, 2/3, 3/4, 1/1) – jen pro demo
  - uložení layoutu do localStorage (pořadí + šířky + stavy)
-->

<style>
/* ===== základ ===== */
.db-wrap{display:flex; flex-direction:column; gap:10px;}
.db-topbar{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  background:rgba(255,255,255,.75);
  border:1px solid rgba(0,0,0,.10);
  border-radius:10px;
  padding:10px;
}
.db-topbar-left{display:flex; gap:8px; align-items:center; flex-wrap:wrap;}
.db-topbar-right{display:flex; gap:8px; align-items:center; flex-wrap:wrap;}
.db-btn{
  border:1px solid rgba(0,0,0,.14);
  background:#fff;
  border-radius:10px;
  padding:6px 10px;
  font-size:12px;
  cursor:pointer;
}
.db-btn:hover{background:rgba(15,23,42,.04);}
.db-pill{
  display:inline-flex; align-items:center; gap:6px;
  border:1px solid rgba(0,0,0,.12);
  background:#fff;
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
}
.dot{width:8px;height:8px;border-radius:999px;background:#3f3092;display:inline-block;}

/* ===== grid ===== */
.db-grid{
  display:grid;
  grid-template-columns:repeat(12,1fr);
  gap:14px;
  align-items:start;
}

/* ===== blok ===== */
.db-block{
  background:#fff;
  border:1px solid rgba(0,0,0,.12);
  border-radius:12px;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  min-height:120px;
  box-shadow:0 10px 24px rgba(0,0,0,.06);
}
.db-block.dragging{opacity:.55; outline:2px dashed rgba(63,48,146,.55);}
.db-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  padding:10px 10px 9px 10px;
  border-bottom:1px solid rgba(0,0,0,.08);
  background:linear-gradient(to bottom, rgba(63,48,146,.08), rgba(63,48,146,.02));
  cursor:grab;
  user-select:none;
}
.db-head:active{cursor:grabbing;}
.db-title{
  display:flex; gap:10px; align-items:baseline; min-width:0;
}
.db-no{color:#7b7b7b; font-weight:700;}
.db-name{font-weight:800; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:14px;}
.db-sub{color:rgba(15,23,42,.68); font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}

/* akce */
.db-actions{display:flex; gap:6px; align-items:center; flex:0 0 auto;}
.db-ic{
  width:30px; height:30px;
  border-radius:9px;
  border:1px solid rgba(0,0,0,.14);
  background:#fff;
  font-size:16px;
  line-height:28px;
  text-align:center;
  cursor:pointer;
  padding:0;
}
.db-ic:hover{background:rgba(15,23,42,.04);}
.db-ic.more{font-size:18px; line-height:26px;}
.db-ic.danger{color:#b91c1c; border-color:rgba(185,28,28,.35); background:rgba(185,28,28,.06);}
.db-ic.danger:hover{background:rgba(185,28,28,.10);}

/* tělo */
.db-body{padding:12px; font-size:13px; color:#111827;}
.db-body p{margin:0 0 10px 0;}
.muted{color:rgba(15,23,42,.70);}
.kpi{
  display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;
}
.kpi .val{font-size:22px; font-weight:900;}
.badge{
  display:inline-flex; align-items:center; gap:6px;
  border:1px solid rgba(0,0,0,.12);
  background:#fff;
  border-radius:999px;
  padding:4px 10px;
  font-size:12px;
}
.badge.ok{background:rgba(16,185,129,.10); border-color:rgba(16,185,129,.22);}
.badge.var{background:rgba(245,158,11,.10); border-color:rgba(245,158,11,.24);}
.badge.prob{background:rgba(239,68,68,.10); border-color:rgba(239,68,68,.24);}
.badge .b{width:8px;height:8px;border-radius:999px;background:#999;}
.badge.ok .b{background:#10b981;}
.badge.var .b{background:#f59e0b;}
.badge.prob .b{background:#ef4444;}

/* widths */
.w-1-4{grid-column:span 3;}
.w-1-3{grid-column:span 4;}
.w-1-2{grid-column:span 6;}
.w-2-3{grid-column:span 8;}
.w-3-4{grid-column:span 9;}
.w-1-1{grid-column:span 12;}

/* stavy */
.collapsed .db-body{display:none;}
.hidden{display:none;}
.maximized{
  position:fixed !important;
  inset:40px;
  z-index:9999;
  box-shadow:0 26px 80px rgba(0,0,0,.35);
}
.maximized .db-body{overflow:auto;}

/* mini bar */
.db-minbar{
  position:fixed;
  left:50%;
  transform:translateX(-50%);
  bottom:12px;
  z-index:99999;
  display:flex;
  gap:8px;
  align-items:center;
  padding:8px 10px;
  border-radius:999px;
  background:rgba(15,23,42,.92);
  border:1px solid rgba(255,255,255,.12);
  box-shadow:0 12px 34px rgba(0,0,0,.30);
}
.db-minbar .cap{color:rgba(255,255,255,.78); font-size:12px; padding-right:6px; border-right:1px solid rgba(255,255,255,.18);}
.db-minbar button{
  border:1px solid rgba(255,255,255,.18);
  background:rgba(255,255,255,.06);
  color:#fff;
  border-radius:999px;
  padding:6px 10px;
  font-size:12px;
  cursor:pointer;
}
.db-minbar button:hover{background:rgba(255,255,255,.10);}

/* table demos */
.twrap{border:1px solid rgba(0,0,0,.10); border-radius:10px; overflow:hidden;}
.twrap .thead{background:rgba(15,23,42,.04); border-bottom:1px solid rgba(0,0,0,.08); padding:8px 10px; font-weight:700;}
.tscroll{max-height:160px; overflow:auto;}
table.tbl{width:100%; border-collapse:collapse; font-size:12px;}
.tbl th,.tbl td{padding:8px 10px; border-bottom:1px solid rgba(0,0,0,.06); text-align:left; white-space:nowrap;}
.tbl tr:nth-child(even) td{background:rgba(15,23,42,.02);}
.tbl th{position:sticky; top:0; background:#fff; z-index:1; border-bottom:1px solid rgba(0,0,0,.10);}
.right{text-align:right;}

/* chart demos */
.spark{display:flex; align-items:flex-end; gap:3px; height:42px;}
.spark i{display:block; width:8px; border-radius:4px; background:rgba(63,48,146,.65);}
.line{height:54px; border-radius:10px; background:linear-gradient(135deg, rgba(63,48,146,.35), rgba(63,48,146,.05)); position:relative; overflow:hidden; border:1px solid rgba(0,0,0,.10);}
.line:before{
  content:"";
  position:absolute; inset:10px;
  background:
    radial-gradient(circle at 6% 70%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 18% 52%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 32% 62%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 48% 38%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 60% 44%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 76% 28%, rgba(63,48,146,.90) 0 2px, transparent 3px),
    radial-gradient(circle at 92% 36%, rgba(63,48,146,.90) 0 2px, transparent 3px);
  opacity:.85;
}
.donut{
  width:92px;height:92px;border-radius:50%;
  background:conic-gradient(#3f3092 var(--deg), rgba(15,23,42,.10) 0);
  display:grid; place-items:center;
  margin-right:12px;
}
.donut .in{
  width:62px;height:62px;border-radius:50%;
  background:#fff; border:1px solid rgba(0,0,0,.08);
  display:grid; place-items:center;
  font-weight:900; font-size:18px;
}
.heat{display:grid; grid-template-columns:repeat(12, 1fr); gap:6px;}
.heat b{display:block; height:16px; border-radius:6px; background:rgba(63,48,146,.08); border:1px solid rgba(0,0,0,.08);}
.heat b.lv1{background:rgba(63,48,146,.14);}
.heat b.lv2{background:rgba(63,48,146,.24);}
.heat b.lv3{background:rgba(63,48,146,.36);}
.heat b.lv4{background:rgba(63,48,146,.52);}
/* kanban */
.kanban{display:grid; grid-template-columns:repeat(3,1fr); gap:10px;}
.kcol{border:1px solid rgba(0,0,0,.10); border-radius:10px; overflow:hidden;}
.khead{padding:8px 10px; font-weight:800; background:rgba(15,23,42,.04); border-bottom:1px solid rgba(0,0,0,.08);}
.kcard{padding:8px 10px; border-bottom:1px solid rgba(0,0,0,.06); font-size:12px;}
.kcard:last-child{border-bottom:none;}
/* tree */
.tree{font-size:12px; line-height:1.35;}
.tree ul{margin:6px 0 0 16px; padding:0;}
.tree li{margin:4px 0;}
/* rights matrix */
.matrix{overflow:auto; border:1px solid rgba(0,0,0,.10); border-radius:10px;}
.matrix table{border-collapse:collapse; width:100%; font-size:12px;}
.matrix th,.matrix td{border-bottom:1px solid rgba(0,0,0,.06); padding:8px 10px; white-space:nowrap;}
.matrix th{position:sticky; top:0; background:#fff; z-index:1;}
.chk{display:inline-grid; place-items:center; width:18px; height:18px; border-radius:6px; border:1px solid rgba(0,0,0,.18); background:#fff;}
.chk.on{background:rgba(16,185,129,.14); border-color:rgba(16,185,129,.30);}
/* inbox */
.inbox{display:grid; gap:8px;}
.msg{border:1px solid rgba(0,0,0,.10); border-radius:10px; padding:8px 10px; background:#fff;}
.msg .h{display:flex; justify-content:space-between; gap:8px; font-weight:800; font-size:12px;}
.msg .t{margin-top:4px; font-size:12px; color:rgba(15,23,42,.75);}
/* progress list */
.plist{display:grid; gap:10px;}
.pitem{display:grid; grid-template-columns:140px 1fr 44px; gap:10px; align-items:center;}
.pbar{height:10px; border-radius:999px; background:rgba(15,23,42,.10); overflow:hidden;}
.pbar > i{display:block; height:100%; width:var(--p); background:rgba(63,48,146,.75);}
/* filters */
.filters{display:grid; grid-template-columns:1fr 1fr; gap:10px;}
.f{display:grid; gap:6px;}
.f label{font-size:12px; color:rgba(15,23,42,.70);}
.f input,.f select{
  border:1px solid rgba(0,0,0,.14);
  border-radius:10px;
  padding:8px 10px;
  font-size:13px;
  background:#fff;
}
.chips{display:flex; gap:8px; flex-wrap:wrap;}
.chip{border:1px solid rgba(0,0,0,.14); background:#fff; border-radius:999px; padding:6px 10px; font-size:12px;}
.chip.on{background:rgba(63,48,146,.10); border-color:rgba(63,48,146,.26);}
/* tiny */
.hr{height:1px;background:rgba(0,0,0,.08); margin:10px 0;}
</style>

<div class="db-wrap">

  <!-- MENU DEMO (jen maketa) -->
  <div class="db-topbar">
    <div class="db-topbar-left">
      <span class="db-pill"><span class="dot"></span> Dashboard engine</span>
      <button class="db-btn" type="button" onclick="resetLayout()">Reset layout</button>
      <button class="db-btn" type="button" onclick="toggleAllCollapsed()">Sbalit/rozbalit vše</button>
      <button class="db-btn" type="button" onclick="showAll()">Zobrazit vše</button>
    </div>
    <div class="db-topbar-right">
      <span class="db-pill">Drag & drop: <strong>za hlavičku</strong></span>
      <span class="db-pill">Ukládá se do <strong>localStorage</strong></span>
    </div>
  </div>

  <div class="db-grid" id="grid">

    <!-- #01 KPI (1/4) -->
    <div class="db-block w-1-4" data-id="b01" data-w="w-1-4" data-title="KPI Prodeje">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#01</span>
          <span class="db-name">KPI Prodeje</span>
          <span class="db-sub">dnes vs včera</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" title="Sbalit" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" title="Maximalizovat" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" title="Šířka" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" title="Skrýt" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="kpi">
          <div class="val">1 240 000 Kč</div>
          <span class="badge ok"><span class="b"></span> OK</span>
          <span class="badge"><span class="b" style="background:#3f3092"></span> +12%</span>
        </div>
        <div class="muted" style="margin-top:6px;">Průměr objednávky: 438 Kč</div>
      </div>
    </div>

    <!-- #02 KPI (1/4) -->
    <div class="db-block w-1-4" data-id="b02" data-w="w-1-4" data-title="KPI Marže">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#02</span>
          <span class="db-name">KPI Marže</span>
          <span class="db-sub">posledních 7 dní</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="kpi">
          <div class="val">31,6%</div>
          <span class="badge var"><span class="b"></span> VAR</span>
        </div>
        <div class="hr"></div>
        <div class="spark" aria-label="mini histogram">
          <i style="height:12px"></i><i style="height:18px"></i><i style="height:26px"></i><i style="height:34px"></i><i style="height:28px"></i><i style="height:22px"></i><i style="height:30px"></i><i style="height:20px"></i><i style="height:16px"></i>
        </div>
      </div>
    </div>

    <!-- #03 Donut (1/4) -->
    <div class="db-block w-1-4" data-id="b03" data-w="w-1-4" data-title="Vytížení kapacity">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#03</span>
          <span class="db-name">Vytížení kapacity</span>
          <span class="db-sub">práh 75%</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body" style="display:flex; gap:10px; align-items:center;">
        <div class="donut" style="--deg:280deg">
          <div class="in">78%</div>
        </div>
        <div>
          <div style="font-weight:800;">Interpretace</div>
          <div class="muted">nad limitem, ale stabilní</div>
          <div style="margin-top:8px;">
            <span class="badge var"><span class="b"></span> VAR</span>
          </div>
        </div>
      </div>
    </div>

    <!-- #04 Tabulka okno (1/2) -->
    <div class="db-block w-1-2" data-id="b04" data-w="w-1-2" data-title="Objednávky (okno)">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#04</span>
          <span class="db-name">Objednávky</span>
          <span class="db-sub">sticky hlavička + vnitřní scroll</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="twrap">
          <div class="thead">Rychlý výpis (maketa)</div>
          <div class="tscroll">
            <table class="tbl">
              <thead>
                <tr><th>ID</th><th>Pobočka</th><th class="right">Kč</th><th>Stav</th><th>Čas</th></tr>
              </thead>
              <tbody>
                <tr><td>10421</td><td>Bolevec</td><td class="right">450</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>12:41</td></tr>
                <tr><td>10422</td><td>Slovany</td><td class="right">320</td><td><span class="badge var"><span class="b"></span>VAR</span></td><td>12:44</td></tr>
                <tr><td>10423</td><td>Doubravka</td><td class="right">510</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>12:46</td></tr>
                <tr><td>10424</td><td>Bolevec</td><td class="right">280</td><td><span class="badge prob"><span class="b"></span>PROB</span></td><td>12:48</td></tr>
                <tr><td>10425</td><td>Centrum</td><td class="right">390</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>12:52</td></tr>
                <tr><td>10426</td><td>Slovany</td><td class="right">610</td><td><span class="badge var"><span class="b"></span>VAR</span></td><td>12:56</td></tr>
                <tr><td>10427</td><td>Doubravka</td><td class="right">440</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>13:02</td></tr>
                <tr><td>10428</td><td>Centrum</td><td class="right">230</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>13:07</td></tr>
                <tr><td>10429</td><td>Bolevec</td><td class="right">720</td><td><span class="badge prob"><span class="b"></span>PROB</span></td><td>13:10</td></tr>
                <tr><td>10430</td><td>Slovany</td><td class="right">360</td><td><span class="badge ok"><span class="b"></span>OK</span></td><td>13:14</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="muted" style="margin-top:8px;">Možnosti (maketa): řazení, filtr, export, označit, hromadné akce.</div>
      </div>
    </div>

    <!-- #05 Filtry (1/2) -->
    <div class="db-block w-1-2" data-id="b05" data-w="w-1-2" data-title="Panel filtrů">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#05</span>
          <span class="db-name">Panel filtrů</span>
          <span class="db-sub">2 sloupce + chips</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="filters">
          <div class="f">
            <label>Pobočka</label>
            <select><option>Všechny</option><option>Bolevec</option><option>Slovany</option><option>Centrum</option></select>
          </div>
          <div class="f">
            <label>Interval</label>
            <select><option>Dnes</option><option>Včera</option><option>Týden</option><option>Měsíc</option></select>
          </div>
          <div class="f">
            <label>Stav</label>
            <select><option>Vše</option><option>OK</option><option>VAR</option><option>PROB</option></select>
          </div>
          <div class="f">
            <label>Vyhledat</label>
            <input value="" placeholder="ID, zákazník, poznámka">
          </div>
        </div>
        <div class="hr"></div>
        <div class="chips" aria-label="rychlé filtry">
          <span class="chip on">Jen problémové</span>
          <span class="chip">Jen hotové</span>
          <span class="chip">Kurýr</span>
          <span class="chip">Osobní odběr</span>
          <span class="chip">VIP</span>
        </div>
      </div>
    </div>

    <!-- #06 Sparkline line (1/3) -->
    <div class="db-block w-1-3" data-id="b06" data-w="w-1-3" data-title="Trend dodání">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#06</span>
          <span class="db-name">Trend dodání</span>
          <span class="db-sub">mini čára + body</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="kpi">
          <div class="val">31 min</div>
          <span class="badge var"><span class="b"></span> VAR</span>
        </div>
        <div class="line" style="margin-top:10px;"></div>
        <div class="muted" style="margin-top:8px;">Pozn.: bez knihoven, jen CSS.</div>
      </div>
    </div>

    <!-- #07 Histogram (1/3) -->
    <div class="db-block w-1-3" data-id="b07" data-w="w-1-3" data-title="Rozptyl časů">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#07</span>
          <span class="db-name">Rozptyl časů</span>
          <span class="db-sub">mini histogram</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="spark" aria-label="histogram">
          <i style="height:10px"></i><i style="height:14px"></i><i style="height:18px"></i><i style="height:24px"></i><i style="height:32px"></i><i style="height:36px"></i><i style="height:28px"></i><i style="height:20px"></i><i style="height:14px"></i><i style="height:10px"></i>
        </div>
        <div class="muted" style="margin-top:10px;">Použití: SLA, špičky, „špatné dny“.</div>
      </div>
    </div>

    <!-- #08 Heat (2/3) -->
    <div class="db-block w-2-3" data-id="b08" data-w="w-2-3" data-title="Heatmapa aktivity">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#08</span>
          <span class="db-name">Heatmapa aktivity</span>
          <span class="db-sub">12×6 (hodiny × dny)</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <span class="badge ok"><span class="b"></span> OK</span>
          <span class="badge var"><span class="b"></span> VAR</span>
          <span class="badge prob"><span class="b"></span> PROB</span>
          <span class="muted">Tip: klik na buňku = detail hodiny (později).</span>
        </div>
        <div style="margin-top:10px;" class="heat" aria-label="heatmap">
          <!-- 72 buněk -->
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
          <b class="lv4"></b>
          <b class="lv1"></b>
          <b class="lv3"></b>
          <b class=""></b>
          <b class="lv2"></b>
        </div>
      </div>
    </div>

    <!-- #09 Kanban (1/3) -->
    <div class="db-block w-1-3" data-id="b09" data-w="w-1-3" data-title="Kanban úkoly">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#09</span>
          <span class="db-name">Kanban úkoly</span>
          <span class="db-sub">3 sloupce</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="kanban">
          <div class="kcol">
            <div class="khead">Nápady</div>
            <div class="kcard">Sjednotit logy (INFO/WARN/ERROR)</div>
            <div class="kcard">Admin → Chyby (filtry + detail)</div>
          </div>
          <div class="kcol">
            <div class="khead">Dělám</div>
            <div class="kcard">Dashboard bloky + uložení layoutu</div>
            <div class="kcard">Čitelnost tabulek (sticky + okno)</div>
          </div>
          <div class="kcol">
            <div class="khead">Hotovo</div>
            <div class="kcard">Základ stylů + card scroll</div>
            <div class="kcard">Menu režim sidebar/dropdown</div>
          </div>
        </div>
      </div>
    </div>

    <!-- #10 Inbox (1/2) -->
    <div class="db-block w-1-2" data-id="b10" data-w="w-1-2" data-title="Inbox notifikací">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#10</span>
          <span class="db-name">Inbox notifikací</span>
          <span class="db-sub">co se dělo v systému</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="inbox">
          <div class="msg">
            <div class="h"><span>API Restia</span><span class="muted">před 3 min</span></div>
            <div class="t">Načteno 325 záznamů (ok). Uloženo do DB.</div>
          </div>
          <div class="msg">
            <div class="h"><span>Chyba</span><span class="muted">před 12 min</span></div>
            <div class="t">Timeout dotazu (pobočka Slovany). Doporučení: zúžit interval.</div>
          </div>
          <div class="msg">
            <div class="h"><span>Přihlášení</span><span class="muted">dnes 12:01</span></div>
            <div class="t">Schváleno 2FA z mobilu. Session: 20 min.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- #11 Strom (1/2) -->
    <div class="db-block w-1-2" data-id="b11" data-w="w-1-2" data-title="Hierarchie">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#11</span>
          <span class="db-name">Strom (hierarchie)</span>
          <span class="db-sub">moduly → stránky → akce</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="tree">
          <div><strong>Admin</strong></div>
          <ul>
            <li>Chyby
              <ul>
                <li>Detail chyby</li>
                <li>Export</li>
              </ul>
            </li>
            <li>Logy
              <ul>
                <li>Filtr podle modulu</li>
                <li>Agregace po dnech</li>
              </ul>
            </li>
            <li>DB
              <ul>
                <li>Inicializace</li>
                <li>Kontrola integrity</li>
              </ul>
            </li>
          </ul>
        </div>
        <div class="muted" style="margin-top:10px;">Použití: navigace bez velkého menu, rychlá orientace.</div>
      </div>
    </div>

    <!-- #12 Matice práv (1/1) -->
    <div class="db-block w-1-1" data-id="b12" data-w="w-1-1" data-title="Matice práv">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#12</span>
          <span class="db-name">Matice práv</span>
          <span class="db-sub">role × oblasti (maketa)</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="matrix">
          <table>
            <thead>
              <tr>
                <th>Oblast</th>
                <th>Admin</th>
                <th>Manažer</th>
                <th>Vedoucí</th>
                <th>Pokladna</th>
                <th>Kurýr</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Objednávky</td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td></tr>
              <tr><td>Reporty</td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td></tr>
              <tr><td>HR</td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td></tr>
              <tr><td>Admin</td><td><span class="chk on">✓</span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td></tr>
              <tr><td>Export</td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk on">✓</span></td><td><span class="chk"> </span></td><td><span class="chk"> </span></td></tr>
            </tbody>
          </table>
        </div>
        <div class="muted" style="margin-top:10px;">Později: klik = přepnout právo, audit změn, export.</div>
      </div>
    </div>

    <!-- #13 Progress list (1/2) -->
    <div class="db-block w-1-2" data-id="b13" data-w="w-1-2" data-title="Progress list">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#13</span>
          <span class="db-name">Progress list</span>
          <span class="db-sub">stav úloh / importů</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div class="plist">
          <div class="pitem" style="--p:82%"><div>Restia objednávky</div><div class="pbar"><i></i></div><div class="right">82%</div></div>
          <div class="pitem" style="--p:46%"><div>Restia položky</div><div class="pbar"><i></i></div><div class="right">46%</div></div>
          <div class="pitem" style="--p:100%"><div>Směny (GraphQL)</div><div class="pbar"><i></i></div><div class="right">100%</div></div>
        </div>
        <div class="muted" style="margin-top:10px;">Později: možnost zrušit, znovu spustit, detail logu.</div>
      </div>
    </div>

    <!-- #14 Info karta (1/2) -->
    <div class="db-block w-1-2" data-id="b14" data-w="w-1-2" data-title="Info karta">
      <div class="db-head" draggable="true">
        <div class="db-title">
          <span class="db-no">#14</span>
          <span class="db-name">Info karta</span>
          <span class="db-sub">klíč / hodnota</span>
        </div>
        <div class="db-actions">
          <button class="db-ic" type="button" onclick="collapseBtn(this)">–</button>
          <button class="db-ic" type="button" onclick="maximizeBtn(this)">□</button>
          <button class="db-ic more" type="button" onclick="widthMenu(this)">⋯</button>
          <button class="db-ic danger" type="button" onclick="hideBtn(this)">×</button>
        </div>
      </div>
      <div class="db-body">
        <div style="display:grid; grid-template-columns:160px 1fr; gap:6px 14px; font-size:12px;">
          <div class="muted">DB</div><div><strong>127.0.0.1</strong> / comeback</div>
          <div class="muted">Velikost DB</div><div>826 MB</div>
          <div class="muted">Dotazů</div><div>652 145 / 325</div>
          <div class="muted">Poslední sync</div><div>dnes 12:58 (OK)</div>
          <div class="muted">Režim</div><div><span class="badge ok"><span class="b"></span> LOCAL</span></div>
        </div>
      </div>
    </div>

  </div>

</div>

<!-- minimalizační lišta -->
<div class="db-minbar" id="minbar" style="display:none;">
  <span class="cap">Minimalizováno</span>
</div>

<script>
(function(){
  const grid = document.getElementById('grid');
  const minbar = document.getElementById('minbar');
  const KEY = 'cb_demo_dash_v2';

  function qBlocks(){ return Array.from(grid.querySelectorAll('.db-block')); }

  function saveLayout(){
    const data = qBlocks().map(b => ({
      id: b.getAttribute('data-id'),
      w:  b.getAttribute('data-w') || '',
      st: {
        collapsed: b.classList.contains('collapsed'),
        hidden: b.classList.contains('hidden')
      }
    }));
    try { localStorage.setItem(KEY, JSON.stringify(data)); } catch(e){}
  }

  function applyWidth(block, w){
    const widths = ['w-1-4','w-1-3','w-1-2','w-2-3','w-3-4','w-1-1'];
    widths.forEach(c => block.classList.remove(c));
    block.classList.add(w);
    block.setAttribute('data-w', w);
  }

  function loadLayout(){
    let raw = null;
    try { raw = localStorage.getItem(KEY); } catch(e){ raw = null; }
    if (!raw) return;
    let data = null;
    try { data = JSON.parse(raw); } catch(e){ data = null; }
    if (!Array.isArray(data)) return;

    // pořadí
    const map = new Map(qBlocks().map(b => [b.getAttribute('data-id'), b]));
    data.forEach(it => {
      const b = map.get(it.id);
      if (!b) return;
      grid.appendChild(b);
      if (it.w) applyWidth(b, it.w);
      if (it.st && it.st.collapsed) b.classList.add('collapsed'); else b.classList.remove('collapsed');
      if (it.st && it.st.hidden) b.classList.add('hidden'); else b.classList.remove('hidden');
    });

    rebuildMinbar();
  }

  // ===== akce =====
  window.collapseBtn = function(btn){
    const b = btn.closest('.db-block');
    b.classList.toggle('collapsed');
    saveLayout();
  };

  window.maximizeBtn = function(btn){
    const b = btn.closest('.db-block');
    b.classList.toggle('maximized');
  };

  function addToMinbar(block){
    const id = block.getAttribute('data-id');
    const title = block.getAttribute('data-title') || id;
    if (!id) return;

    // už existuje?
    if (minbar.querySelector('button[data-id="'+id+'"]')) return;

    const x = document.createElement('button');
    x.type = 'button';
    x.textContent = title;
    x.setAttribute('data-id', id);
    x.onclick = function(){
      block.classList.remove('hidden');
      const btn = minbar.querySelector('button[data-id="'+id+'"]');
      if (btn) btn.remove();
      rebuildMinbar();
      saveLayout();
    };
    minbar.appendChild(x);
    rebuildMinbar();
  }

  function rebuildMinbar(){
    const hasBtns = minbar.querySelectorAll('button').length > 0;
    minbar.style.display = hasBtns ? 'flex' : 'none';
  }

  window.hideBtn = function(btn){
    const b = btn.closest('.db-block');
    b.classList.add('hidden');
    addToMinbar(b);
    saveLayout();
  };

  window.widthMenu = function(btn){
    const b = btn.closest('.db-block');
    const cur = b.getAttribute('data-w') || 'w-1-2';
    const order = ['w-1-4','w-1-3','w-1-2','w-2-3','w-3-4','w-1-1'];
    const i = order.indexOf(cur);
    const next = order[(i+1) % order.length];
    applyWidth(b, next);
    saveLayout();
  };

  window.resetLayout = function(){
    try { localStorage.removeItem(KEY); } catch(e){}
    location.reload();
  };

  window.toggleAllCollapsed = function(){
    const blocks = qBlocks().filter(b => !b.classList.contains('hidden'));
    const anyOpen = blocks.some(b => !b.classList.contains('collapsed'));
    blocks.forEach(b => b.classList.toggle('collapsed', anyOpen));
    saveLayout();
  };

  window.showAll = function(){
    qBlocks().forEach(b => b.classList.remove('hidden'));
    // vyčistit lištu
    Array.from(minbar.querySelectorAll('button')).forEach(x => x.remove());
    rebuildMinbar();
    saveLayout();
  };

  // ===== drag & drop =====
  let dragEl = null;

  function handleDragStart(e){
    const head = e.target.closest('.db-head');
    if (!head) return;
    const block = head.closest('.db-block');
    if (!block) return;

    dragEl = block;
    block.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', block.getAttribute('data-id') || ''); } catch(err){}
  }

  function handleDragEnd(){
    if (dragEl) dragEl.classList.remove('dragging');
    dragEl = null;
    saveLayout();
  }

  function handleDragOver(e){
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    const over = e.target.closest('.db-block');
    if (!over || !dragEl || over === dragEl) return;

    const rect = over.getBoundingClientRect();
    const before = (e.clientY - rect.top) < rect.height / 2;

    if (before) grid.insertBefore(dragEl, over);
    else grid.insertBefore(dragEl, over.nextSibling);
  }

  grid.addEventListener('dragstart', handleDragStart, true);
  grid.addEventListener('dragend', handleDragEnd, true);
  grid.addEventListener('dragover', handleDragOver, true);

  // init
  loadLayout();
})();
</script>

</section>
