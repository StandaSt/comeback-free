<?php
declare(strict_types=1);
/* blocks/blok_m05.php * Verze: V1 * Aktualizace: 05.03.2026 */
?>
<section class="card">
  <div class="uk-cardhead">
    <div class="uk-title"><span class="uk-no">#5</span> Stav systému</div>
    <div class="uk-actions">
      <button type="button" class="icon-btn uk-mini" title="Detail">⋯</button>
      <button type="button" class="icon-btn uk-mini" title="Max">⤢</button>
      <button type="button" class="icon-btn uk-mini" title="Zavřít">×</button>
    </div>
  </div>
  <div class="uk-body">
    <div style="display:flex; flex-direction:column; gap:10px;">
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div style="font-weight:700;">DB</div>
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="width:10px; height:10px; border-radius:50%; background:#22c55e;"></span>
          <span style="color:rgba(0,0,0,.60); font-size:13px;">OK (4 ms)</span>
        </div>
      </div>

      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div style="font-weight:700;">Směny</div>
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="width:10px; height:10px; border-radius:50%; background:#22c55e;"></span>
          <span style="color:rgba(0,0,0,.60); font-size:13px;">OK (120 ms)</span>
        </div>
      </div>

      <div style="display:flex; align-items:center; justify-content:space-between;">
        <div style="font-weight:700;">Restia</div>
        <div style="display:flex; align-items:center; gap:8px;">
          <span style="width:10px; height:10px; border-radius:50%; background:#f59e0b;"></span>
          <span style="color:rgba(0,0,0,.60); font-size:13px;">Zpoždění (1,3 s)</span>
        </div>
      </div>

      <div style="margin-top:6px; padding-top:10px; border-top:1px solid rgba(0,0,0,.08); display:flex; justify-content:space-between; color:rgba(0,0,0,.60); font-size:12px;">
        <span>Incidenty dnes</span><b style="color:rgba(0,0,0,.75);">1</b>
      </div>
    </div>
  </div>
</section>
