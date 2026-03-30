<?php
// karty/prehled_smen.php * Verze: V7 * Aktualizace: 27.03.2026
declare(strict_types=1);

/*
 * Karta "Přehled směn":
 * - čte data ze smeny_report podle globálního období a výběru poboček,
 * - umí filtry, třídění a stránkování,
 * - vykreslí min i max režim karty.
 */

// === KONFIG TABULKY: PREHLED_SMEN ===
$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
    'default_sort' => 'datum',
    'default_dir' => 'DESC',
];

$psRows = [];
$psTotal = 0;
$psPages = 1;
$psPage = 1;
$psPer = (int)$tabKonfig['default_per'];
$psError = '';
$psErrCount = 0;

$formAction = cb_url('/');
$keepExpanded = isset($_GET['ps_p']) || isset($_GET['ps_per']) || isset($_GET['ps_f']) || isset($_GET['ps_sort']) || isset($_GET['ps_dir']);

// Globální období je nastavené v hlavičce a drží se v session.
$periodOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

if ($periodOd === '' || $periodDo === '') {
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v >= 0));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob >= 0) {
        $selectedPob = [$fallbackPob];
    }
}

if (!function_exists('ps_format_cz_date')) {
    function ps_format_cz_date(string $ymd): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (!$dt) {
            return $ymd;
        }
        return $dt->format('j.n.Y');
    }
}

if (!function_exists('ps_format_hm')) {
    function ps_format_hm(string $time): string
    {
        $t = trim($time);
        if ($t === '') {
            return '';
        }
        if (preg_match('/^(\d{2}):(\d{2})(:\d{2})?$/', $t, $m) === 1) {
            return $m[1] . ':' . $m[2];
        }
        return $t;
    }
}

$periodOdCz = ps_format_cz_date($periodOd);
$periodDoCz = ps_format_cz_date($periodDo);

$selectedMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
$selectedOblasti = $_SESSION['selected_oblasti'] ?? [];
if (!is_array($selectedOblasti)) {
    $selectedOblasti = [];
}
$selectedOblasti = array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $selectedOblasti), static fn(string $v): bool => $v !== ''));
sort($selectedOblasti);

$selectedOblastSingle = trim((string)($_SESSION['selected_oblast'] ?? ''));
if ($selectedOblastSingle !== '' && !in_array($selectedOblastSingle, $selectedOblasti, true)) {
    $selectedOblasti[] = $selectedOblastSingle;
    sort($selectedOblasti);
}

$selectedPobNames = [];
if ($selectedPob !== []) {
    try {
        $connNames = db();
        $inIds = [];
        foreach ($selectedPob as $idPobName) {
            $inIds[] = (string)(int)$idPobName;
        }
        $sqlNames = 'SELECT id_pob, nazev FROM pobocka WHERE id_pob IN (' . implode(',', $inIds) . ')';
        $resNames = $connNames->query($sqlNames);
        if ($resNames instanceof mysqli_result) {
            $nameMap = [];
            while ($rowName = $resNames->fetch_assoc()) {
                $idName = (int)($rowName['id_pob'] ?? 0);
                $nazev = trim((string)($rowName['nazev'] ?? ''));
                if ($idName > 0 && $nazev !== '') {
                    $nameMap[$idName] = $nazev;
                }
            }
            $resNames->free();
            foreach ($selectedPob as $idSelName) {
                if ((int)$idSelName === 0) {
                    $selectedPobNames[] = 'Výroba';
                } elseif (isset($nameMap[(int)$idSelName])) {
                    $selectedPobNames[] = $nameMap[(int)$idSelName];
                }
            }
        }
    } catch (Throwable $e) {
        $selectedPobNames = [];
    }
}

$selectedPobText = '-';
if ($selectedMode === 'area' && $selectedOblasti !== []) {
    $selectedPobText = (count($selectedOblasti) === 1 ? 'Oblast: ' : 'Oblasti: ') . implode(', ', $selectedOblasti);
} elseif ($selectedPobNames !== []) {
    $selectedPobText = implode(', ', $selectedPobNames);
} elseif ($selectedPob !== []) {
    $selectedPobText = implode(', ', array_map(static fn(int $v): string => (string)$v, $selectedPob));
}

