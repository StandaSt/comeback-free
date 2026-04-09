<?php
// includes/dashboard.php * Verze: V10 * Aktualizace: 02.04.2026
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
    SELECT id_karta, nazev, subtitle_min, subtitle_max, soubor, min_role, aktivni, poradi
    FROM karty
    WHERE aktivni = 1
      AND min_role >= ?
    ORDER BY poradi ASC, id_karta ASC
');

if ($stmt) {
    $stmt->bind_param('i', $roleFilter);
    $stmt->execute();
    $stmt->bind_result($idKarta, $nazev, $subtitleMin, $subtitleMax, $soubor, $minRole, $aktivni, $poradi);
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
        ];
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
    // zde se nastavuje počet karet v gridu pro nano karty
    foreach (array_chunk($kartyNano, 9) as $nanoSkupina) {
        $renderItems[] = [
            'kind' => 'nano_group',
            'karty' => $nanoSkupina,
        ];
    }
} else {
    foreach ($kartyNano as $kartaNano) {
        $renderItems[] = [
            'kind' => 'card',
            'karta' => $kartaNano,
            'is_nano' => true,
            'grid_style' => '',
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

foreach ($kartyMini as $kartaMini) {
    $renderItems[] = [
        'kind' => 'card',
        'karta' => $kartaMini,
        'is_nano' => false,
        'grid_style' => '',
    ];
}

$renderCard = static function (array $karta, bool $isNano, string $gridStyle = '') use ($userCardHeaderColorById, $userCardIconFileById, $userCardPosById, $dashGridCols): string {
    $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
    $title = (string)($karta['nazev'] ?? '');
    $subtitleMin = (string)($karta['subtitle_min'] ?? '');
    $subtitleMax = (string)($karta['subtitle_max'] ?? '');
    $cardId = (int)($karta['id_karta'] ?? 0);
    $cardMode = $isNano ? 'nano' : 'mini';
    $cardPoradi = (int)($karta['poradi'] ?? 0);
    $renderPos = $isNano ? 0 : (int)($karta['__render_pos'] ?? 0);
    $renderCol = 0;
    $renderLine = 0;
    if (!$isNano && $renderPos > 0) {
        $cols = ($dashGridCols > 0) ? $dashGridCols : 3;
        $renderCol = (($renderPos - 1) % $cols) + 1;
        $renderLine = (int)floor(($renderPos - 1) / $cols) + 1;
    }
    $cardLineHeightClass = $isNano ? ' radek_1_1' : ' radek_1_15';
    $cardTopStyle = '';
    if ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) {
        $cardTopStyle = 'background:' . (string)$userCardHeaderColorById[$cardId] . ';';
    }
    $cardIconFile = ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '';
    $hasCardIcon = ($cardIconFile !== '');
    $cardIconSrc = $hasCardIcon ? cb_url('/img/card_icons/' . ltrim($cardIconFile, '/')) : '';
    $cardColorUrl = cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId);
    $cardIconUrl = cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId);
    $storedPos = ($cardId > 0 && isset($userCardPosById[$cardId])) ? (array)$userCardPosById[$cardId] : ['col' => null, 'line' => null];
    $isPosLocked = (!$isNano && ($storedPos['col'] ?? null) !== null && ($storedPos['line'] ?? null) !== null);
    if ($isPosLocked) {
        $storedCol = (int)($storedPos['col'] ?? 0);
        $storedLine = (int)($storedPos['line'] ?? 0);
        if ($storedCol > 0 && $storedLine > 0) {
            $renderCol = $storedCol;
            $renderLine = $storedLine;
        }
    }
    $gridStyle = '';
    if ($renderCol > 0 && $renderLine > 0) {
        $gridStyle = 'grid-column:' . $renderCol . ';grid-row:' . $renderLine . ';';
    }

    $minRole = (int)($karta['min_role'] ?? 3);
    $cardTopRoleClass = '';
    if ($minRole === 1) {
        $cardTopRoleClass = ' card_top_role_1';
    } elseif ($minRole === 2) {
        $cardTopRoleClass = ' card_top_role_2';
    }

    $card_min_html = '';
    $card_max_html = '';
    $legacy_html = '';
    $startExpanded = false;

    if ($fullPath !== null) {
        ob_start();
        require $fullPath;
        $legacy_html = (string)ob_get_clean();
    }

    if ($card_min_html === '' && $card_max_html === '' && $legacy_html !== '') {
        $card_min_html = $legacy_html;
    }

    $cardClass = 'dash_card bg_bila card_blue zaobleni_12';
    if ($isNano) {
        $cardClass .= ' card_mode_nano';
    }

    ob_start();
    ?>
    <section class="<?= h($cardClass) ?>" data-cb-dash-card="1"<?= $gridStyle !== '' ? ' style="' . h($gridStyle) . '"' : '' ?>>
      <article class="card_shell<?= h($cardLineHeightClass) ?> odstup_vnitrni_0"
        data-card-id="<?= h((string)$cardId) ?>"
        data-card-mode="<?= h($cardMode) ?>"
        data-card-title="<?= h($title) ?>"
        data-card-col="<?= h((string)$renderCol) ?>"
        data-card-line="<?= h((string)$renderLine) ?>"
        data-card-pos-locked="<?= $isPosLocked ? '1' : '0' ?>"
        <?= $startExpanded ? ' data-card-start-expanded="1"' : '' ?>>
        <div class="card_top<?= h($cardTopRoleClass) ?> gap_10 odstup_vnitrni_10 displ_flex jc_mezi"<?= $cardTopStyle !== '' ? ' style="' . h($cardTopStyle) . '"' : '' ?>>
          <div class="card_head_left displ_flex">
            <div class="card_pref_wrap<?= $isPosLocked ? ' card_pref_wrap_pos_locked' : '' ?>" data-card-pref-wrap="1">
              <button type="button" class="card_pref_toggle cursor_ruka bg_bila" data-card-pref-toggle="1" aria-haspopup="true" aria-expanded="false" title="Nastavení karty">
                <?php if ($hasCardIcon): ?>
                  <span class="card_pref_icon"><img src="<?= h((string)$cardIconSrc) ?>" class="card_pref_icon_img" alt=""></span>
                <?php elseif ($isNano): ?>
                  <span class="card_pref_empty" aria-hidden="true"></span>
                <?php else: ?>
                  <span class="card_pref_dots txt_seda">&#8942;</span>
                <?php endif; ?>
                <span class="card_pref_kid">K<?= h((string)$cardId) ?>/<?= h((string)$cardPoradi) ?></span>
              </button>
              <div class="card_pref_menu is-hidden" data-card-pref-menu="1">
                <div class="card_pref_list" data-card-pref-list="1">
                  <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="color" data-card-pref-url="<?= h($cardColorUrl) ?>">Barva karty</button>
                  <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="ikon" data-card-pref-url="<?= h($cardIconUrl) ?>">Ikona karty</button>
                  <button type="button" class="card_pref_item cursor_ruka" data-card-pref-move="1">Přesunout na pozici</button>
                  <button type="button" class="card_pref_item cursor_ruka" data-card-pref-unlock-all="1">Odemkni vše</button>
                </div>
                <iframe class="card_pref_frame is-hidden" data-card-pref-frame="1" title="Nastavení karty"></iframe>
              </div>
            </div>
            <div class="card_head_text">
              <h3 class="card_title txt_seda text_15 odstup_vnejsi_0"><?= h($title) ?></h3>
              <?php if (!$isNano): ?>
                <p
                  class="card_subtitle text_12"
                  data-card-subtitle="1"
                  data-subtitle-min="<?= h($subtitleMin) ?>"
                  data-subtitle-max="<?= h($subtitleMax) ?>"
                ><?= h($subtitleMin) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <div class="card_tools gap_4 displ_flex flex_sloupec">
            <?php if ($isNano): ?>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-nano-target="mini" title="Prepnout na mini">&#8722;</button>
            <?php else: ?>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-toggle="1" aria-expanded="false" title="Prepnout na maxi/mini">&#10530;</button>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-to-nano="1" title="Prepnout na nano">&bull;</button>
            <?php endif; ?>
          </div>
        </div>

        <div class="card_body odstup_vnejsi_0">
          <div class="card_min card_compact odstup_vnitrni_10" data-card-compact>
            <?= $card_min_html ?>
          </div>
          <div class="card_max card_expanded odstup_vnitrni_10 is-hidden" data-card-expanded>
            <?= $card_max_html ?>
          </div>
        </div>
      </article>
    </section>
    <?php
    return (string)ob_get_clean();
};
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
          foreach ((array)($renderItem['karty'] ?? []) as $kartaNano) {
              echo $renderCard((array)$kartaNano, true, '');
          }
          echo '</div>';
          continue;
      }
      echo $renderCard((array)($renderItem['karta'] ?? []), (bool)($renderItem['is_nano'] ?? false), trim((string)($renderItem['grid_style'] ?? '')));
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
/* includes/dashboard.php * Verze: V9 * Aktualizace: 25.03.2026 */
// Konec souboru
