<?php
// K8
// karty/uzivatele.php * Verze: V17 * Aktualizace: 27.03.2026
declare(strict_types=1);

/*
 * Karta "Uzivatele":
 * - nacita uzivatele z DB,
 * - v max rezimu umi filtry a strankovani,
 * - mini rezim je jen souhrn.
 */

// === KONFIG TABULKY: UZIVATELE ===
$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
];

if (!function_exists('uz_format_reg_cz')) {
    function uz_format_reg_cz(string $value): string
    {
        $v = trim($value);
        if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
            return '';
        }
        $dt = date_create($v);
        if ($dt === false) {
            return $v;
        }
        return date_format($dt, 'j.n.Y H:i');
    }
}

$uzRows = [];
$uzTotal = 0;
$uzPages = 1;
$uzPage = 1;
$uzPer = (int)$tabKonfig['default_per'];
$uzAkt = '1';
$uzFilters = [];
$uzError = '';
$formAction = cb_url('/');
$roleStats = ['admin' => 0, 'manager' => 0, 'uzivatel' => 0];

$uzCols = [
    'id' => ['label' => 'Poř.č.', 'db' => 'id_user'],
    'prijmeni' => ['label' => 'příjmení', 'db' => 'prijmeni', 'filter' => true],
    'jmeno' => ['label' => 'jméno', 'db' => 'jmeno', 'filter' => true],
    'telefon' => ['label' => 'telefon', 'db' => 'telefon', 'filter' => true],
    'email' => ['label' => 'email', 'db' => 'email', 'filter' => true],
    'reg' => ['label' => 'registrován', 'db' => 'reg'],
    'aktivni' => ['label' => 'aktivní', 'db' => 'aktivni'],
    'akce' => ['label' => 'detaily uživatele', 'db' => 'akce'],
];

// Whitelist sloupcu pro trideni.
$uzSortMap = [
    'id' => 'u.id_user',
    'prijmeni' => 'COALESCE(u.prijmeni, "")',
    'jmeno' => 'COALESCE(u.jmeno, "")',
    'telefon' => 'COALESCE(u.telefon, "")',
    'email' => 'COALESCE(u.email, "")',
    'reg' => 'u.vytvoren_smeny',
    'aktivni' => 'u.aktivni',
];
$uzSortRaw = trim((string)($_GET['uz_sort'] ?? 'id'));
$uzDirRaw = strtoupper(trim((string)($_GET['uz_dir'] ?? 'DESC')));
$uzSort = 'id';
$uzDir = 'DESC';
if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($uzSortRaw, $uzSortMap)) {
    $uzSort = $uzSortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($uzDirRaw, ['ASC', 'DESC'], true)) {
    $uzDir = $uzDirRaw;
}

$uzPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($uzPerOptions === []) {
    $uzPerOptions = [20, 50, 100];
}

