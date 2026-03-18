<?php
// karty/uzivatele.php * Verze: V15 * Aktualizace: 13.03.2026
declare(strict_types=1);

$uzRows = [];
$uzTotal = 0;
$uzPages = 1;
$uzPage = 1;
$uzPer = 20;
$uzAkt = '1';
$uzFilters = [];
$uzError = '';
$formAction = cb_url('/?sekce=1');
$keepExpanded = isset($_GET['uz_p']) || isset($_GET['uz_per']) || isset($_GET['uz_akt']) || isset($_GET['uz_f']);
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

$uzPerRaw = (int)($_GET['uz_per'] ?? 20);
if (in_array($uzPerRaw, [20, 50, 100], true)) {
    $uzPer = $uzPerRaw;
}

$uzPageRaw = (int)($_GET['uz_p'] ?? 1);
if ($uzPageRaw > 1) {
    $uzPage = $uzPageRaw;
}

$uzAktRaw = (string)($_GET['uz_akt'] ?? '1');
if (in_array($uzAktRaw, ['1', '0', 'all'], true)) {
    $uzAkt = $uzAktRaw;
}

$uzFiltersRaw = $_GET['uz_f'] ?? [];
if (is_array($uzFiltersRaw)) {
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

    $uzPages = max(1, (int)ceil($uzTotal / $uzPer));
    if ($uzPage > $uzPages) {
        $uzPage = $uzPages;
    }
    $offset = ($uzPage - 1) * $uzPer;

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
        ORDER BY u.id_user DESC
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

$uzBaseParams = [
    'sekce=1',
    'uz_per=' . rawurlencode((string)$uzPer),
    'uz_akt=' . rawurlencode($uzAkt),
];
foreach ($uzFilters as $key => $value) {
    if ($value === '') {
        continue;
    }
    $uzBaseParams[] = 'uz_f[' . rawurlencode($key) . ']=' . rawurlencode($value);
}
$uzBaseUrl = cb_url('/?' . implode('&', $uzBaseParams));
?>

<?php
ob_start();
?>
<p class="card_text">Zde bude přehled uživatelů.</p>
<?php
$card_min_html = (string)ob_get_clean();
$card_min_html = ''
    . '<div class="table-wrap">'
    . '  <table class="table" aria-label="Přehled uživatelů IS Comeback">'
    . '    <thead>'
    . '      <tr>'
    . '        <th>Přidělená role</th>'
    . '        <th style="text-align:right;">počet</th>'
    . '      </tr>'
    . '    </thead>'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>uživatel</td>'
    . '        <td style="text-align:right;"><strong>' . h((string)$roleStats['uzivatel']) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>manager</td>'
    . '        <td style="text-align:right;"><strong>' . h((string)$roleStats['manager']) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>admin</td>'
    . '        <td style="text-align:right;"><strong>' . h((string)$roleStats['admin']) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td><strong>Celkem</strong></td>'
    . '        <td style="text-align:right;"><strong>' . h((string)($roleStats['uzivatel'] + $roleStats['manager'] + $roleStats['admin'])) . '</strong></td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>
<?php if ($uzError !== ''): ?>
      <p class="card_text card_text_muted"><?= h($uzError) ?></p>
    <?php else: ?>
      <form method="get" action="<?= h($formAction) ?>" class="card_stack" autocomplete="off">
        <input type="hidden" name="sekce" value="1">
        <input type="hidden" name="uz_p" value="1">

        <div class="table-wrap">
          <table class="table uzivatele-table">
            <thead>
              <tr class="filter-row">
                <th class="c-id"></th>
                <th class="c-prijmeni"><input class="filter-input" type="text" name="uz_f[prijmeni]" value="<?= h($uzFilters['prijmeni'] ?? '') ?>"></th>
                <th class="c-jmeno"><input class="filter-input" type="text" name="uz_f[jmeno]" value="<?= h($uzFilters['jmeno'] ?? '') ?>"></th>
                <th class="c-telefon"><input class="filter-input" type="text" name="uz_f[telefon]" value="<?= h($uzFilters['telefon'] ?? '') ?>"></th>
                <th class="c-email"><input class="filter-input" type="text" name="uz_f[email]" value="<?= h($uzFilters['email'] ?? '') ?>"></th>
                <th class="uzivatele_filter_reset" colspan="3">
                  <div class="filter-actions">
                    <a class="icon-btn icon-x small" href="<?= h(cb_url('/?sekce=1')) ?>">×</a>
                  </div>
                </th>
              </tr>
              <tr>
                <?php foreach ($uzCols as $key => $cfg): ?>
                  <th class="c-<?= h($key) ?>"><?= h($cfg['label']) ?></th>
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
                    <td class="c-id"><?= h((string)($rowUser['id_user'] ?? '')) ?></td>
                    <td class="c-prijmeni"><?= h((string)($rowUser['prijmeni'] ?? '')) ?></td>
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
                    <td class="c-email"><?= h((string)($rowUser['email'] ?? '')) ?></td>
                    <td class="c-reg"><?= h((string)($rowUser['reg'] ?? '')) ?></td>
                    <td class="c-aktivni"><?= ((string)($rowUser['aktivni'] ?? '') === '1') ? 'Ano' : 'Ne' ?></td>
                    <td class="c-akce">
                      <span class="row-icons">
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

        <div class="list-bottom">
          <div class="per-form">
            <span>Zobrazuji</span>
            <select name="uz_per" class="filter-input per-select" onchange="this.form.uz_p.value=1; this.form.submit();">
              <option value="20"<?= $uzPer === 20 ? ' selected' : '' ?>>20 řádků</option>
              <option value="50"<?= $uzPer === 50 ? ' selected' : '' ?>>50 řádků</option>
              <option value="100"<?= $uzPer === 100 ? ' selected' : '' ?>>100 řádků</option>
            </select>
          </div>

          <div class="pagination-icon">
            <?php $prevDisabled = $uzPage <= 1; ?>
            <?php $nextDisabled = $uzPage >= $uzPages; ?>
            <a class="icon-btn w44<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($uzBaseUrl . '&uz_p=1') ?>">«</a>
            <a class="icon-btn w44<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($uzBaseUrl . '&uz_p=' . (string)max(1, $uzPage - 1)) ?>">‹</a>

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
                <span class="icon-btn w44 disabled">…</span>
              <?php elseif ((int)$item === $uzPage): ?>
                <span class="icon-btn w44 page-current"><?= h((string)$item) ?></span>
              <?php else: ?>
                <a class="icon-btn w44" href="<?= h($uzBaseUrl . '&uz_p=' . (string)$item) ?>"><?= h((string)$item) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>

            <a class="icon-btn w44<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($uzBaseUrl . '&uz_p=' . (string)min($uzPages, $uzPage + 1)) ?>">›</a>
            <a class="icon-btn w44<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($uzBaseUrl . '&uz_p=' . (string)$uzPages) ?>">»</a>
          </div>

          <div class="per-form right">
            <select name="uz_akt" class="filter-input akt-select" onchange="this.form.uz_p.value=1; this.form.submit();">
              <option value="1"<?= $uzAkt === '1' ? ' selected' : '' ?>>Aktivní</option>
              <option value="0"<?= $uzAkt === '0' ? ' selected' : '' ?>>Neaktivní</option>
              <option value="all"<?= $uzAkt === 'all' ? ' selected' : '' ?>>Vše</option>
            </select>
          </div>
        </div>
      </form>
    <?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/uzivatele.php * Verze: V15 * Aktualizace: 13.03.2026 */
?>
