<?php
// karty/admin_priprava_init.php * Verze: V1 * Aktualizace: 22.03.2026
declare(strict_types=1);

$initRowsByArea = [
    'Restia' => [],
    'Směny' => [],
    'Google Sheets' => [],
];
$hotovo_proc = 0;
$celkova_procenta = 0;
$initTotal = 0;
$initHotovy = 0;
$initRozpracovany = 0;
$initNeni = 0;
$initError = '';

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $sql = '
        SELECT
            id_init_script,
            hlavni_oblast,
            krok,
            nazev,
            zdroj_dat,
            soubor,
            db_tabulky,
            procenta,
            poznamka
            
         FROM init_scripty
         ORDER BY hlavni_oblast ASC, procenta DESC
    ';

    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $area = trim((string)($row['hlavni_oblast'] ?? ''));
            if (!isset($initRowsByArea[$area])) {
                continue;
            }

            $initRowsByArea[$area][] = $row;
            $initTotal++;

            $hotovo_proc = $hotovo_proc + $row['procenta'];
            if ($row['procenta'] === '100') {
                $initHotovy++;
                
            } elseif ($row['procenta'] === '0') {
                $initNeni++;
            } else {
                $initRozpracovany++;
            }
        }

        $celkova_procenta = ($hotovo_proc / $initTotal ) ;
        $res->free();
    }
} catch (Throwable $e) {
    $initRowsByArea = [
        'Restia' => [],
        'Směny' => [],
        'Google Sheets' => [],
    ];
    $initTotal = 0;
    $initHotovy = 0;
    $initRozpracovany = 0;
    $initNeni = 0;
    $initError = 'Načtení přípravy inicializace - selhalo.';
}

if ($initError !== '') {
    $card_min_html = '<p class="card_text card_text_muted">' . h($initError) . '</p>';
} else {
    $card_min_html = ''
        . '<div class="table-wrap">'
        . '  <table class="table card_table_min">'
        . '    <tbody>'
        . '      <tr>'
        . '        <td>Potřebné akce pro inicializaci:</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initTotal) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Dokončeno:</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initHotovy) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Rozpracované soubory:</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initRozpracovany) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Nemá soubor:</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initNeni) . '</strong></td>'
        . '      </tr>'
        . '    </tbody>'
        . '  </table>'
        . '</div><br><strong>  Celkový stav inicializace: </strong>' .  $celkova_procenta  . ' %';
}

ob_start();
?>
<?php if ($initError !== ''): ?>
  <p class="card_text card_text_muted"><?= h($initError) ?></p>
<?php else: ?>
<div class="karta-max" style="height:calc(100dvh - 250px);">

  <div class="karta-tablewrap">
    <table class="karta-table">
      <thead class="karta-thead-sticky">
        <tr>
          <th>Krok</th>
          <th>Nazev scriptu</th>
          <th>Zdroj dat</th>
          <th>Soubor</th>
          <th>DB tabulky</th>
          <th>Hotovo</th>
          <th>Poznamka</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($initRowsByArea as $areaName => $areaRows): ?>
          <?php
            // Volba pastelove barvy pro danou oblast
            $areaColor = '';
            if ($areaName === 'Restia') {
                $areaColor = '#ffeaea';
            } elseif ($areaName === 'Směny') {
                $areaColor = '#94c1ee';
            } elseif ($areaName === 'Google Sheets') {
                $areaColor = '#eaffef';
            }
          ?>
          <tr>
            <th colspan="8" style="text-align:left; font-weight:700; font-size:1.05rem; background:<?= $areaColor ?>;"><?= h($areaName) ?></th>
          </tr>

          <?php if (!$areaRows): ?>
            <tr
>
              <td colspan="8">Žádné aktivní záznamy</td>
            </tr>
          <?php else: ?>
            <?php foreach ($areaRows as $row): ?>
              <?php
              $krok = trim((string)($row['krok'] ?? ''));
              $nazev = trim((string)($row['nazev'] ?? ''));
              $zdrojDat = trim((string)($row['zdroj_dat'] ?? ''));
              $soubor = trim((string)($row['soubor'] ?? ''));
              $dbTabulky = trim((string)($row['db_tabulky'] ?? ''));
              $procenta = (int)($row['procenta'] ?? 0);
              $poznamka = trim((string)($row['poznamka'] ?? ''));

              if ($krok === '') {
                  $krok = '-';
              }
              if ($nazev === '') {
                  $nazev = '-';
              }
              if ($zdrojDat === '') {
                  $zdrojDat = '-';
              }
              if ($soubor === 'není') {
                  $soubor = '-';
              }
              if ($dbTabulky === '') {
                  $dbTabulky = '-';
              }
              if ($poznamka === '') {
                  $poznamka = '-';
              }

              $isHotovo100 = $procenta === 100;
              $isHotovo0 = $procenta === 0;
              $styleProcenta = ' style="text-align:right;"';
              if ($isHotovo100) {
                  $styleProcenta = ' style="color:#1b7f2a; font-weight:700; text-align:right;"';
              }

              if ($isHotovo0) {
                  $styleProcenta = ' style="color:#b00020; font-weight:700; text-align:right;"';
              }
              ?>
              <tr>
                <td><?= h($krok) ?></td>
                <td><?= h($nazev) ?></td>
                <td><?= h($zdrojDat) ?></td>
                <td><?= h($soubor) ?></td>
                <td><?= h($dbTabulky) ?></td>
               <td<?= $styleProcenta ?>><?= h((string)$procenta) ?> %</td>
                <td style="white-space:pre-wrap;"><?= h($poznamka) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
  <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<style>
.karta-max {
  display: flex;
  flex-direction: column;
  height: 100%;
  border-radius: 7px;
  border: 1px solid #e0e0e0;
  padding:0;
  margin-bottom: 16px;
  background:#fff;
  box-shadow: 0 2px 8px 0 #e0e0e01c;
}
.karta-header {
  position: relative;
  padding: 16px 18px 6px 18px;
  background: #f8f8fa;
  border-bottom: 1px solid #e0e0e0;
  z-index: 2;
}
.karta-tablewrap {
  flex: 1 1 0;
  min-height: 160px;
  overflow: auto;
  background: #fff;
  border-radius: 0 0 7px 7px;
}
.karta-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  font-size:1em;
  min-width: 820px;
}
.karta-table th, .karta-table td {
  padding: 6px 9px;
  border-bottom: 1px solid #ececec;
  text-align:left;
  background: #fff;
}
.karta-thead-sticky th {
  position: sticky;
  top: 0;
  z-index: 3;
  background: #f4f4f7;
  border-bottom: 2px solid #c7d2ff;
  box-shadow: 0 2px 4px #0001;
  font-weight: 700;
}
.karta-table tr:last-child td {
  border-bottom: none;
}
</style>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_priprava_init.php * Verze: V1 * Aktualizace: 22.03.2026 */
?>