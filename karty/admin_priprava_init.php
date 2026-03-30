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
            poradi,
            db_tabulky,
            procenta,
            spousti,
            poznamka
        FROM init_scripty
        ORDER BY hlavni_oblast ASC, poradi ASC
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
    $card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0 card_text_muted">' . h($initError) . '</p>';
} else {
    $card_min_html = ''
        . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
        . '  <table class="table ram_normal bg_bila radek_rozvolneny">'
        . '    <tbody>'
        . '      <tr>'
        . '        <td>Potřebné akce pro inicializaci:</td>'
        . '        <td class="text_vpravo"><strong>' . h((string)$initTotal) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Dokončeno:</td>'
        . '        <td class="text_vpravo"><strong>' . h((string)$initHotovy) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Rozpracované soubory:</td>'
        . '        <td class="text_vpravo"><strong>' . h((string)$initRozpracovany) . '</strong></td>'
        . '      </tr>'
        . '      <tr>'
        . '        <td>Nemá soubor:</td>'
        . '        <td class="text_vpravo"><strong>' . h((string)$initNeni) . '</strong></td>'
        . '      </tr>'
        . '    </tbody>'
        . '  </table>'
        . '</div><p class="card_text txt_seda odstup_vnejsi_0"><strong>Celkový stav inicializace:</strong> ' . h((string)$celkova_procenta) . ' %</p>';
}

ob_start();
?>
<?php if ($initError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($initError) ?></p>
<?php else: ?>
<?php
$sirkaSloupcu = [
    'krok' => 'width:9ch;',
    'nazev' => 'width:25ch;',
    'zdroj' => 'width:24ch;',
    'soubor' => 'width:24ch;',
    'db' => 'width:18ch;',
    'hotovo' => 'width:8ch;',
    'spousti' => 'width:8ch;',
    'poznamka' => 'width:55ch;',
];
?>
<div class="card_stack mezera_mezi_10 displ_flex">

  <div class="table-wrap ram_normal bg_bila zaobleni_12">
    <table class="table ram_normal bg_bila radek_rozvolneny">
      <thead>
        <tr>
          <th style="<?= h($sirkaSloupcu['krok']) ?>">Krok</th>
          <th style="<?= h($sirkaSloupcu['nazev']) ?>">Název skriptu</th>
          <th style="<?= h($sirkaSloupcu['zdroj']) ?>">Zdroj dat</th>
          <th style="<?= h($sirkaSloupcu['soubor']) ?>">Soubor</th>
          <th style="<?= h($sirkaSloupcu['db']) ?>">DB tabulky</th>
          <th style="<?= h($sirkaSloupcu['hotovo']) ?>">Hotovo</th>
          <th style="<?= h($sirkaSloupcu['spousti']) ?>">Spouští</th>
          <th style="<?= h($sirkaSloupcu['poznamka']) ?>">Poznámka</th>
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
            <th colspan="8" class="text_vlevo text_tucny" style="background:<?= $areaColor ?>;"><?= h($areaName) ?></th>
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
              if ($soubor === 'není') {
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

              $isHotovo100 = $procenta === 100;
              $isHotovo0 = $procenta === 0;
              $procentaClass = 'text_vpravo';
              if ($isHotovo100) {
                  $procentaClass .= ' txt_zelena text_tucny';
              }

              if ($isHotovo0) {
                  $procentaClass .= ' txt_cervena text_tucny';
              }
              ?>
              <tr>
                <td style="<?= h($sirkaSloupcu['krok']) ?>"><?= h($krok) ?></td>
                <td style="<?= h($sirkaSloupcu['nazev']) ?>"><?= $nazev ?></td>
                <td style="<?= h($sirkaSloupcu['zdroj']) ?>"><?= $zdrojDat ?></td>
                <td style="<?= h($sirkaSloupcu['soubor']) ?>"><?= $soubor ?></td>
                <td style="<?= h($sirkaSloupcu['db']) ?>"><?= $dbTabulky ?></td>
                <td class="<?= h($procentaClass) ?>"><?= h((string)$procenta) ?> %</td>
                <td style="<?= h($sirkaSloupcu['spousti']) ?>"><?= h($spousti) ?></td>
                <td style="<?= h($sirkaSloupcu['poznamka']) ?> white-space:pre-wrap;"><?= h($poznamka) ?></td>
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

/* karty/admin_priprava_init.php * Verze: V1 * Aktualizace: 22.03.2026 */
?>
