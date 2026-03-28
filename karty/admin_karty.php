<?php
// karty/admin_karty.php * Verze: V7 * Aktualizace: 12.03.2026
declare(strict_types=1);

$cbUser = $_SESSION['cb_user'] ?? [];
$idRole = (int)($cbUser['id_role'] ?? 0);
$isAdmin = ($idRole === 1);
$formAction = cb_url('/');

$cbMsg = '';
$cbMsgErr = false;
$keepExpanded = false;
$karetCount = 0;
$lastAddedName = '-';
$nextCardId = 1;
$souborOptions = [];
$usedSouborMap = [];
$activeCards = [];
$inactiveCards = [];
$tableColsHtml = '';
$tableHeadHtml = '';
$sekceStats = [
    3 => ['all' => 0, 'on' => 0, 'off' => 0],
    2 => ['all' => 0, 'on' => 0, 'off' => 0],
    1 => ['all' => 0, 'on' => 0, 'off' => 0],
];

$isName = static function (string $v): bool {
    return (bool)preg_match('~^[a-z0-9_]{2,80}$~', $v);
};

$normalizeSoubor = static function (string $v) use ($isName): string {
    $s = trim(str_replace('\\', '/', $v));
    if ($s === '') {
        return '';
    }

    $s = basename($s);
    $s = preg_replace('~\.php$~i', '', $s) ?: '';
    if (!$isName($s)) {
        return '';
    }

    return $s;
};

