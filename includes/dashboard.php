<?php
// includes/dashboard.php * Verze: V18 * Aktualizace: 15.04.2026
declare(strict_types=1);

function cb_dashboard_resolve_file(string $soubor): ?string
{
    $raw = trim(str_replace('\\', '/', $soubor));
    if ($raw === '') {
        return null;
    }

    $name = basename($raw);
    $name = preg_replace('~\.php$~i', '', $name) ?: '';
    if (!preg_match('~^[a-z0-9_]{2,80}$~', $name)) {
        return null;
    }

    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        return null;
    }

    $full = realpath($base . '/karty/' . $name . '.php');
    if ($full === false || !is_file($full)) {
        return null;
    }
    if (strpos($full, $base) !== 0) {
        return null;
    }

    return $full;
}

require_once __DIR__ . '/priprav_kartu_nano.php';
require_once __DIR__ . '/priprav_kartu_mini.php';
require_once __DIR__ . '/priprav_kartu_max.php';
require_once __DIR__ . '/../funkce/zobraz_kartu.php';

$cbDashTimingStart = microtime(true);
$cbDashTimingLast = $cbDashTimingStart;
$cbDashTimingUri = (string)($_SERVER['REQUEST_URI'] ?? '');
$cbDashTimingPath = (string)(parse_url($cbDashTimingUri, PHP_URL_PATH) ?? '');
$cbDashBasePath = trim(str_replace('\\', '/', (string)($GLOBALS['BASE_PATH'] ?? '')));
if ($cbDashBasePath !== '' && $cbDashBasePath !== '/') {
    $cbDashBasePath = '/' . trim($cbDashBasePath, '/');
} else {
    $cbDashBasePath = '';
}
$cbDashTimingAllowed = (
    isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])
    || isset($_SERVER['HTTP_X_COMEBACK_CARD'])
    || $cbDashTimingPath === '/'
    || $cbDashTimingPath === '/index.php'
    || (
        $cbDashBasePath !== ''
        && (
            $cbDashTimingPath === $cbDashBasePath
            || $cbDashTimingPath === $cbDashBasePath . '/'
            || $cbDashTimingPath === $cbDashBasePath . '/index.php'
        )
    )
);
$GLOBALS['cbDashTimingAllowed'] = $cbDashTimingAllowed;

if (!function_exists('cb_dashboard_timing_log')) {
    function cb_dashboard_timing_log(string $label, float $startTs, float &$lastTs): void
    {
        if (empty($GLOBALS['cbDashTimingAllowed'])) {
            return;
        }

        $idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
        if ($idUser !== 1) {
            return;
        }

        $now = microtime(true);
        $stepMs = (int)round(($now - $lastTs) * 1000);
        $totalMs = (int)round(($now - $startTs) * 1000);
        $lastTs = $now;

        $dir = __DIR__ . '/../log';
        $fileAi = $dir . '/merime_casy_AI.txt';
        $fileUser = $dir . '/merime_casy_user.txt';
        @mkdir($dir, 0775, true);

        $lineUser = sprintf(
            "%s | dashboard | %s / total_ms=%d\n",
            date('Y-m-d H:i:s'),
            $label,
            $totalMs
        );

        $lineAi = sprintf(
            "%s | dashboard | %s / total_ms=%d%s  step_ms=%d%s  uri=%s%s  partial=%s%s  card=%s%s  card_id=%s%s%s",
            date('Y-m-d H:i:s'),
            $label,
            $totalMs,
            PHP_EOL,
            $stepMs,
            PHP_EOL,
            (string)($_SERVER['REQUEST_URI'] ?? ''),
            PHP_EOL,
            isset($_SERVER['HTTP_X_COMEBACK_PARTIAL']) ? '1' : '0',
            PHP_EOL,
            isset($_SERVER['HTTP_X_COMEBACK_CARD']) ? '1' : '0',
            PHP_EOL,
            (string)($GLOBALS['cb_dashboard_single_card_id'] ?? 0),
            PHP_EOL,
            PHP_EOL,
            PHP_EOL
        );

        @file_put_contents($fileUser, $lineUser, FILE_APPEND | LOCK_EX);
        @file_put_contents($fileAi, $lineAi, FILE_APPEND | LOCK_EX);
    }
}

cb_dashboard_timing_log('start', $cbDashTimingStart, $cbDashTimingLast);

