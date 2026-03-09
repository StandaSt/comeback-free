<?php
// blocks/blok_05_stav_systemu.php * Verze: V1 * Aktualizace: 05.03.2026
declare(strict_types=1);
?>
<div class="dash_card_head">
  <h3 class="dash_card_title">#5 Stav systému</h3>
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
  <table class="tbl" aria-label="Stav systému">
    <thead>
      <tr>
        <th>Služba</th>
        <th class="num">Stav</th>
        <th class="num">Odezva</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>DB</strong></td>
        <td class="num"><span class="badge_up">OK</span></td>
        <td class="num">4&nbsp;ms</td>
      </tr>
      <tr>
        <td><strong>Směny</strong></td>
        <td class="num"><span class="badge_up">OK</span></td>
        <td class="num">120&nbsp;ms</td>
      </tr>
      <tr>
        <td><strong>Restia</strong></td>
        <td class="num"><span class="badge_down">Zpoždění</span></td>
        <td class="num">1,3&nbsp;s</td>
      </tr>
    </tbody>
  </table>

  <div style="margin-top:10px; display:flex; justify-content:space-between; align-items:center;">
    <div class="small_note">Incidenty dnes</div>
    <div style="font-weight:900; font-size:18px;">1</div>
  </div>
</div>