$psCols = [
    'datum' => 'datum',
    'pobocka' => 'pobočka',
    'prijmeni' => 'příjmení',
    'jmeno' => 'jméno',
    'slot' => 'slot',
    'cas_od' => 'od',
    'cas_do' => 'do',
    'pauza' => 'pauza',
    'odpracovano' => 'odpracováno',
    'chyba' => 'chyba',
];

$psFilters = [
    'prijmeni' => '',
    'jmeno' => '',
    'slot' => '',
    'chyba' => '',
];

// Whitelist sloupců pro ORDER BY - ochrana proti neplatným vstupům.
$psSortRaw = trim((string)($_GET['ps_sort'] ?? (string)$tabKonfig['default_sort']));
$psDirRaw = strtoupper(trim((string)($_GET['ps_dir'] ?? (string)$tabKonfig['default_dir'])));
$psSort = (string)$tabKonfig['default_sort'];
$psDir = (string)$tabKonfig['default_dir'];

$psSortMap = [
    'datum' => 'sr.datum',
    'pobocka' => 'COALESCE(p.nazev, "Vyroba")',
    'prijmeni' => 'COALESCE(sr.prijmeni, "")',
    'jmeno' => 'COALESCE(sr.jmeno, "")',
    'slot' => 'sr.id_slot',
    'cas_od' => 'COALESCE(sr.cas_od, "")',
    'cas_do' => 'COALESCE(sr.cas_do, "")',
    'pauza' => 'COALESCE(sr.pauza, "")',
    'odpracovano' => 'COALESCE(sr.odpracovano, 0)',
    'chyba' => 'COALESCE(sr.chyba, 0)',
];
if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($psSortRaw, $psSortMap)) {
    $psSort = $psSortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($psDirRaw, ['ASC', 'DESC'], true)) {
    $psDir = $psDirRaw;
}

