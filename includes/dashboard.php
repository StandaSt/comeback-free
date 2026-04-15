<?php
// includes/dashboard.php * Verze: V16 * Aktualizace: 15.04.2026
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
        $cardRow = [
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

        if ($singleCardId > 0 && (int)$cardRow['id_karta'] !== $singleCardId) {
            continue;
        }

        $karty[] = $cardRow;
    }
    $stmt->close();
}

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
        }
        $renderItems[] = [
            'kind' => 'nano_group',
            'karty' => $nanoSkupinaPrepared,
        ];
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
    }
}

$miniStartSlot = ($nanoKde === 1 && !empty($kartyNano))
    ? 2
    : (count($kartyNano) + 1);
$kartyMini = $applyUserSlots($kartyMini, $miniStartSlot);

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
}

if ($singleCardId > 0) {
    foreach ($renderItems as $renderItem) {
        if (($renderItem['kind'] ?? '') === 'nano_group') {
            foreach ((array)($renderItem['karty'] ?? []) as $nanoCardPrepared) {
                if ((int)($nanoCardPrepared['cardId'] ?? 0) === $singleCardId) {
                    echo cb_zobraz_kartu($nanoCardPrepared);
                    return;
                }
            }
            continue;
        }

        if (($renderItem['kind'] ?? '') === 'card') {
            $singleCard = (array)($renderItem['karta'] ?? []);
            if ((int)($singleCard['cardId'] ?? 0) === $singleCardId) {
                echo cb_zobraz_kartu($singleCard);
                return;
            }
        }
    }

    http_response_code(404);
    echo '<section class="card odstup_vnitrni_14"><p>Pozadovana karta neexistuje.</p></section>';
    return;
}
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
