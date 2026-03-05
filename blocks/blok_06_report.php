<?php
// blocks/blok_06_report.php * Verze: V1 * Aktualizace: 05.03.2026
declare(strict_types=1);
?>
<div class="dash_card_head">
  <h3 class="dash_card_title">#6 Report</h3>
  <div class="dash_tools" aria-hidden="true">
  <button class="dash_btn" type="button" title="Detail">
    <svg viewBox="0 0 24 24"><path d="M4 12h16"></path><path d="M12 4v16"></path></svg>
  </button>
  <button class="dash_btn" type="button" title="Maximalizace">
    <svg viewBox="0 0 24 24"><path d="M8 3H3v5"></path><path d="M16 3h5v5"></path><path d="M21 16v5h-5"></path><path d="M3 16v5h5"></path></svg>
  </button>
  <button class="dash_btn" type="button" title="Zavřít">
    <svg viewBox="0 0 24 24"><path d="M6 6l12 12"></path><path d="M18 6L6 18"></path></svg>
  </button>
</div>
</div>
<div class="dash_card_body">

  <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;">
    <div style="border:1px solid rgba(15,23,42,0.10); border-radius:12px; padding:10px;">
      <div class="small_note">Objednávky</div>
      <div style="font-weight:900; font-size:18px;">742</div>
    </div>
    <div style="border:1px solid rgba(15,23,42,0.10); border-radius:12px; padding:10px;">
      <div class="small_note">Průměr</div>
      <div style="font-weight:900; font-size:18px;">325&nbsp;Kč</div>
    </div>
    <div style="border:1px solid rgba(15,23,42,0.10); border-radius:12px; padding:10px;">
      <div class="small_note">Reklamace</div>
      <div style="font-weight:900; font-size:18px;">6</div>
    </div>
  </div>

  <table class="tbl" aria-label="Tržby poboček">
    <thead>
      <tr>
        <th>Pobočka</th>
        <th class="num">Tržba</th>
        <th class="num">Zisk</th>
        <th class="num">Obj.</th>
      </tr>
    </thead>
    <tbody>
      <tr><td>Bolevec</td><td class="num">28&nbsp;400</td><td class="num">6&nbsp;120</td><td class="num">138</td></tr>
      <tr><td>Slovany</td><td class="num">24&nbsp;900</td><td class="num">5&nbsp;410</td><td class="num">122</td></tr>
      <tr><td>Doubravka</td><td class="num">19&nbsp;300</td><td class="num">3&nbsp;880</td><td class="num">96</td></tr>
      <tr><td>Centrum</td><td class="num">16&nbsp;200</td><td class="num">3&nbsp;210</td><td class="num">82</td></tr>
      <tr><td>Skvrňany</td><td class="num">14&nbsp;700</td><td class="num">2&nbsp;940</td><td class="num">73</td></tr>
    </tbody>
  </table>

</div>
