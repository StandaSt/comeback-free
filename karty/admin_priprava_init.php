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
    ob_start();
    ?>
    <div class="displ_flex jc_stred">
      <table class="table ram_normal bg_bila radek_1_35 sirka100">
        <thead>
          <tr>
            <th class="txt_l">Skupina</th>
            <th class="txt_r">Záznamů</th>
            <th class="txt_r">Objem</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($summaryKeys as $key): ?>
            <?php if (!isset($summary[$key])) continue; ?>
            <?php
              $item = $summary[$key];
              $label = (string)($item['label'] ?? $key);
              $count = (int)($item['count'] ?? 0);
              $bytes = (int)($item['bytes'] ?? 0);
            ?>
            <tr>
              <td><?= h($label) ?></td>
              <td class="<?= $count === 0 ? 'txt_r txt_cervena text_tucny' : 'txt_r' ?>"><strong><?= h(cb_db_fmt_rows_approx($count)) ?></strong></td>
              <td class="txt_r"><strong><?= h(cb_db_fmt_bytes($bytes)) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    $card_min_html = (string)ob_get_clean();
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
<div class="card_stack gap_8">
  <div>
    <h4 class="text_18 text_tucny odstup_vnejsi_0">Příprava inicializace - detailní stav</h4>
    <p class="card_text txt_seda odstup_vnejsi_0">Souhrnný přehled aktivních přípravných skriptů pro Restia, Směny, Google Sheets.</p>
  </div>
  <div class="table-wrap ram_normal bg_bila">
    <table class="table ram_normal bg_bila radek_1_35 sirka100">
      <thead>
        <tr>
          <th>Krok</th>
          <th>Název skriptu</th>
          <th>Zdroj dat</th>
          <th>Soubor</th>
          <th>DB tabulky</th>
          <th>Hotovo %</th>
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
              $souborClass = '';
              if (preg_match('~^nen(i|\x{ED})$~ui', $soubor) === 1) {
                  $souborClass = 'txt_cervena text_tucny';
              }
              ?>
              <tr>
                <td><?= h($krok) ?></td>
                <td><?= h($nazev) ?></td>
                <td><?= h($zdrojDat) ?></td>
                <td class="<?= h($souborClass) ?>"><?= h($soubor) ?></td>
                <td><?= h($dbTabulky) ?></td>
                <td class="<?= h($procentaClass) ?>"><?= h((string)$procenta) ?> %</td>
                <td><?= h($poznamka) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

// Konec souboru
