<?php
// K2
// karty/admin_priprava_init.php * Verze: V2 * Aktualizace: 17.04.2026
declare(strict_types=1);

$summaryKeys = ['restia', 'smeny', 'reporty'];
$summary = [];
$initError = '';

try {
    $conn = db();
    $conn->set_charset('utf8mb4');
    $summary = cb_db_scope_summary($conn, $summaryKeys);
} catch (Throwable $e) {
    $summary = [];
    $initError = 'Načtení přehledu databáze selhalo.';
}

if ($initError !== '') {
    $card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0 card_text_muted">' . h($initError) . '</p>';
} else {
    $card_min_html = ''
        . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
        . '  <table class="table ram_normal bg_bila radek_1_35">'
        . '    <thead>'
        . '      <tr>'
        . '        <th class="txt_l">Skupina</th>'
        . '        <th class="txt_r">Záznamů</th>'
        . '        <th class="txt_r">Objem</th>'
        . '      </tr>'
        . '    </thead>'
        . '    <tbody>';

    foreach ($summaryKeys as $key) {
        if (!isset($summary[$key])) {
            continue;
        }

        $item = $summary[$key];
        $label = (string)($item['label'] ?? $key);
        $count = (int)($item['count'] ?? 0);
        $bytes = (int)($item['bytes'] ?? 0);

        $card_min_html .= ''
            . '      <tr>'
            . '        <td>' . h($label) . '</td>'
            . '        <td class="txt_r" style="' . h(cb_db_count_style($count)) . '"><strong>' . h(cb_db_fmt_rows_approx($count)) . '</strong></td>'
            . '        <td class="txt_r"><strong>' . h(cb_db_fmt_bytes($bytes)) . '</strong></td>'
            . '      </tr>';
    }

    $card_min_html .= ''
        . '    </tbody>'
        . '  </table>'
        . '</div>';
}

$initRowsByArea = [
    'Restia' => [],
    'Směny' => [],
    'Google Sheets' => [],
];

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $res = $conn->query('
        SELECT
            hlavni_oblast,
            krok,
            nazev,
            zdroj_dat,
            soubor,
            db_tabulky,
            procenta,
            spousti,
            poznamka,
            poradi
        FROM init_scripty
        ORDER BY hlavni_oblast ASC, poradi ASC, id_init_script ASC
    ');

    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $area = trim((string)($row['hlavni_oblast'] ?? ''));
            if (!isset($initRowsByArea[$area])) {
                continue;
            }

            $initRowsByArea[$area][] = $row;
        }
        $res->free();
    }
} catch (Throwable $e) {
    $initRowsByArea = [
        'Restia' => [],
        'Směny' => [],
        'Google Sheets' => [],
    ];
}

ob_start();
?>
<?php if ($initError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($initError) ?></p>
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
          <th>Název skriptu</th>
          <th>Zdroj dat</th>
          <th>Soubor</th>
          <th>DB tabulky</th>
          <th>Hotovo %</th>
          <th>Spouští</th>
          <th>Poznámka</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($initRowsByArea as $areaName => $areaRows): ?>
          <?php
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
            <th colspan="8" class="txt_l text_tucny" style="background:<?= h($areaColor) ?>;"><?= h($areaName) ?></th>
          </tr>

          <?php if (!$areaRows): ?>
            <tr>
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
              $spousti = trim((string)($row['spousti'] ?? ''));
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
              if ($spousti === '') {
                  $spousti = '-';
              }
              if ($poznamka === '') {
                  $poznamka = '-';
              }

              $isHotovo100 = ($procenta === 100);
              $isHotovo0 = ($procenta === 0);
              $procentaClass = 'txt_r';
              if ($isHotovo100) {
                  $procentaClass .= ' txt_zelena text_tucny';
              }
              if ($isHotovo0) {
                  $procentaClass .= ' txt_cervena text_tucny';
              }
              $souborStyle = '';
              if (preg_match('~^nen(i|\x{ED})$~ui', $soubor) === 1) {
                  $souborStyle = ' style="color:#b00020; font-weight:700;"';
              }
              ?>
              <tr>
                <td><?= h($krok) ?></td>
                <td><?= h($nazev) ?></td>
                <td><?= h($zdrojDat) ?></td>
                <td<?= $souborStyle ?>><?= h($soubor) ?></td>
                <td><?= h($dbTabulky) ?></td>
                <td class="<?= h($procentaClass) ?>"><?= h((string)$procenta) ?> %</td>
                <td><?= h($spousti) ?></td>
                <td style="white-space:pre-wrap;"><?= h($poznamka) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
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
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

// Konec souboru