$uzPerRaw = (int)($_GET['uz_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($uzPerRaw, $uzPerOptions, true)) {
    $uzPer = $uzPerRaw;
}

$uzPageRaw = (int)($_GET['uz_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $uzPageRaw > 1) {
    $uzPage = $uzPageRaw;
}

$uzAktRaw = (string)($_GET['uz_akt'] ?? '1');
if (in_array($uzAktRaw, ['1', '0', 'all'], true)) {
    $uzAkt = $uzAktRaw;
}

$uzFiltersRaw = $_GET['uz_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($uzFiltersRaw)) {
    foreach ($uzCols as $key => $cfg) {
        if (empty($cfg['filter'])) {
            continue;
        }
        $uzFilters[$key] = trim((string)($uzFiltersRaw[$key] ?? ''));
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $where = [];
    if ($uzAkt !== 'all') {
        $where[] = 'u.aktivni = ' . (int)$uzAkt;
    }

    if ((int)$tabKonfig['enable_filters'] === 1) {
        foreach ($uzFilters as $key => $value) {
            if ($value === '') {
                continue;
            }

            $safe = $conn->real_escape_string($value);
            if ($key === 'id') {
                $intValue = (int)$value;
                if ($intValue > 0) {
                    $where[] = 'u.id_user = ' . $intValue;
                }
                continue;
            }

            $dbKey = match ($key) {
                'prijmeni' => 'prijmeni',
                'jmeno' => 'jmeno',
                'telefon' => 'telefon',
                'email' => 'email',
                default => '',
            };
            if ($dbKey === '') {
                continue;
            }
            $where[] = "COALESCE(u.`" . $dbKey . "`, '') LIKE '%" . $safe . "%'";
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countSql = 'SELECT COUNT(*) FROM `user` u' . $whereSql;
    $resCount = $conn->query($countSql);
    if ($resCount) {
        $rowCount = $resCount->fetch_row();
        $uzTotal = (int)($rowCount[0] ?? 0);
        $resCount->free();
    }

    $resRoleStats = $conn->query('
        SELECT
            SUM(id_role = 1) AS admin_cnt,
            SUM(id_role = 2) AS manager_cnt,
            SUM(id_role = 3) AS uzivatel_cnt
        FROM `user`
    ');
    if ($resRoleStats) {
        $rowRoleStats = $resRoleStats->fetch_assoc();
        $roleStats['admin'] = (int)($rowRoleStats['admin_cnt'] ?? 0);
        $roleStats['manager'] = (int)($rowRoleStats['manager_cnt'] ?? 0);
        $roleStats['uzivatel'] = (int)($rowRoleStats['uzivatel_cnt'] ?? 0);
        $resRoleStats->free();
    }

    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $uzPages = max(1, (int)ceil($uzTotal / $uzPer));
        if ($uzPage > $uzPages) {
            $uzPage = $uzPages;
        }
        $offset = ($uzPage - 1) * $uzPer;
    } else {
        $uzPages = 1;
        $uzPage = 1;
        $uzPer = max(1, $uzTotal);
        $offset = 0;
    }

    $orderSql = 'u.id_user DESC';
    if ((int)$tabKonfig['enable_sort'] === 1) {
        $orderSql = $uzSortMap[$uzSort] . ' ' . $uzDir . ', u.id_user DESC';
    }

    $sql = '
        SELECT
            u.id_user,
            u.prijmeni,
            u.jmeno,
            COALESCE(u.telefon, "") AS telefon,
            COALESCE(u.email, "") AS email,
            u.vytvoren_smeny AS reg,
            u.aktivni
        FROM `user` u
    ' . $whereSql . '
        ORDER BY ' . $orderSql . '
        LIMIT ' . (int)$uzPer . ' OFFSET ' . (int)$offset;

    $resUsers = $conn->query($sql);
    if ($resUsers) {
        while ($rowUser = $resUsers->fetch_assoc()) {
            $uzRows[] = $rowUser;
        }
        $resUsers->free();
    }
} catch (Throwable $e) {
    $uzRows = [];
    $uzTotal = 0;
    $uzPages = 1;
    $uzPage = 1;
    $uzError = 'Načtení uživatelů selhalo.';
}

$uzQueryDefaults = [
    'uz_p' => '1',
    'uz_per' => (string)$tabKonfig['default_per'],
    'uz_akt' => '1',
    'uz_sort' => 'id',
    'uz_dir' => 'DESC',
];
$uzBaseParams = [
    'uz_per' => (string)$uzPer,
    'uz_akt' => $uzAkt,
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $uzBaseParams['uz_sort'] = $uzSort;
    $uzBaseParams['uz_dir'] = $uzDir;
}
if ((int)$tabKonfig['enable_filters'] === 1 && $uzFilters !== []) {
    $uzBaseParams['uz_f'] = $uzFilters;
}
$uzBuildUrl = static function (array $extra = []) use ($uzBaseParams, $uzQueryDefaults): string {
    return cb_url_query('/', array_merge($uzBaseParams, $extra), $uzQueryDefaults);
};
?>

<?php
ob_start();
?>
<div class="displ_flex jc_stred">
  <table class="table ram_normal bg_bila radek_1_35 card_table_min sirka100" aria-label="Přehled uživatelů IS Comeback">
    <thead>
      <tr>
        <th>Přidělená role</th>
        <th class="txt_r">počet</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>uživatel</td>
        <td class="txt_r"><strong><?= h((string)$roleStats['uzivatel']) ?></strong></td>
      </tr>
      <tr>
        <td>manager</td>
        <td class="txt_r"><strong><?= h((string)$roleStats['manager']) ?></strong></td>
      </tr>
      <tr>
        <td>admin</td>
        <td class="txt_r"><strong><?= h((string)$roleStats['admin']) ?></strong></td>
      </tr>
      <tr>
        <td><strong>Celkem</strong></td>
        <td class="txt_r"><strong><?= h((string)($roleStats['uzivatel'] + $roleStats['manager'] + $roleStats['admin'])) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<?php if ($uzError !== ''): ?>
      <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($uzError) ?></p>
    <?php else: ?>
      <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off">
        <input type="hidden" name="uz_p" value="1">
        <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
          <input type="hidden" name="uz_sort" value="<?= h($uzSort) ?>">
          <input type="hidden" name="uz_dir" value="<?= h($uzDir) ?>">
        <?php endif; ?>

        <div class="table-wrap ram_normal bg_bila zaobleni_12">
          <table class="table ram_normal bg_bila radek_1_35 uzivatele-table card_table_max">
            <thead>
              <tr class="filter-row">
                <th class="c-id"></th>
                <th class="c-prijmeni"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" type="text" name="uz_f[prijmeni]" value="<?= h($uzFilters['prijmeni'] ?? '') ?>"></th>
                <th class="c-jmeno"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" type="text" name="uz_f[jmeno]" value="<?= h($uzFilters['jmeno'] ?? '') ?>"></th>
                <th class="c-telefon"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" type="text" name="uz_f[telefon]" value="<?= h($uzFilters['telefon'] ?? '') ?>"></th>
                <th class="c-email"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" type="text" name="uz_f[email]" value="<?= h($uzFilters['email'] ?? '') ?>"></th>
                <th class="uzivatele_filter_reset txt_l" colspan="3">
                  <div class="filter-actions gap_8 displ_flex">
                    <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 icon-x small zaobleni_6 vyska_24 radek_24 displ_inline_flex" href="<?= h($formAction) ?>">&times;</a>
                  </div>
                </th>
              </tr>
              <tr>
                <?php foreach ($uzCols as $key => $cfg): ?>
                  <?php
                  $isSortable = isset($uzSortMap[$key]);
                  $isActiveSort = ($uzSort === $key);
                  $arrow = '↕';
                  if ($isActiveSort) {
                      $arrow = $uzDir === 'ASC' ? '↑' : '↓';
                  }
                  ?>
                  <?php $thRight = in_array($key, ['id', 'prijmeni', 'email', 'aktivni'], true); ?>
                  <th class="c-<?= h($key) ?> th-sort<?= $isActiveSort ? ' active' : '' ?><?= $thRight ? ' txt_r' : '' ?>">
                    <?php if ((int)$tabKonfig['enable_sort'] === 1 && $isSortable): ?>
                      <?php
                      $nextDir = ($isActiveSort && $uzDir === 'ASC') ? 'DESC' : 'ASC';
                      $sortUrl = $uzBuildUrl([
                          'uz_p' => '1',
                          'uz_sort' => $key,
                          'uz_dir' => $nextDir,
                      ]);
                      ?>
                      <a class="th-sort-link gap_8 jc_mezi sirka100<?= $isActiveSort ? ' active' : '' ?>" href="<?= h($sortUrl) ?>">
                        <span class="th-sort-label"><?= h($cfg['label']) ?></span>
                        <span class="th-sort-arrow txt_r"><?= h($arrow) ?></span>
                      </a>
                    <?php else: ?>
                      <span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label"><?= h($cfg['label']) ?></span></span>
                    <?php endif; ?>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$uzRows): ?>
                <tr>
                  <td colspan="<?= h((string)count($uzCols)) ?>">Žádná data</td>
                </tr>
              <?php else: ?>
                <?php foreach ($uzRows as $rowUser): ?>
                  <tr>
                    <td class="c-id txt_r"><?= h((string)($rowUser['id_user'] ?? '')) ?></td>
                    <td class="c-prijmeni txt_r"><?= h((string)($rowUser['prijmeni'] ?? '')) ?></td>
                    <td class="c-jmeno"><?= h((string)($rowUser['jmeno'] ?? '')) ?></td>
                    <td class="c-telefon">
                      <?php
                      $phoneValue = trim((string)($rowUser['telefon'] ?? ''));
                      if ($phoneValue === '') {
                          $phoneValue = '-';
                      } else {
                          $phoneDigits = preg_replace('~[^\d\+]~u', '', $phoneValue);
                          $phoneValue = is_string($phoneDigits) ? $phoneDigits : $phoneValue;
                          if (preg_match('~^\+(\d{1,3})(\d{9})$~', $phoneValue, $m)) {
                              $phoneValue = '+' . $m[1] . ' ' . substr($m[2], 0, 3) . ' ' . substr($m[2], 3, 3) . ' ' . substr($m[2], 6, 3);
                          } elseif (preg_match('~^\d{9}$~', $phoneValue)) {
                              $phoneValue = substr($phoneValue, 0, 3) . ' ' . substr($phoneValue, 3, 3) . ' ' . substr($phoneValue, 6, 3);
                          }
                      }
                      ?>
                      <?= h($phoneValue) ?>
                    </td>
                    <td class="c-email txt_r"><?= h((string)($rowUser['email'] ?? '')) ?></td>
                    <td class="c-reg"><?= h(uz_format_reg_cz((string)($rowUser['reg'] ?? ''))) ?></td>
                    <td class="c-aktivni txt_r"><?= ((string)($rowUser['aktivni'] ?? '') === '1') ? 'Ano' : 'Ne' ?></td>
                    <td class="c-akce">
                      <span class="row-icons gap_10">
                        <img src="<?= h(cb_url('img/icons/search.svg')) ?>" alt="Detail uživatele">
                        <img src="<?= h(cb_url('img/icons/calendar.svg')) ?>" alt="Směny">
                        <img src="<?= h(cb_url('img/icons/clock-3.svg')) ?>" alt="Hodiny">
                        <img src="<?= h(cb_url('img/icons/key.svg')) ?>" alt="Loginy">
                        <img src="<?= h(cb_url('img/icons/role.svg')) ?>" alt="Práva a pozice">
                        <img src="<?= h(cb_url('img/icons/graf.svg')) ?>" alt="Aktivita">
                        <img src="<?= h(cb_url('img/icons/notes.svg')) ?>" alt="Poznámka">
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
        <div class="list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
          <div class="per-form gap_8 displ_inline_flex">
            <span>Zobrazuji</span>
            <select name="uz_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.uz_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
              <option value="20"<?= $uzPer === 20 ? ' selected' : '' ?>>20 řádků</option>
              <option value="50"<?= $uzPer === 50 ? ' selected' : '' ?>>50 řádků</option>
              <option value="100"<?= $uzPer === 100 ? ' selected' : '' ?>>100 řádků</option>
            </select>
          </div>

          <div class="pagination-icon gap_4 displ_inline_flex">
            <?php $prevDisabled = $uzPage <= 1; ?>
            <?php $nextDisabled = $uzPage >= $uzPages; ?>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($uzBuildUrl(['uz_p' => '1'])) ?>">«</a>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($uzBuildUrl(['uz_p' => (string)max(1, $uzPage - 1)])) ?>">‹</a>

            <?php
            $pageItems = [];
            if ($uzPages <= 7) {
                for ($i = 1; $i <= $uzPages; $i++) {
                    $pageItems[] = $i;
                }
            } elseif ($uzPage <= 4) {
                $pageItems = [1, 2, 3, 4, 5, '…', $uzPages];
            } elseif ($uzPage >= $uzPages - 3) {
                $pageItems = [1, '…', $uzPages - 4, $uzPages - 3, $uzPages - 2, $uzPages - 1, $uzPages];
            } else {
                $pageItems = [1, '…', $uzPage - 1, $uzPage, $uzPage + 1, '…', $uzPages];
            }
            ?>
            <?php foreach ($pageItems as $item): ?>
              <?php if ($item === '…'): ?>
                <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 disabled displ_inline_flex">…</span>
              <?php elseif ((int)$item === $uzPage): ?>
                <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 page-current displ_inline_flex"><?= h((string)$item) ?></span>
              <?php else: ?>
                <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 displ_inline_flex" href="<?= h($uzBuildUrl(['uz_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>

            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($uzBuildUrl(['uz_p' => (string)min($uzPages, $uzPage + 1)])) ?>">›</a>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($uzBuildUrl(['uz_p' => (string)$uzPages])) ?>">»</a>
          </div>

          <div class="per-form gap_8 right displ_inline_flex jc_konec">
            <select name="uz_akt" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 akt-select sirka_min_160" onchange="this.form.uz_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
              <option value="1"<?= $uzAkt === '1' ? ' selected' : '' ?>>Aktivní</option>
              <option value="0"<?= $uzAkt === '0' ? ' selected' : '' ?>>Neaktivní</option>
              <option value="all"<?= $uzAkt === 'all' ? ' selected' : '' ?>>Vše</option>
            </select>
          </div>
        </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/uzivatele.php * Verze: V17 * Aktualizace: 27.03.2026 */
// pocet radku 450
?>