$roleFilter = (int)(($_SESSION['cb_user']['id_role'] ?? 3));
if (!in_array($roleFilter, [1, 2, 3], true)) {
    $roleFilter = 3;
}

$idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
$dashColsClass = 'dash_cols_3';
$dashGridCols = 3;
$nanoKde = 0;
$nanoCardIds = [];
$userCardHeaderColorById = [];
$userCardIconFileById = [];
$userCardPosById = [];
$dashColsPostOverride = null;
$singleCardId = (int)($GLOBALS['cb_dashboard_single_card_id'] ?? 0);
$singleCardLoadMax = (((int)($_GET['cb_load_max'] ?? 0)) === 1);

if ((string)($_POST['us_action'] ?? '') === 'save') {
    $postColsRaw = (int)($_POST['us_pocet_sl'] ?? 0);
    if (in_array($postColsRaw, [3, 4, 5], true)) {
        $dashColsPostOverride = $postColsRaw;
    }
}

if ($idUser > 0) {
    $stmtCols = db()->prepare('SELECT pocet_sl, nano_kde FROM `user_set` WHERE id_user = ? LIMIT 1');
    if ($stmtCols) {
        $stmtCols->bind_param('i', $idUser);
        $stmtCols->execute();
        $stmtCols->bind_result($pocetSl, $nanoKdeDb);
        if ($stmtCols->fetch()) {
            $effectivePocetSl = ($dashColsPostOverride !== null) ? (int)$dashColsPostOverride : (int)$pocetSl;
            if ($effectivePocetSl === 5) {
                $dashColsClass = 'dash_cols_5';
                $dashGridCols = 5;
            } elseif ($effectivePocetSl === 4) {
                $dashColsClass = 'dash_cols_4';
                $dashGridCols = 4;
            }
        }
        $nanoKde = (int)$nanoKdeDb;
        if (!in_array($nanoKde, [0, 1], true)) {
            $nanoKde = 0;
        }
        $stmtCols->close();
    }
    cb_dashboard_timing_log('after_user_set', $cbDashTimingStart, $cbDashTimingLast);

    $stmtNano = db()->prepare('SELECT id_nano FROM user_nano WHERE id_user = ?');
    if ($stmtNano) {
        $stmtNano->bind_param('i', $idUser);
        $stmtNano->execute();
        $stmtNano->bind_result($nanoKartaId);
        while ($stmtNano->fetch()) {
            $idNano = (int)$nanoKartaId;
            if ($idNano > 0) {
                $nanoCardIds[$idNano] = true;
            }
        }
        $stmtNano->close();
    }
    cb_dashboard_timing_log('after_nano_ids', $cbDashTimingStart, $cbDashTimingLast);

    $stmtCardColor = db()->prepare('SELECT id_karta, color FROM user_card_set WHERE id_user = ?');
    if ($stmtCardColor) {
        $stmtCardColor->bind_param('i', $idUser);
        $stmtCardColor->execute();
        $stmtCardColor->bind_result($colorCardId, $colorValue);
        while ($stmtCardColor->fetch()) {
            $cid = (int)$colorCardId;
            $cval = trim((string)$colorValue);
            if ($cid > 0 && $cval !== '') {
                $userCardHeaderColorById[$cid] = $cval;
            }
        }
        $stmtCardColor->close();
    }
    cb_dashboard_timing_log('after_card_colors', $cbDashTimingStart, $cbDashTimingLast);

    $stmtCardIcon = db()->prepare('
        SELECT ucs.id_karta, ci.soubor
        FROM user_card_set ucs
        LEFT JOIN card_icons ci ON ci.id_ikon = ucs.ikon
        WHERE ucs.id_user = ?
    ');
    if ($stmtCardIcon) {
        $stmtCardIcon->bind_param('i', $idUser);
        $stmtCardIcon->execute();
        $stmtCardIcon->bind_result($iconCardId, $iconFile);
        while ($stmtCardIcon->fetch()) {
            $cid = (int)$iconCardId;
            $ifile = trim((string)$iconFile);
            if ($cid > 0 && $ifile !== '') {
                $userCardIconFileById[$cid] = $ifile;
            }
        }
        $stmtCardIcon->close();
    }
    cb_dashboard_timing_log('after_card_icons', $cbDashTimingStart, $cbDashTimingLast);

    $stmtCardPos = db()->prepare('SELECT id_karta, col, line FROM user_card_set WHERE id_user = ?');
    if ($stmtCardPos) {
        $stmtCardPos->bind_param('i', $idUser);
        $stmtCardPos->execute();
        $stmtCardPos->bind_result($posCardId, $posCol, $posLine);
        while ($stmtCardPos->fetch()) {
            $cid = (int)$posCardId;
            $cc = ($posCol === null) ? null : (int)$posCol;
            $ll = ($posLine === null) ? null : (int)$posLine;
            if ($cid > 0) {
                $userCardPosById[$cid] = [
                    'col' => ($cc !== null && $cc > 0) ? $cc : null,
                    'line' => ($ll !== null && $ll > 0) ? $ll : null,
                ];
            }
        }
        $stmtCardPos->close();
    }
    cb_dashboard_timing_log('after_card_positions', $cbDashTimingStart, $cbDashTimingLast);
}

$karty = [];
$stmt = db()->prepare('
    SELECT id_karta, nazev, subtitle_min, subtitle_max, soubor, min_role, aktivni, poradi, refresh_op
    FROM karty
    WHERE aktivni = 1
      AND min_role >= ?
    ORDER BY poradi ASC, id_karta ASC
');

if ($stmt) {
    $stmt->bind_param('i', $roleFilter);
    $stmt->execute();
    $stmt->bind_result($idKarta, $nazev, $subtitleMin, $subtitleMax, $soubor, $minRole, $aktivni, $poradi, $refreshOp);
    while ($stmt->fetch()) {
        $karty[] = [
            'id_karta' => (int)$idKarta,
            'nazev' => (string)$nazev,
            'subtitle_min' => (string)$subtitleMin,
            'subtitle_max' => (string)$subtitleMax,
            'soubor' => (string)$soubor,
            'min_role' => (int)$minRole,
            'aktivni' => (int)$aktivni,
            'poradi' => (int)$poradi,
            'refresh_op' => (int)$refreshOp,
        ];
    }
    $stmt->close();
}
cb_dashboard_timing_log('after_cards_query', $cbDashTimingStart, $cbDashTimingLast);

$emptyMap = [
    3 => 'Zadna karta k zobrazeni',
    2 => 'Zadna karta k zobrazeni',
    1 => 'Zadna karta k zobrazeni',
];
$emptyText = (string)($emptyMap[$roleFilter] ?? $emptyMap[3]);

$kartyNano = [];
$kartyMini = [];
foreach ($karty as $kartaRow) {
    $idK = (int)($kartaRow['id_karta'] ?? 0);
    if ($idK > 0 && isset($nanoCardIds[$idK])) {
        $kartyNano[] = $kartaRow;
    } else {
        $kartyMini[] = $kartaRow;
    }
}
cb_dashboard_timing_log('after_split_cards', $cbDashTimingStart, $cbDashTimingLast);

$sortByFallback = static function (array &$list): void {
    usort($list, static function (array $a, array $b): int {
        $idA = (int)($a['id_karta'] ?? 0);
        $idB = (int)($b['id_karta'] ?? 0);
        $poradiA = (int)($a['poradi'] ?? 0);
        $poradiB = (int)($b['poradi'] ?? 0);
        if ($poradiA !== $poradiB) {
            return $poradiA <=> $poradiB;
        }
        return $idA <=> $idB;
    });
};

$applyUserSlots = static function (array $list, int $startSlot = 1) use ($userCardPosById, $dashGridCols, $sortByFallback): array {
    if (count($list) <= 1) {
        foreach ($list as &$singleCard) {
            if (!is_array($singleCard)) {
                continue;
            }
            $idK = (int)($singleCard['id_karta'] ?? 0);
            $pos = ($idK > 0 && isset($userCardPosById[$idK])) ? (array)$userCardPosById[$idK] : ['col' => null, 'line' => null];
            $storedCol = (int)($pos['col'] ?? 0);
            $storedLine = (int)($pos['line'] ?? 0);
            if ($storedCol > 0 && $storedLine > 0) {
                $singleCard['__render_pos'] = (((max($storedLine, 1) - 1) * (($dashGridCols > 0) ? $dashGridCols : 3)) + $storedCol);
            } else {
                $singleCard['__render_pos'] = ($startSlot > 0) ? $startSlot : 1;
            }
        }
        unset($singleCard);
        return $list;
    }

    $cols = ($dashGridCols > 0) ? $dashGridCols : 3;

    $lockedBySlot = [];
    $unlocked = [];
    $lowestLockedSlot = null;

    foreach ($list as $card) {
        $idK = (int)($card['id_karta'] ?? 0);
        $pos = ($idK > 0 && isset($userCardPosById[$idK])) ? (array)$userCardPosById[$idK] : ['col' => null, 'line' => null];

        $col = ($pos['col'] ?? null);
        $line = ($pos['line'] ?? null);

        $hasLock = ($col !== null && $line !== null && (int)$col > 0 && (int)$line > 0 && (int)$col <= $cols);

        if ($hasLock) {
            $slot = (((int)$line - 1) * $cols) + (int)$col;

            if (!isset($lockedBySlot[$slot])) {
                $lockedBySlot[$slot] = $card;
                if ($lowestLockedSlot === null || $slot < $lowestLockedSlot) {
                    $lowestLockedSlot = $slot;
                }
                continue;
            }
        }

        $unlocked[] = $card;
    }

    if (!empty($unlocked)) {
        $sortByFallback($unlocked);
    }

    $result = [];
    $unlockIdx = 0;

    $slot = ($startSlot > 0) ? $startSlot : 1;
    if ($lowestLockedSlot !== null && $lowestLockedSlot > 0 && $lowestLockedSlot < $slot) {
        $slot = $lowestLockedSlot;
    }
    $placed = 0;
    $total = count($list);

    while ($placed < $total) {
        if (isset($lockedBySlot[$slot])) {
            $card = $lockedBySlot[$slot];
            if (is_array($card)) {
                $card['__render_pos'] = $slot;
            }
            $result[] = $card;
            $placed++;
            $slot++;
            continue;
        }

        if (isset($unlocked[$unlockIdx])) {
            $card = $unlocked[$unlockIdx];
            if (is_array($card)) {
                $card['__render_pos'] = $slot;
            }
            $result[] = $card;
            $unlockIdx++;
            $placed++;
            $slot++;
            continue;
        }

        $slot++;
    }

    return $result;
};

$dashGridClass = $dashColsClass . ' dash_nano_kde_' . $nanoKde;
$cbLoginId = (int)($_SESSION['cb_id_login'] ?? 0);

$miniStartSlot = ($nanoKde === 1 && !empty($kartyNano))
    ? 2
    : (count($kartyNano) + 1);
$kartyMini = $applyUserSlots($kartyMini, $miniStartSlot);
cb_dashboard_timing_log('after_apply_slots', $cbDashTimingStart, $cbDashTimingLast);

if ($singleCardId > 0) {
    foreach ($kartyNano as $kartaNanoRaw) {
        if ((int)($kartaNanoRaw['id_karta'] ?? 0) !== $singleCardId) {
            continue;
        }

        echo cb_zobraz_kartu(cb_priprav_kartu_nano(
            $kartaNanoRaw,
            $userCardHeaderColorById,
            $userCardIconFileById,
            $userCardPosById,
            $dashGridCols
        ));
        cb_dashboard_timing_log('single_card_nano_render', $cbDashTimingStart, $cbDashTimingLast);
        return;
    }

    foreach ($kartyMini as $kartaMiniRaw) {
        if ((int)($kartaMiniRaw['id_karta'] ?? 0) !== $singleCardId) {
            continue;
        }

        $pripravenaMini = cb_priprav_kartu_mini(
            $kartaMiniRaw,
            $userCardHeaderColorById,
            $userCardIconFileById,
            $userCardPosById,
            $dashGridCols
        );
        cb_dashboard_timing_log('single_card_mini_prepare', $cbDashTimingStart, $cbDashTimingLast);

        if ($singleCardLoadMax) {
            $pripravenaMini['maxHtml'] = cb_priprav_kartu_max($kartaMiniRaw);
            cb_dashboard_timing_log('single_card_max_prepare', $cbDashTimingStart, $cbDashTimingLast);
        }

        echo cb_zobraz_kartu($pripravenaMini);
        cb_dashboard_timing_log('single_card_render', $cbDashTimingStart, $cbDashTimingLast);
        return;
    }

    http_response_code(404);
    echo '<section class="card odstup_vnitrni_14"><p>Pozadovana karta neexistuje.</p></section>';
    return;
}

$renderItems = [];
if ($nanoKde === 1) {
    foreach (array_chunk($kartyNano, 9) as $nanoSkupinaRaw) {
        $nanoSkupinaPrepared = [];
        foreach ($nanoSkupinaRaw as $kartaNanoRaw) {
            $nanoSkupinaPrepared[] = cb_priprav_kartu_nano(
                $kartaNanoRaw,
                $userCardHeaderColorById,
                $userCardIconFileById,
                $userCardPosById,
                $dashGridCols
            );
            cb_dashboard_timing_log('nano_prepare_card_' . (string)($kartaNanoRaw['id_karta'] ?? 0), $cbDashTimingStart, $cbDashTimingLast);
        }
        $renderItems[] = [
            'kind' => 'nano_group',
            'karty' => $nanoSkupinaPrepared,
        ];
        cb_dashboard_timing_log('nano_group_ready', $cbDashTimingStart, $cbDashTimingLast);
    }
} else {
    foreach ($kartyNano as $kartaNanoRaw) {
        $renderItems[] = [
            'kind' => 'card',
            'karta' => cb_priprav_kartu_nano(
                $kartaNanoRaw,
                $userCardHeaderColorById,
                $userCardIconFileById,
                $userCardPosById,
                $dashGridCols
            ),
        ];
        cb_dashboard_timing_log('nano_prepare_card_' . (string)($kartaNanoRaw['id_karta'] ?? 0), $cbDashTimingStart, $cbDashTimingLast);
    }
}
cb_dashboard_timing_log('after_nano_render_items', $cbDashTimingStart, $cbDashTimingLast);

if ($nanoKde === 0 && !empty($kartyNano) && !empty($kartyMini)) {
    $renderItems[] = ['kind' => 'break'];
}

foreach ($kartyMini as $kartaMiniRaw) {
    $renderItems[] = [
        'kind' => 'card',
        'karta' => cb_priprav_kartu_mini(
            $kartaMiniRaw,
            $userCardHeaderColorById,
            $userCardIconFileById,
            $userCardPosById,
            $dashGridCols
        ),
    ];
    cb_dashboard_timing_log('mini_prepare_card_' . (string)($kartaMiniRaw['id_karta'] ?? 0), $cbDashTimingStart, $cbDashTimingLast);
}
cb_dashboard_timing_log('after_mini_render_items', $cbDashTimingStart, $cbDashTimingLast);
?>

<div class="dash_grid gap_2 <?= h($dashGridClass) ?> displ_grid sirka100" data-login-id="<?= h((string)$cbLoginId) ?>">
  <?php if (empty($karty)): ?>
    <section class="dash_card ram_normal bg_bila card_blue zaobleni_12" data-cb-dash-card="1">
      <div class="dash_card_body">
        <p class="small_note text_12"><?= h($emptyText) ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($renderItems as $renderItem): ?>
      <?php
      if (($renderItem['kind'] ?? '') === 'break') {
          echo '<div class="dash_break odstup_vnejsi_0 odstup_vnitrni_0" aria-hidden="true"></div>';
          continue;
      }

      if (($renderItem['kind'] ?? '') === 'nano_group') {
          echo '<div class="dash_nano_group">';
          foreach ((array)($renderItem['karty'] ?? []) as $kartaNanoPrepared) {
              echo cb_zobraz_kartu((array)$kartaNanoPrepared);
          }
          echo '</div>';
          continue;
      }

      echo cb_zobraz_kartu((array)($renderItem['karta'] ?? []));
      ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php cb_dashboard_timing_log('after_html_output', $cbDashTimingStart, $cbDashTimingLast); ?>

<div id="cbCardModeModal" class="cb_cardmode_modal is-hidden" data-cb-cardmode-overlay="1" aria-hidden="true">
  <div class="cb_cardmode_dialog">
    <div class="cb_cardmode_head">
      <div class="cb_cardmode_logo_wrap">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback" class="cb_cardmode_logo">
      </div>
      <div class="cb_cardmode_head_text">
        <h4 class="cb_cardmode_title">Upozornění systému</h4>
        <p class="cb_cardmode_text" data-cb-cardmode-msg=""></p>
      </div>
    </div>
    <div class="cb_cardmode_actions">
      <button type="button" class="btn cb_cardmode_btn is-hidden" data-cb-cardmode-confirm>Potvrdit</button>
      <button type="button" class="btn cb_cardmode_btn" data-cb-cardmode-close>Rozumím</button>
    </div>
  </div>
</div>

<?php
// Konec souboru
cb_dashboard_timing_log('total_end', $cbDashTimingStart, $cbDashTimingLast);