// Stránkování lze vypnout přes konfig.
$perOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($perOptions === []) {
    $perOptions = [20, 50, 100];
}
$psPerRaw = (int)($_GET['ps_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($psPerRaw, $perOptions, true)) {
    $psPer = $psPerRaw;
}

$psPageRaw = (int)($_GET['ps_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $psPageRaw > 1) {
    $psPage = $psPageRaw;
}

$psFiltersRaw = $_GET['ps_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($psFiltersRaw)) {
    $psFilters['prijmeni'] = trim((string)($psFiltersRaw['prijmeni'] ?? ''));
    $psFilters['jmeno'] = trim((string)($psFiltersRaw['jmeno'] ?? ''));

    $slotRaw = trim((string)($psFiltersRaw['slot'] ?? ''));
    if (in_array($slotRaw, ['', '1', '2', '3'], true)) {
        $psFilters['slot'] = $slotRaw;
    }

    $chybaRaw = trim((string)($psFiltersRaw['chyba'] ?? ''));
    if ($chybaRaw === '1') {
        $psFilters['chyba'] = '1';
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $where = [];
    $where[] = "sr.datum >= '" . $conn->real_escape_string($periodOd) . "'";
    $where[] = "sr.datum <= '" . $conn->real_escape_string($periodDo) . "'";

    if ($selectedPob !== []) {
        $inIds = [];
        foreach ($selectedPob as $idPob) {
            $inIds[] = (string)(int)$idPob;
        }
        $where[] = 'sr.id_pob IN (' . implode(',', $inIds) . ')';
    }

    // Dynamické filtry tabulky.
    if ((int)$tabKonfig['enable_filters'] === 1 && $psFilters['prijmeni'] !== '') {
        $safe = $conn->real_escape_string($psFilters['prijmeni']);
        $where[] = "COALESCE(sr.prijmeni,'') LIKE '%{$safe}%'";
    }

    if ((int)$tabKonfig['enable_filters'] === 1 && $psFilters['jmeno'] !== '') {
        $safe = $conn->real_escape_string($psFilters['jmeno']);
        $where[] = "COALESCE(sr.jmeno,'') LIKE '%{$safe}%'";
    }

    if ((int)$tabKonfig['enable_filters'] === 1 && $psFilters['slot'] !== '') {
        $where[] = 'sr.id_slot = ' . (int)$psFilters['slot'];
    }

    if ((int)$tabKonfig['enable_filters'] === 1 && $psFilters['chyba'] === '1') {
        $where[] = 'sr.chyba = 1';
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $sqlCount = 'SELECT COUNT(*) AS cnt, SUM(CASE WHEN sr.chyba = 1 THEN 1 ELSE 0 END) AS err_cnt FROM smeny_report sr' . $whereSql;
    $resCount = $conn->query($sqlCount);
    if ($resCount instanceof mysqli_result) {
        $rowCount = $resCount->fetch_assoc();
        $psTotal = (int)($rowCount['cnt'] ?? 0);
        $psErrCount = (int)($rowCount['err_cnt'] ?? 0);
        $resCount->free();
    }

    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $psPages = max(1, (int)ceil($psTotal / $psPer));
        if ($psPage > $psPages) {
            $psPage = $psPages;
        }
        $offset = ($psPage - 1) * $psPer;
    } else {
        $psPages = 1;
        $psPage = 1;
        $psPer = max(1, $psTotal);
        $offset = 0;
    }

    $orderSql = 'sr.datum DESC, sr.id_pob ASC, sr.prijmeni ASC, sr.jmeno ASC';
    if ((int)$tabKonfig['enable_sort'] === 1) {
        $orderSql = $psSortMap[$psSort] . ' ' . $psDir . ', sr.datum DESC, sr.id_pob ASC, sr.prijmeni ASC, sr.jmeno ASC';
    }

    $sqlRows = '
        SELECT
            sr.datum,
            sr.id_pob,
            COALESCE(p.nazev, "Výroba") AS pobocka_nazev,
            sr.prijmeni,
            sr.jmeno,
            sr.id_slot,
            sr.cas_od,
            sr.cas_do,
            sr.pauza,
            sr.odpracovano,
            sr.chyba
        FROM smeny_report sr
        LEFT JOIN pobocka p ON p.id_pob = sr.id_pob
    ' . $whereSql . '
        ORDER BY ' . $orderSql . '
        LIMIT ' . (int)$psPer . ' OFFSET ' . (int)$offset;

    $resRows = $conn->query($sqlRows);
    if ($resRows instanceof mysqli_result) {
        while ($row = $resRows->fetch_assoc()) {
            $psRows[] = $row;
        }
        $resRows->free();
    }
} catch (Throwable $e) {
    $psRows = [];
    $psTotal = 0;
    $psPages = 1;
    $psPage = 1;
    $psErrCount = 0;
    $psError = 'Načtení přehledu směn selhalo.';
}

$card_min_html = ''
    . '<p class="card_text txt_seda odstup_vnejsi_0">Období: <strong>' . h($periodOdCz) . ' až ' . h($periodDoCz) . '</strong></p>'
    . '<p class="card_text txt_seda odstup_vnejsi_0">Pobočky: <strong>' . h($selectedPobText) . '</strong></p>'
    . '<p class="card_text txt_seda odstup_vnejsi_0">Řádků: <strong>' . h((string)$psTotal) . '</strong>, chyby: <strong>' . h((string)$psErrCount) . '</strong></p>';

$startExpanded = $keepExpanded;

$psBaseParams = [
    'ps_per=' . rawurlencode((string)$psPer),
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $psBaseParams[] = 'ps_sort=' . rawurlencode($psSort);
    $psBaseParams[] = 'ps_dir=' . rawurlencode($psDir);
}
foreach ($psFilters as $key => $value) {
    if ($value === '') {
        continue;
    }
    if ((int)$tabKonfig['enable_filters'] === 1) {
        $psBaseParams[] = 'ps_f[' . rawurlencode($key) . ']=' . rawurlencode($value);
    }
}
$psBaseUrl = cb_url('/?' . implode('&', $psBaseParams));

ob_start();
?>
<?php if ($psError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($psError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h($formAction) ?>" class="card_stack mezera_mezi_10 displ_flex" autocomplete="off">
    <input type="hidden" name="ps_p" value="1">
    <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
      <input type="hidden" name="ps_sort" value="<?= h($psSort) ?>">
      <input type="hidden" name="ps_dir" value="<?= h($psDir) ?>">
    <?php endif; ?>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="table ram_normal bg_bila radek_rozvolneny card_table_max">
        <thead>
          <tr class="filter-row">
            <th></th>
            <th></th>
            <th>
              <?php if ((int)$tabKonfig['enable_filters'] === 1): ?>
                <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="width:10ch;" type="text" name="ps_f[prijmeni]" value="<?= h($psFilters['prijmeni']) ?>">
              <?php endif; ?>
            </th>
            <th>
              <?php if ((int)$tabKonfig['enable_filters'] === 1): ?>
                <input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="width:10ch;" type="text" name="ps_f[jmeno]" value="<?= h($psFilters['jmeno']) ?>">
              <?php endif; ?>
            </th>
            <th>
              <?php if ((int)$tabKonfig['enable_filters'] === 1): ?>
                <select class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" name="ps_f[slot]" onchange="this.form.ps_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <option value=""<?= $psFilters['slot'] === '' ? ' selected' : '' ?>>slot</option>
                  <option value="1"<?= $psFilters['slot'] === '1' ? ' selected' : '' ?>>instor</option>
                  <option value="2"<?= $psFilters['slot'] === '2' ? ' selected' : '' ?>>kurýr</option>
                  <option value="3"<?= $psFilters['slot'] === '3' ? ' selected' : '' ?>>výroba</option>
                </select>
              <?php endif; ?>
            </th>
            <th></th>
            <th></th>
            <th></th>
            <th></th>
            <th style="text-align:right;">
              <?php if ((int)$tabKonfig['enable_filters'] === 1 && $psErrCount > 0): ?>
                <label style="display:inline-flex; align-items:center; gap:6px; white-space:nowrap; cursor:pointer;">
                  <input type="checkbox" name="ps_f[chyba]" value="1"<?= $psFilters['chyba'] === '1' ? ' checked' : '' ?> onchange="this.form.ps_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                  <span style="color:#c62828; font-weight:700;">Chyby (<?= h((string)$psErrCount) ?>)</span>
                </label>
              <?php endif; ?>
            </th>
          </tr>
          <tr>
            <?php foreach ($psCols as $key => $label): ?>
              <?php
              $isActiveSort = ($psSort === $key);
              $arrow = '↕';
              if ($isActiveSort) {
                  $arrow = $psDir === 'ASC' ? '↑' : '↓';
              }
              $sortUrl = '';
              if ((int)$tabKonfig['enable_sort'] === 1) {
                  $nextDir = ($isActiveSort && $psDir === 'ASC') ? 'DESC' : 'ASC';
                  $sortParams = $psBaseParams;
                  $sortParams[] = 'ps_p=1';
                  $sortParams[] = 'ps_sort=' . rawurlencode($key);
                  $sortParams[] = 'ps_dir=' . rawurlencode($nextDir);
                  $sortUrl = cb_url('/?' . implode('&', $sortParams));
              }
              ?>
              <th class="th-sort<?= $isActiveSort ? ' active' : '' ?>">
                <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
                  <a class="th-sort-link mezera_mezi_8 jc_mezi sirka100<?= $isActiveSort ? ' active' : '' ?>" href="<?= h($sortUrl) ?>">
                    <span class="th-sort-label"><?= h($label) ?></span>
                    <span class="th-sort-arrow text_vpravo"><?= h($arrow) ?></span>
                  </a>
                <?php else: ?>
                  <span class="th-sort-link mezera_mezi_8 jc_mezi sirka100">
                    <span class="th-sort-label"><?= h($label) ?></span>
                  </span>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$psRows): ?>
            <tr><td colspan="10">Žádná data</td></tr>
          <?php else: ?>
            <?php foreach ($psRows as $row): ?>
              <?php
              $slotTxt = match ((int)($row['id_slot'] ?? 0)) {
                  1 => 'instor',
                  2 => 'kurýr',
                  3 => 'výroba',
                  default => '-',
              };
              $chybaTxt = ((int)($row['chyba'] ?? 0) === 1) ? 'ano' : '-';
              $pobTxt = ((int)($row['id_pob'] ?? 0) === 0) ? 'Výroba' : (string)($row['pobocka_nazev'] ?? '-');
              ?>
              <tr>
                <td><?= h(ps_format_cz_date((string)$row['datum'])) ?></td>
                <td style="text-align:right;"><?= h($pobTxt) ?></td>
                <td style="text-align:right;"><?= h((string)($row['prijmeni'] ?? '')) ?></td>
                <td><?= h((string)($row['jmeno'] ?? '')) ?></td>
                <td><?= h($slotTxt) ?></td>
                <td><?= h(ps_format_hm((string)($row['cas_od'] ?? ''))) ?></td>
                <td><?= h(ps_format_hm((string)($row['cas_do'] ?? ''))) ?></td>
                <td><?= h(ps_format_hm((string)($row['pauza'] ?? ''))) ?></td>
                <td style="text-align:right;"><?= h((string)($row['odpracovano'] ?? '')) ?></td>
                <td><?= h($chybaTxt) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
    <div class="list-bottom mezera_mezi_14 mezera_mezi_10 odstup_vnitrni_0 displ_grid">
      <div class="per-form mezera_mezi_8 displ_inline_flex">
        <span>Zobrazuji</span>
        <select name="ps_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.ps_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
          <?php foreach ($perOptions as $optPer): ?>
            <option value="<?= h((string)$optPer) ?>"<?= $psPer === $optPer ? ' selected' : '' ?>><?= h((string)$optPer) ?> řádků</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pagination-icon mezera_mezi_4 displ_inline_flex">
        <?php $prevDisabled = $psPage <= 1; ?>
        <?php $nextDisabled = $psPage >= $psPages; ?>

        <a class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($psBaseUrl . '&ps_p=1') ?>">«</a>
        <a class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($psBaseUrl . '&ps_p=' . (string)max(1, $psPage - 1)) ?>">‹</a>

        <?php
        $pageItems = [];
        if ($psPages <= 7) {
            for ($i = 1; $i <= $psPages; $i++) {
                $pageItems[] = $i;
            }
        } elseif ($psPage <= 4) {
            $pageItems = [1, 2, 3, 4, 5, '…', $psPages];
        } elseif ($psPage >= $psPages - 3) {
            $pageItems = [1, '…', $psPages - 4, $psPages - 3, $psPages - 2, $psPages - 1, $psPages];
        } else {
            $pageItems = [1, '…', $psPage - 1, $psPage, $psPage + 1, '…', $psPages];
        }
        ?>

        <?php foreach ($pageItems as $item): ?>
          <?php if ($item === '…'): ?>
            <span class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24 disabled displ_inline_flex">…</span>
          <?php elseif ((int)$item === $psPage): ?>
            <span class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24 page-current displ_inline_flex"><?= h((string)$item) ?></span>
          <?php else: ?>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24 displ_inline_flex" href="<?= h($psBaseUrl . '&ps_p=' . (string)$item) ?>"><?= h((string)$item) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>

        <a class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($psBaseUrl . '&ps_p=' . (string)min($psPages, $psPage + 1)) ?>">›</a>
        <a class="icon-btn cursor_ruka ram_normal bg_seda text_titulek_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($psBaseUrl . '&ps_p=' . (string)$psPages) ?>">»</a>
      </div>

      <div class="per-form mezera_mezi_8 right displ_inline_flex jc_konec">
        <span>Celkem: <strong><?= h((string)$psTotal) ?></strong></span>
      </div>
    </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/prehled_smen.php * Verze: V7 * Aktualizace: 27.03.2026 */
// pocet radku 530
?>
