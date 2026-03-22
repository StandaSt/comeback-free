<?php
// karty/admin_priprava_init.php * Verze: V1 * Aktualizace: 22.03.2026
declare(strict_types=1);

$initRowsByArea = [
    'Restia' => [],
    'Smeny' => [],
    'Google Sheets' => [],
];

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
            stav,
            procenta,
            poznamka,
            poradi,
            aktivni
        FROM init_scripty
        WHERE aktivni = 1
        ORDER BY hlavni_oblast ASC, poradi ASC, id_init_script ASC
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

            $stav = strtolower(trim((string)($row['stav'] ?? '')));
            if ($stav === 'hotovy') {
                $initHotovy++;
            } elseif ($stav === 'rozpracovany') {
                $initRozpracovany++;
            } elseif ($stav === 'není') {
                $initNeni++;
            }
        }
        $res->free();
    }
} catch (Throwable $e) {
    $initRowsByArea = [
        'Restia' => [],
        'Smeny' => [],
        'Google Sheets' => [],
    ];
    $initTotal = 0;
    $initHotovy = 0;
    $initRozpracovany = 0;
    $initNeni = 0;
    $initError = 'Nacteni pripravy inicializace selhalo.';
}

if ($initError !== '') {
    $card_min_html = '<p class="card_text card_text_muted">' . h($initError) . '</p>';
} else {
    $card_min_html = ''
        . '<div class="table-wrap">'
        . '  <table class="table card_table_min">'
        . '    <tbody>'
        . '      <tr>'
        . '        <td>Aktivni scripty celkem</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initTotal) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Stav hotovy</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initHotovy) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Stav rozpracovany</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initRozpracovany) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Stav neni</td>'
        . '        <td style="text-align:right;"><strong>' . h((string)$initNeni) . '</strong></td>'
        . '      </tr>'
        . '    </tbody>'
        . '  </table>'
        . '</div>';
}

ob_start();
?>
<?php if ($initError !== ''): ?>
  <p class="card_text card_text_muted"><?= h($initError) ?></p>
<?php else: ?>
<div class="karta-max">
  <div class="karta-header">
    <h4 style="margin:0; font-size:1.13rem; font-weight:700;">Příprava inicializace - detailní stav</h4>
    <div style="font-size:0.97em;color:#555; margin-bottom: 8px;">Souhrnný přehled aktivních přípravných skriptů pro Restia, Směny, Google Sheets.</div>
  </div>
  <div class="karta-tablewrap">
    <table class="karta-table">
      <thead class="karta-thead-sticky">
        <tr>
          <th>Krok</th>
          <th>Nazev scriptu</th>
          <th>Zdroj dat</th>
          <th>Soubor</th>
          <th>DB tabulky</th>
          <th>Stav</th>
          <th>Hotovo %</th>
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
            } elseif ($areaName === 'Smeny') {
                $areaColor = '#eaf3ff';
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
              <td colspan="8">Zadne aktivni zaznamy</td>
            </tr>
          <?php else: ?>
            <?php foreach ($areaRows as $row): ?>
              <?php
              $krok = trim((string)($row['krok'] ?? ''));
              $nazev = trim((string)($row['nazev'] ?? ''));
              $zdrojDat = trim((string)($row['zdroj_dat'] ?? ''));
              $soubor = trim((string)($row['soubor'] ?? ''));
              $dbTabulky = trim((string)($row['db_tabulc jiného - pouze ky'] ?? ''));
              $stav = trim((string)($row['stav'] ?? ''));
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
              if ($soubor === '') {
                  $soubor = '-';
              }
              if ($dbTabulky === '') {
                  $dbTabulky = '-';
              }
              if ($stav === '') {
                  $stav = '-';
              }
              if ($poznamka === '') {
                  $poznamka = '-';
              }

              $isHotovo100 = $procenta === 100;
              $isSouborNeni = (preg_match('~^nen(i|\x{ED})$~ui', $soubor) === 1);
              ?>
              <tr>
                <td><?= h($krok) ?></td>
                <td><?= h($nazev) ?></td>
                <td><?= h($zdrojDat) ?></td>
                <td<?= $isSouborNeni ? ' style="color:#b00020; font-weight:700;"' : '' ?>><?= h($soubor) ?></td>
                <td><?= h($dbTabulky) ?></td>
                <td><?= h($stav) ?></td>
                <td<?= $isHotovo100 ? ' style="color:#1b7f2a; font-weight:700;"' : '' ?>><?= h((string)$procenta) ?> %</td>
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