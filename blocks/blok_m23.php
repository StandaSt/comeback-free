<?php
declare(strict_types=1);
/* blocks/blok_m23.php * Verze: V1 * Aktualizace: 05.03.2026 */
?>
<section class="card">
  <div class="uk-cardhead">
    <div class="uk-title"><span class="uk-no">#23</span> Oprávnění</div>
    <div class="uk-actions">
      <button type="button" class="icon-btn uk-mini" title="Detail">⋯</button>
      <button type="button" class="icon-btn uk-mini" title="Max">⤢</button>
      <button type="button" class="icon-btn uk-mini" title="Zavřít">×</button>
    </div>
  </div>
  <div class="uk-body">
    <style>
      .m23_wrap{ overflow:auto; border:1px solid rgba(0,0,0,.08); border-radius:12px; }
      .m23_tbl{ width:100%; border-collapse:collapse; font-size:12px; }
      .m23_tbl th, .m23_tbl td{ padding:8px; border-bottom:1px solid rgba(0,0,0,.06); }
      .m23_tbl thead th{ border-bottom:1px solid rgba(0,0,0,.10); background:rgba(0,0,0,.03); }
      .m23_tbl th.left{ text-align:left; position:sticky; left:0; background:rgba(0,0,0,.03); z-index:2; }
      .m23_tbl td.left{ text-align:left; position:sticky; left:0; background:#fff; z-index:1; }
      .m23_tbl th.v{ writing-mode:vertical-rl; transform:rotate(180deg); white-space:nowrap; text-align:left; height:140px; width:28px; padding:10px 6px; }
      .m23_chk{ width:14px; height:14px; }
      .m23_row:hover td{ background:rgba(0,0,0,.02); }
    </style>

    <div class="m23_wrap">
      <table class="m23_tbl">
        <thead>
          <tr>
            <th class="left">Role</th>
            <th class="v">Dashboard</th>
            <th class="v">Reporty</th>
            <th class="v">Lidi</th>
            <th class="v">Admin</th>
            <th class="v">Objednávky</th>
            <th class="v">Zákazníci</th>
            <th class="v">Platby</th>
            <th class="v">Položky</th>
            <th class="v">Porovnání</th>
            <th class="v">Top</th>
            <th class="v">Směny</th>
            <th class="v">Restia</th>
            <th class="v">Logy</th>
            <th class="v">Chyby</th>
            <th class="v">Nastavení</th>
            <th class="v">Export</th>
            <th class="v">Import</th>
            <th class="v">Tisk</th>
            <th class="v">API</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = [
            ['Admin',      [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1]],
            ['Vedoucí',    [1,1,1,0,1,1,1,1,1,1,1,1,1,0,0,1,1,0,1,0]],
            ['Pokladna',   [1,0,0,0,1,1,1,1,1,0,0,1,1,0,0,0,0,0,1,0]],
            ['Kurýr',      [1,0,0,0,0,1,0,0,0,0,0,1,0,0,0,0,0,0,0,0]],
            ['Host',       [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0]],
          ];
          foreach ($rows as $r) {
              echo '<tr class="m23_row">';
              echo '<td class="left">' . htmlspecialchars($r[0], ENT_QUOTES) . '</td>';
              foreach ($r[1] as $v) {
                  $checked = ($v === 1) ? ' checked' : '';
                  echo '<td style="text-align:center;"><input class="m23_chk" type="checkbox"' . $checked . '></td>';
              }
              echo '</tr>';
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
