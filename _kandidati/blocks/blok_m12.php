<?php
declare(strict_types=1);
/* blocks/blok_m12.php * Verze: V1 * Aktualizace: 05.03.2026 */
?>
<section class="card">
  <div class="uk-cardhead">
    <div class="uk-title"><span class="uk-no">#12</span> Kalendář</div>
    <div class="uk-actions">
      <button type="button" class="icon-btn uk-mini" title="Detail">⋯</button>
      <button type="button" class="icon-btn uk-mini" title="Max">⤢</button>
      <button type="button" class="icon-btn uk-mini" title="Zavřít">×</button>
    </div>
  </div>
  <div class="uk-body">
    <div style="display:grid; grid-template-columns:240px 1fr; gap:12px; align-items:start;">
      <div style="border:1px solid rgba(0,0,0,.08); border-radius:12px; padding:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
          <b>Březen 2026</b>
          <span style="color:rgba(0,0,0,.55); font-size:12px;">týden 10</span>
        </div>
        <div style="margin-top:10px; display:grid; grid-template-columns:repeat(7, 1fr); gap:6px; font-size:12px; color:rgba(0,0,0,.60);">
          <div>Po</div><div>Út</div><div>St</div><div>Čt</div><div>Pá</div><div>So</div><div>Ne</div>
        </div>
        <div style="margin-top:6px; display:grid; grid-template-columns:repeat(7, 1fr); gap:6px;">
          <?php
          $days = [" "," "," "," "," ","1","2","3","4","5","6","7","8","9","10","11","12","13","14","15","16","17","18","19","20","21","22","23","24","25","26","27","28","29","30","31"]; // jednoduchá maketa
          foreach ($days as $d) {
              $isToday = ($d === '5');
              $bg = $isToday ? 'rgba(0,0,0,.10)' : 'transparent';
              $bd = $isToday ? '1px solid rgba(0,0,0,.18)' : '1px solid rgba(0,0,0,.06)';
              echo '<div style="height:28px; border:' . $bd . '; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:12px; background:' . $bg . '; color:rgba(0,0,0,.75);">' . htmlspecialchars($d, ENT_QUOTES) . '</div>';
          }
          ?>
        </div>
      </div>

      <div style="border:1px solid rgba(0,0,0,.08); border-radius:12px; overflow:hidden;">
        <div style="padding:10px 12px; border-bottom:1px solid rgba(0,0,0,.08); display:flex; justify-content:space-between;">
          <b>Plán na dnes</b>
          <span style="color:rgba(0,0,0,.55); font-size:12px;">05.03.2026</span>
        </div>
        <div style="padding:12px; display:flex; flex-direction:column; gap:10px;">
          <div style="display:flex; justify-content:space-between; gap:12px;">
            <div><b>11:00</b> – Kontrola skladu</div>
            <div style="color:rgba(0,0,0,.55);">Bolevec</div>
          </div>
          <div style="display:flex; justify-content:space-between; gap:12px;">
            <div><b>14:30</b> – Servis tiskárny</div>
            <div style="color:rgba(0,0,0,.55);">Centrum</div>
          </div>
          <div style="display:flex; justify-content:space-between; gap:12px;">
            <div><b>18:00</b> – Závěrka dne</div>
            <div style="color:rgba(0,0,0,.55);">Všechny</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
