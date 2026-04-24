<?php
// K9
// karty/zakaznici.php * Verze: V11 * Aktualizace: 27.03.2026
declare(strict_types=1);

/*
 * Karta "Zakaznici":
 * - nacita seznam zakazniku,
 * - umi filtrovani a strankovani v max rezimu,
 * - mini rezim ponechava jen souhrn.
 */

// === KONFIG TABULKY: ZAKAZNICI ===
$tabKonfig = [
    'enable_filters' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
];

$totalZak = 0;
$activeZak = 0;
$blockedZak = 0;
$zakRows = [];
$zakTotal = 0;
$zakPages = 1;
$zakPage = 1;
$zakPer = (int)$tabKonfig['default_per'];
$zakBlk = '0';
$zakFilters = [];
$zakError = '';
$formAction = cb_url('/');

$zakPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($zakPerOptions === []) {
    $zakPerOptions = [20, 50, 100];
}

$zakPerRaw = (int)($_GET['zak_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($zakPerRaw, $zakPerOptions, true)) {
    $zakPer = $zakPerRaw;
}

$zakPageRaw = (int)($_GET['zak_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $zakPageRaw > 1) {
    $zakPage = $zakPageRaw;
}

$zakBlkRaw = (string)($_GET['zak_blk'] ?? '0');
if (in_array($zakBlkRaw, ['0', '1'], true)) {
    $zakBlk = $zakBlkRaw;
}

$zakFiltersRaw = $_GET['zak_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($zakFiltersRaw)) {
    $zakFilters['prijmeni'] = trim((string)($zakFiltersRaw['prijmeni'] ?? ''));
    $zakFilters['jmeno'] = trim((string)($zakFiltersRaw['jmeno'] ?? ''));
    $zakFilters['telefon'] = trim((string)($zakFiltersRaw['telefon'] ?? ''));
    $zakFilters['email'] = trim((string)($zakFiltersRaw['email'] ?? ''));
    $zakFilters['ulice'] = trim((string)($zakFiltersRaw['ulice'] ?? ''));
    $zakFilters['mesto'] = trim((string)($zakFiltersRaw['mesto'] ?? ''));
    $zakFilters['pobocka'] = trim((string)($zakFiltersRaw['pobocka'] ?? ''));
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $selectedPobocky = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    $selectedPobocky = array_values(array_filter(array_map('intval', $selectedPobocky), static function (int $v): bool {
        return $v > 0;
    }));
    $selectedWhere = '';
    if ($selectedPobocky) {
        $selectedWhere = ' WHERE z.id_pob IN (' . implode(',', $selectedPobocky) . ')';
    }

    $sqlCount = '
        SELECT
            COUNT(*) AS total_cnt,
            SUM(CASE WHEN blokovany = 0 THEN 1 ELSE 0 END) AS active_cnt,
            SUM(CASE WHEN blokovany = 1 THEN 1 ELSE 0 END) AS blocked_cnt
        FROM zakaznik z
    ' . $selectedWhere;
    $resCount = $conn->query($sqlCount);
    if ($resCount) {
        $row = $resCount->fetch_assoc() ?: [];
        $totalZak = (int)($row['total_cnt'] ?? 0);
        $activeZak = (int)($row['active_cnt'] ?? 0);
        $blockedZak = (int)($row['blocked_cnt'] ?? 0);
        $resCount->free();
    }

    $where = [];
    if ($selectedPobocky) {
        $where[] = 'z.id_pob IN (' . implode(',', $selectedPobocky) . ')';
    }

    $where[] = 'z.blokovany = ' . (int)$zakBlk;

    if ((int)$tabKonfig['enable_filters'] === 1) {
        if (($zakFilters['prijmeni'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['prijmeni']);
            $where[] = "COALESCE(z.prijmeni, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['jmeno'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['jmeno']);
            $where[] = "COALESCE(z.jmeno, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['telefon'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['telefon']);
            $where[] = "COALESCE(z.telefon, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['email'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['email']);
            $where[] = "COALESCE(z.email, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['ulice'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['ulice']);
            $where[] = "COALESCE(z.ulice, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['mesto'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['mesto']);
            $where[] = "COALESCE(z.mesto, '') LIKE '%" . $safe . "%'";
        }
        if (($zakFilters['pobocka'] ?? '') !== '') {
            $safe = $conn->real_escape_string((string)$zakFilters['pobocka']);
            $where[] = "COALESCE(p.kod, '') LIKE '%" . $safe . "%'";
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countSql = '
        SELECT COUNT(*)
        FROM zakaznik z
        LEFT JOIN pobocka p ON p.id_pob = z.id_pob
    ' . $whereSql;
    $resZakCount = $conn->query($countSql);
    if ($resZakCount) {
        $rowCount = $resZakCount->fetch_row();
        $zakTotal = (int)($rowCount[0] ?? 0);
        $resZakCount->free();
    }

    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $zakPages = max(1, (int)ceil($zakTotal / $zakPer));
        if ($zakPage > $zakPages) {
            $zakPage = $zakPages;
        }
        $offset = ($zakPage - 1) * $zakPer;
    } else {
        $zakPages = 1;
        $zakPage = 1;
        $zakPer = max(1, $zakTotal);
        $offset = 0;
    }

    $dataSql = '
        SELECT
            z.id_zak,
            z.prijmeni,
            z.jmeno,
            COALESCE(z.telefon, "") AS telefon,
            COALESCE(z.email, "") AS email,
            COALESCE(z.ulice, "") AS ulice,
            COALESCE(z.mesto, "") AS mesto,
            COALESCE(p.kod, "") AS pobocka,
            z.posledni_obj,
            z.blokovany
        FROM zakaznik z
        LEFT JOIN pobocka p ON p.id_pob = z.id_pob
    ' . $whereSql . '
        ORDER BY z.id_zak DESC
        LIMIT ' . (int)$zakPer . ' OFFSET ' . (int)$offset;

    $resZak = $conn->query($dataSql);
    if ($resZak) {
        while ($rowZak = $resZak->fetch_assoc()) {
            $zakRows[] = $rowZak;
        }
        $resZak->free();
    }
} catch (Throwable $e) {
    $totalZak = 0;
    $activeZak = 0;
    $blockedZak = 0;
    $zakRows = [];
    $zakTotal = 0;
    $zakPages = 1;
    $zakPage = 1;
    $zakError = 'Načtení zákazníků selhalo.';
}

$zakQueryDefaults = [
    'zak_p' => '1',
    'zak_per' => (string)$tabKonfig['default_per'],
    'zak_blk' => '0',
];
$zakBaseParams = [
    'cb_load_max' => '1',
    'zak_per' => (string)$zakPer,
    'zak_blk' => $zakBlk,
];
if ((int)$tabKonfig['enable_filters'] === 1 && $zakFilters !== []) {
    $zakBaseParams['zak_f'] = $zakFilters;
}
$zakBuildUrl = static function (array $extra = []) use ($zakBaseParams, $zakQueryDefaults): string {
    return cb_url_query('/', array_merge($zakBaseParams, $extra), $zakQueryDefaults);
};
$zakResetUrl = cb_url_query('/', ['cb_load_max' => '1'], $zakQueryDefaults);
?>

<?php
ob_start();
?>
<div class="displ_flex jc_stred">
  <table class="table ram_normal bg_bila radek_1_35 card_table_min sirka100">
    <tbody>
      <tr>
        <td>Zákazníků v DB</td>
        <td class="txt_r"><strong><?= h((string)$totalZak) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();
// --- Kompaktni verze pro MAX TABULKA VZOR ---
// $card_max_html = ... zde je pouze HTML pro max-rezim tabulky zakazniku
ob_start();
?>
<?php if ($zakError !== ''): ?>
  <div style="color:#b91c1c; font-size:14px; padding:8px 0;">
    <?= h($zakError) ?>
  </div>
<?php else: ?>
<form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-max-form="1">
  <input type="hidden" name="cb_load_max" value="1">
  <input type="hidden" name="zak_p" value="1">
  <div style="margin-bottom:12px; font-size:14px;">
    Zákazníků v DB: <strong><?= h((string)$totalZak) ?></strong>
  </div>
  <div class="table-wrap ram_normal bg_bila zaobleni_12">
    <table class="card-max-table">
      <thead>
        <tr class="card-max-filter filter-row">
          <th style="min-width:65px;"></th>
          <th><input class="filter-input" type="text" name="zak_f[prijmeni]" value="<?= h($zakFilters['prijmeni'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[jmeno]" value="<?= h($zakFilters['jmeno'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[telefon]" value="<?= h($zakFilters['telefon'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[email]" value="<?= h($zakFilters['email'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[ulice]" value="<?= h($zakFilters['ulice'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[mesto]" value="<?= h($zakFilters['mesto'] ?? '') ?>" autocomplete="off"></th>
          <th><input class="filter-input" type="text" name="zak_f[pobocka]" value="<?= h($zakFilters['pobocka'] ?? '') ?>" autocomplete="off"></th>
          <th>
            <a href="<?= h($zakResetUrl) ?>" class="icon-btn cursor_ruka ram_normal bg_seda text_18 icon-x small zaobleni_6 vyska_24 radek_24 displ_inline_flex">&times;</a>
          </th>
        </tr>
        <tr>
          <th style="width:90px;">Poř.č.</th>
          <th style="width:120px;">příjmení</th>
          <th style="width:120px;">jméno</th>
          <th style="width:150px;">telefon</th>
          <th style="width:auto;">email</th>
          <th style="width:150px;">ulice</th>
          <th style="width:150px;">město</th>
          <th style="width:120px;">pobočka</th>
          <th style="width:120px;">aktivita</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$zakRows): ?>
          <tr><td colspan="9" style="text-align:center; padding:22px 0; color:#888;">Žádná data</td></tr>
        <?php else: ?>
          <?php foreach ($zakRows as $rowZak): ?>
            <?php
              $telefon = preg_replace('~[^\d\+]~u', '', trim((string)($rowZak['telefon'] ?? '')));
              if ($telefon === '') {
                  $telefon = '-';
              } elseif (preg_match('~^\+(\d{1,3})(\d{9})$~', $telefon, $m)) {
                  $telefon = '+' . $m[1] . ' ' . substr($m[2], 0, 3) . ' ' . substr($m[2], 3, 3) . ' ' . substr($m[2], 6, 3);
              } elseif (preg_match('~^\d{9}$~', $telefon)) {
                  $telefon = substr($telefon, 0, 3) . ' ' . substr($telefon, 3, 3) . ' ' . substr($telefon, 6, 3);
              }
              $aktivita = trim((string)($rowZak['posledni_obj'] ?? ''));
              if ($aktivita === '' || $aktivita === '0000-00-00' || $aktivita === '0000-00-00 00:00:00') {
                  $aktivita = '';
              } else {
                  $ts = strtotime($aktivita);
                  if ($ts !== false) {
                      $aktivita = date('j.n.Y', $ts);
                  }
              }
            ?>
            <tr>
              <td><?= h((string)($rowZak['id_zak'] ?? '')) ?></td>
              <td><?= h((string)($rowZak['prijmeni'] ?? '')) ?></td>
              <td><?= h((string)($rowZak['jmeno'] ?? '')) ?></td>
              <td><?= h($telefon) ?></td>
              <td><?= h((string)($rowZak['email'] ?? '')) ?></td>
              <td><?= h((string)($rowZak['ulice'] ?? '')) ?></td>
              <td><?= h((string)($rowZak['mesto'] ?? '')) ?></td>
              <td><?= h((string)($rowZak['pobocka'] ?? '')) ?></td>
              <td><?= h($aktivita) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
  <div class="card-max-pagination list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
    <div class="per-form gap_8 displ_inline_flex">
      <span>Zobrazuji</span>
      <select name="zak_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.zak_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
        <option value="20"<?= $zakPer === 20 ? ' selected' : '' ?>>20 řádků</option>
        <option value="50"<?= $zakPer === 50 ? ' selected' : '' ?>>50 řádků</option>
        <option value="100"<?= $zakPer === 100 ? ' selected' : '' ?>>100 řádků</option>
      </select>
    </div>
    <div class="pagination-icon gap_4 displ_inline_flex">
      <?php $prevDisabled = $zakPage <= 1; ?>
      <?php $nextDisabled = $zakPage >= $zakPages; ?>
      <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($zakBuildUrl(['zak_p' => '1'])) ?>">«</a>
      <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)max(1, $zakPage - 1)])) ?>">‹</a>
      <?php
      $pageItems = [];
      if ($zakPages <= 7) {
          for ($i = 1; $i <= $zakPages; $i++) {
              $pageItems[] = $i;
          }
      } elseif ($zakPage <= 4) {
          $pageItems = [1, 2, 3, 4, 5, '…', $zakPages];
      } elseif ($zakPage >= $zakPages - 3) {
          $pageItems = [1, '…', $zakPages - 4, $zakPages - 3, $zakPages - 2, $zakPages - 1, $zakPages];
      } else {
          $pageItems = [1, '…', $zakPage - 1, $zakPage, $zakPage + 1, '…', $zakPages];
      }
      ?>
      <?php foreach ($pageItems as $item): ?>
        <?php if ($item === '…'): ?>
          <span class="icon-btn disabled">…</span>
        <?php elseif ((int)$item === $zakPage): ?>
          <span class="icon-btn page-current"><?= h((string)$item) ?></span>
        <?php else: ?>
          <a class="icon-btn" href="<?= h($zakBuildUrl(['zak_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)min($zakPages, $zakPage + 1)])) ?>">›</a>
      <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)$zakPages])) ?>">»</a>
    </div>
    <div class="per-form gap_8 right displ_inline_flex jc_konec">
      <input type="hidden" name="zak_blk" value="0">
      <label style="align-items:center; white-space:nowrap; cursor:pointer;">
        <input type="checkbox" name="zak_blk" value="1"<?= $zakBlk === '1' ? ' checked' : '' ?> onchange="this.form.zak_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}" style="vertical-align:middle; margin-right:6px;">
        <span>blokovaní (<?= h((string)$blockedZak) ?>)</span>
      </label>
    </div>
  </div>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
// --- konec max tabulka vzor ---
?>