if ($isAdmin && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cb_admin_karty_action'])) {
    $action = trim((string)$_POST['cb_admin_karty_action']);
    $keepExpanded = true;

    try {
        $conn = db();

        if ($action === 'add') {
            $nazev = trim((string)($_POST['nazev'] ?? ''));
            $soubor = $normalizeSoubor((string)($_POST['soubor'] ?? ''));
            $minRole = (int)($_POST['min_role'] ?? 3);
            $poradi = (int)($_POST['poradi'] ?? 100);

            if ($nazev === '' || $soubor === '') {
                throw new RuntimeException('Neplatna vstupni data.');
            }

            $stmt = $conn->prepare('
                INSERT INTO karty (nazev, soubor, min_role, poradi, aktivni, zalozeno, upraveno)
                VALUES (?, ?, ?, ?, 1, NOW(), NOW())
            ');
            if (!$stmt) {
                throw new RuntimeException('DB prepare selhal.');
            }
            $stmt->bind_param('ssii', $nazev, $soubor, $minRole, $poradi);
            $stmt->execute();
            $stmt->close();

            $cbMsg = 'Karta byla pridana.';
        } elseif ($action === 'save') {
            $idKarta = (int)($_POST['id_karta'] ?? 0);
            $nazev = trim((string)($_POST['nazev'] ?? ''));
            $soubor = $normalizeSoubor((string)($_POST['soubor'] ?? ''));
            $minRole = (int)($_POST['min_role'] ?? 3);
            $poradi = (int)($_POST['poradi'] ?? 100);

            if ($idKarta <= 0 || $nazev === '' || $soubor === '') {
                throw new RuntimeException('Neplatna vstupni data.');
            }

            $stmt = $conn->prepare('
                UPDATE karty
                SET nazev=?, soubor=?, min_role=?, poradi=?, upraveno=NOW()
                WHERE id_karta=?
                LIMIT 1
            ');
            if (!$stmt) {
                throw new RuntimeException('DB prepare selhal.');
            }
            $stmt->bind_param('ssiii', $nazev, $soubor, $minRole, $poradi, $idKarta);
            $stmt->execute();
            $stmt->close();

            $cbMsg = 'Zmena byla ulozena.';
        } elseif ($action === 'disable' || $action === 'enable') {
            $idKarta = (int)($_POST['id_karta'] ?? 0);
            $aktivni = ($action === 'enable') ? 1 : 0;

            if ($idKarta <= 0) {
                throw new RuntimeException('Neplatne ID karty.');
            }

            $stmt = $conn->prepare('
                UPDATE karty
                SET aktivni=?, upraveno=NOW()
                WHERE id_karta=?
                LIMIT 1
            ');
            if (!$stmt) {
                throw new RuntimeException('DB prepare selhal.');
            }
            $stmt->bind_param('ii', $aktivni, $idKarta);
            $stmt->execute();
            $stmt->close();

            $cbMsg = ($aktivni === 1) ? 'Karta byla povolena.' : 'Karta byla zakazana.';
        } elseif ($action === 'move_up' || $action === 'move_down') {
            $idKarta = (int)($_POST['id_karta'] ?? 0);
            if ($idKarta <= 0) {
                throw new RuntimeException('Neplatne ID karty.');
            }

            $conn->begin_transaction();

            try {
                $stmtCur = $conn->prepare('
                    SELECT id_karta, poradi
                    FROM karty
                    WHERE id_karta=? AND aktivni=1
                    LIMIT 1
                    FOR UPDATE
                ');
                if (!$stmtCur) {
                    throw new RuntimeException('DB prepare selhal.');
                }
                $stmtCur->bind_param('i', $idKarta);
                $stmtCur->execute();
                $stmtCur->bind_result($curId, $curPoradi);
                if (!$stmtCur->fetch()) {
                    $stmtCur->close();
                    throw new RuntimeException('Karta nebyla nalezena.');
                }
                $stmtCur->close();

                if ($action === 'move_up') {
                    $stmtNbr = $conn->prepare('
                        SELECT id_karta, poradi
                        FROM karty
                        WHERE aktivni=1
                          AND ((poradi < ?) OR (poradi = ? AND id_karta < ?))
                        ORDER BY poradi DESC, id_karta DESC
                        LIMIT 1
                        FOR UPDATE
                    ');
                } else {
                    $stmtNbr = $conn->prepare('
                        SELECT id_karta, poradi
                        FROM karty
                        WHERE aktivni=1
                          AND ((poradi > ?) OR (poradi = ? AND id_karta > ?))
                        ORDER BY poradi ASC, id_karta ASC
                        LIMIT 1
                        FOR UPDATE
                    ');
                }
                if (!$stmtNbr) {
                    throw new RuntimeException('DB prepare selhal.');
                }
                $stmtNbr->bind_param('iii', $curPoradi, $curPoradi, $curId);
                $stmtNbr->execute();
                $stmtNbr->bind_result($nbrId, $nbrPoradi);
                $hasNbr = $stmtNbr->fetch();
                $stmtNbr->close();

                if ($hasNbr) {
                    $stmtSwap = $conn->prepare('
                        UPDATE karty
                        SET poradi=?, upraveno=NOW()
                        WHERE id_karta=?
                        LIMIT 1
                    ');
                    if (!$stmtSwap) {
                        throw new RuntimeException('DB prepare selhal.');
                    }

                    $stmtSwap->bind_param('ii', $nbrPoradi, $curId);
                    $stmtSwap->execute();

                    $stmtSwap->bind_param('ii', $curPoradi, $nbrId);
                    $stmtSwap->execute();
                    $stmtSwap->close();
                }

                $conn->commit();
                $cbMsg = 'Poradi bylo upraveno.';
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
    } catch (Throwable $e) {
        $cbMsg = $e->getMessage();
        $cbMsgErr = true;
    }
}

try {
    $conn = db();

    $res = $conn->query('SELECT COUNT(*) AS cnt FROM karty');
    if ($res) {
        $row = $res->fetch_assoc();
        $karetCount = (int)($row['cnt'] ?? 0);
        $res->free();
    }

    $resMax = $conn->query('SELECT COALESCE(MAX(id_karta), 0) AS max_id FROM karty');
    if ($resMax) {
        $rowMax = $resMax->fetch_assoc();
        $nextCardId = ((int)($rowMax['max_id'] ?? 0)) + 1;
        $resMax->free();
    }

    $resLast = $conn->query('SELECT nazev FROM karty ORDER BY id_karta DESC LIMIT 1');
    if ($resLast) {
        $rowLast = $resLast->fetch_assoc();
        $lastAddedName = trim((string)($rowLast['nazev'] ?? ''));
        if ($lastAddedName === '') {
            $lastAddedName = '-';
        }
        $resLast->free();
    }

    $resStats = $conn->query('
        SELECT
            SUM(min_role = 1) AS r1_all,
            SUM(min_role = 1 AND aktivni = 1) AS r1_on,
            SUM(min_role = 1 AND aktivni = 0) AS r1_off,
            SUM(min_role = 2) AS r2_all,
            SUM(min_role = 2 AND aktivni = 1) AS r2_on,
            SUM(min_role = 2 AND aktivni = 0) AS r2_off,
            SUM(min_role = 3) AS r3_all,
            SUM(min_role = 3 AND aktivni = 1) AS r3_on,
            SUM(min_role = 3 AND aktivni = 0) AS r3_off
        FROM karty
    ');
    if ($resStats) {
        $rowStats = $resStats->fetch_assoc();
        $sekceStats[1]['all'] = (int)($rowStats['r1_all'] ?? 0);
        $sekceStats[1]['on'] = (int)($rowStats['r1_on'] ?? 0);
        $sekceStats[1]['off'] = (int)($rowStats['r1_off'] ?? 0);
        $sekceStats[2]['all'] = (int)($rowStats['r2_all'] ?? 0);
        $sekceStats[2]['on'] = (int)($rowStats['r2_on'] ?? 0);
        $sekceStats[2]['off'] = (int)($rowStats['r2_off'] ?? 0);
        $sekceStats[3]['all'] = (int)($rowStats['r3_all'] ?? 0);
        $sekceStats[3]['on'] = (int)($rowStats['r3_on'] ?? 0);
        $sekceStats[3]['off'] = (int)($rowStats['r3_off'] ?? 0);
        $resStats->free();
    }

    $resUsed = $conn->query('SELECT soubor FROM karty');
    if ($resUsed) {
        while ($rowUsed = $resUsed->fetch_assoc()) {
            $used = trim((string)($rowUsed['soubor'] ?? ''));
            if ($used !== '') {
                $usedSouborMap[strtolower($used)] = true;
            }
        }
        $resUsed->free();
    }

    $resCards = $conn->query('
        SELECT id_karta, nazev, soubor, min_role, poradi, aktivni
        FROM karty
        ORDER BY aktivni DESC, poradi ASC, id_karta ASC
    ');
    if ($resCards) {
        while ($rowCard = $resCards->fetch_assoc()) {
            $item = [
                'id_karta' => (int)($rowCard['id_karta'] ?? 0),
                'nazev' => (string)($rowCard['nazev'] ?? ''),
                'soubor' => (string)($rowCard['soubor'] ?? ''),
                'min_role' => (int)($rowCard['min_role'] ?? 0),
                'poradi' => (int)($rowCard['poradi'] ?? 0),
                'aktivni' => (int)($rowCard['aktivni'] ?? 0),
            ];

            if ($item['aktivni'] === 1) {
                $activeCards[] = $item;
            } else {
                $inactiveCards[] = $item;
            }
        }
        $resCards->free();
    }
} catch (Throwable $e) {
    $karetCount = 0;
    $lastAddedName = '-';
    $nextCardId = 1;
    $usedSouborMap = [];
    $activeCards = [];
    $inactiveCards = [];
    if ($cbMsg === '') {
        $cbMsg = 'Nacteni karet selhalo.';
        $cbMsgErr = true;
    }
}

try {
    $dir = __DIR__;
    $all = scandir($dir);
    if (is_array($all)) {
        foreach ($all as $f) {
            if (!is_string($f) || $f === '.' || $f === '..') {
                continue;
            }
            if (!preg_match('~^[a-z0-9_]{2,80}\.php$~i', $f)) {
                continue;
            }
            $name = preg_replace('~\.php$~i', '', $f);
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (isset($usedSouborMap[strtolower($name)])) {
                continue;
            }
            $souborOptions[] = $name;
        }
    }
    sort($souborOptions, SORT_NATURAL | SORT_FLAG_CASE);
} catch (Throwable $e) {
    $souborOptions = [];
}

$tableColsHtml = implode("\n", [
    '            <colgroup>',
    '              <col class="admin_karty_col_id">',
    '              <col class="admin_karty_col_nazev">',
    '              <col class="admin_karty_col_soubor">',
    '              <col class="admin_karty_col_role">',
    '              <col class="admin_karty_col_poradi">',
    '              <col class="admin_karty_col_aktivni">',
    '              <col class="admin_karty_col_akce">',
    '            </colgroup>',
]);
$tableHeadHtml = implode("\n", [
    '            <tr>',
    '              <th class="admin_karty_col_id">ID</th>',
    '              <th class="admin_karty_col_nazev">Nadpis</th>',
    '              <th class="admin_karty_col_soubor">Soubor</th>',
    '              <th class="admin_karty_col_role">Min role</th>',
    '              <th class="admin_karty_col_poradi">Pořadí</th>',
    '              <th class="admin_karty_col_aktivni">Aktivní</th>',
    '              <th class="admin_karty_col_akce">Akce</th>',
    '            </tr>',
]);
?>

<?php
ob_start();
?>
<div class="table-wrap">
  <table class="table card_table_min" aria-label="Přehled dashboard karet">
    <thead>
      <tr>
        <th>Sekce</th>
        <th style="text-align:right;">celkem/on/off</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>home</td>
        <td style="text-align:right;"><strong><?= h((string)$sekceStats[3]['all']) ?>/<?= h((string)$sekceStats[3]['on']) ?>/<?= h((string)$sekceStats[3]['off']) ?></strong></td>
      </tr>
      <tr>
        <td>manager</td>
        <td style="text-align:right;"><strong><?= h((string)$sekceStats[2]['all']) ?>/<?= h((string)$sekceStats[2]['on']) ?>/<?= h((string)$sekceStats[2]['off']) ?></strong></td>
      </tr>
      <tr>
        <td>admin</td>
        <td style="text-align:right;"><strong><?= h((string)$sekceStats[1]['all']) ?>/<?= h((string)$sekceStats[1]['on']) ?>/<?= h((string)$sekceStats[1]['off']) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();
$startExpanded = $keepExpanded;

ob_start();
?>
    <form id="cb-karta-add" method="post" action="<?= h($formAction) ?>" autocomplete="off">
      <input type="hidden" name="cb_admin_karty_action" value="add">
    </form>
    <div class="table-wrap">
      <table class="table admin_karty_table card_table_max">
<?= $tableColsHtml . "\n" ?>
        <tbody>
          <tr>
            <th colspan="7" class="admin_karty_list_title">Přidání nové karty včetně názvu souboru, pořadí a minimální role.</th>
          </tr>
<?= $tableHeadHtml . "\n" ?>
          <tr>
            <td><?= h((string)$nextCardId) ?></td>
            <td>
              <input class="card_input" name="nazev" type="text" required maxlength="120" placeholder="např. Správa karet" form="cb-karta-add">
            </td>
            <td>
              <select class="card_select" name="soubor" required form="cb-karta-add">
                <option value="">Vyber soubor...</option>
                <?php foreach ($souborOptions as $opt): ?>
                  <option value="<?= h($opt) ?>"><?= h($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <select class="card_select" name="min_role" required form="cb-karta-add">
                <option value="1">admin</option>
                <option value="2">manager</option>
                <option value="3" selected>všichni</option>
              </select>
            </td>
            <td>
              <input class="card_input" name="poradi" type="number" min="1" max="9999" value="100" required form="cb-karta-add">
            </td>
            <td>0</td>
            <td>
              <button class="admin_karty_btn" type="submit" form="cb-karta-add">Přidat kartu</button>
            </td>
          </tr>
          <tr>
            <td colspan="7" class="admin_karty_sep"></td>
          </tr>
          <tr>
            <th colspan="7" class="admin_karty_list_title">Seznam aktivních karet</th>
          </tr>
<?= $tableHeadHtml . "\n" ?>
          <?php if (!$activeCards): ?>
            <tr><td colspan="7">Zatim nejsou zadne aktivni karty.</td></tr>
          <?php else: ?>
            <?php foreach ($activeCards as $card): ?>
              <?php $formId = 'cb-karta-' . (string)$card['id_karta']; ?>
              <tr>
                <td><?= h((string)$card['id_karta']) ?></td>
                <td>
                  <form id="<?= h($formId) ?>" method="post" action="<?= h($formAction) ?>">
                    <input type="hidden" name="id_karta" value="<?= h((string)$card['id_karta']) ?>">
                  </form>
                  <input type="text" class="card_input" name="nazev" value="<?= h($card['nazev']) ?>" maxlength="120" form="<?= h($formId) ?>">
                </td>
                <td><input type="text" class="card_input" name="soubor" value="<?= h($card['soubor']) ?>" maxlength="80" form="<?= h($formId) ?>"></td>
                <td>
                  <select class="card_select" name="min_role" form="<?= h($formId) ?>">
                    <option value="1"<?= $card['min_role'] === 1 ? ' selected' : '' ?>>admin</option>
                    <option value="2"<?= $card['min_role'] === 2 ? ' selected' : '' ?>>manager</option>
                    <option value="3"<?= $card['min_role'] === 3 ? ' selected' : '' ?>>všichni</option>
                  </select>
                </td>
                <td><input type="number" class="card_input" name="poradi" value="<?= h((string)$card['poradi']) ?>" min="1" max="9999" form="<?= h($formId) ?>"></td>
                <td>ano</td>
                <td>
                  <button class="admin_karty_btn admin_karty_btn_icon" type="submit" name="cb_admin_karty_action" value="move_up" form="<?= h($formId) ?>">&uarr;</button>
                  <button class="admin_karty_btn admin_karty_btn_icon" type="submit" name="cb_admin_karty_action" value="move_down" form="<?= h($formId) ?>">&darr;</button>
                  <button class="admin_karty_btn" type="submit" name="cb_admin_karty_action" value="save" form="<?= h($formId) ?>">Uložit</button>
                  <button class="admin_karty_btn admin_karty_btn_danger" type="submit" name="cb_admin_karty_action" value="disable" form="<?= h($formId) ?>">Zakázat</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          <tr>
            <td colspan="7" class="admin_karty_sep"></td>
          </tr>
          <tr>
            <th colspan="7" class="admin_karty_list_title">Neaktivní karty</th>
          </tr>
<?= $tableHeadHtml . "\n" ?>
          <?php if (!$inactiveCards): ?>
            <tr><td colspan="7">Nejsou žádné neaktivní karty</td></tr>
          <?php else: ?>
            <?php foreach ($inactiveCards as $card): ?>
              <?php $formId = 'cb-karta-inactive-' . (string)$card['id_karta']; ?>
              <tr>
                <td><?= h((string)$card['id_karta']) ?></td>
                <td><?= h($card['nazev']) ?></td>
                <td><?= h($card['soubor']) ?></td>
                <td><?= h((string)$card['min_role']) ?></td>
                <td><?= h((string)$card['poradi']) ?></td>
                <td>ne</td>
                <td>
                  <form id="<?= h($formId) ?>" method="post" action="<?= h($formAction) ?>">
                    <input type="hidden" name="id_karta" value="<?= h((string)$card['id_karta']) ?>">
                  </form>
                  <button class="admin_karty_btn admin_karty_btn_danger" type="submit" name="cb_admin_karty_action" value="enable" form="<?= h($formId) ?>">Povolit</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="cb-admin-karty-msg admin_karty_msg <?= $cbMsgErr ? 'admin_karty_msg_err' : 'admin_karty_msg_ok' ?>" aria-live="polite">
      <br> <strong>&nbsp;&nbsp;Poslední akce:</strong> <?= h($cbMsg) ?>
    </div>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/admin_karty.php * Verze: V7 * Aktualizace: 12.03.2026 */
?>
