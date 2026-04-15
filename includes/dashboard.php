<?php
// includes/dashboard.php * Verze: V15 * Aktualizace: 15.04.2026
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

// --- DASHBOARD PRIPRAVA DAT ---

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

// --- ROZDELENI KARET NA NANO/MINI A PRIPRAVA DAT ---
$kartyNanoPripravene = [];
$kartyMiniPripravene = [];

// Rozdělení do skupin a pořadí (nano, mini)
// NANO: vždy pouze podle user_nano, max 9 a bez duplicit
$kartyNanoPripravene = [];
$nanoAdded = [];
$countNano = 0;
foreach ($karty as $kartaRow) {
    $idK = (int)($kartaRow['id_karta'] ?? 0);
    if ($idK > 0 && isset($nanoCardIds[$idK]) && !isset($nanoAdded[$idK]) && $countNano < 9) {
        $kartyNanoPripravene[] = cb_priprav_kartu_nano($kartaRow, $userCardHeaderColorById, $userCardIconFileById, $userCardPosById, $dashGridCols);
        $nanoAdded[$idK] = true;
        $countNano++;
    }
}
// MINI: všechny ostatní
$kartyMiniPripravene = [];
foreach ($karty as $kartaRow) {
    $idK = (int)($kartaRow['id_karta'] ?? 0);
    if (!$idK || isset($nanoAdded[$idK])) continue;
    $kartyMiniPripravene[] = cb_priprav_kartu_mini($kartaRow, $userCardHeaderColorById, $userCardIconFileById, $userCardPosById, $dashGridCols);
}

$dashGridClass = $dashColsClass . ' dash_nano_kde_' . $nanoKde;
$cbLoginId = (int)($_SESSION['cb_id_login'] ?? 0);
$cbSingleCardId = (int)($GLOBALS['cb_render_single_card_id'] ?? 0);

// --- RENDER JEDNÉ KARTY PŘÍMO ---
if ($singleCardId > 0) {
    $singleCard = null;
    foreach ($kartyNanoPripravene as $nanoCard) {
        if ((int)($nanoCard['cardId'] ?? 0) === $singleCardId) {
            $singleCard = $nanoCard;
            break;
        }
    }
    if ($singleCard === null) {
        foreach ($kartyMiniPripravene as $miniCard) {
            if ((int)($miniCard['cardId'] ?? 0) === $singleCardId) {
                $singleCard = $miniCard;
                break;
            }
        }
    }
    if ($singleCard === null) {
        http_response_code(404);
        echo '<section class="card odstup_vnitrni_14"><p>Pozadovana karta neexistuje.</p></section>';
        return;
    }
    echo cb_zobraz_kartu($singleCard);
    return;
}
?>

<?php if ($cbSingleCardId > 0): ?>
  <?php
  $cbSingleHtml = '';
  foreach ($kartyNanoPripravene as $nanoCard) {
      if ((int)($nanoCard['cardId'] ?? 0) === $cbSingleCardId) {
          $cbSingleHtml = cb_zobraz_kartu($nanoCard);
          break;
      }
  }
  if ($cbSingleHtml === '') {
      foreach ($kartyMiniPripravene as $miniCard) {
          if ((int)($miniCard['cardId'] ?? 0) === $cbSingleCardId) {
              $cbSingleHtml = cb_zobraz_kartu($miniCard);
              break;
          }
      }
  }
  if ($cbSingleHtml === '') {
      http_response_code(404);
      echo '<section class="card odstup_vnitrni_14"><p>Karta nebyla nalezena.</p></section>';
  } else {
      echo $cbSingleHtml;
  }
  ?>
<?php else: ?>
<div class="dash_grid gap_2 <?= h($dashGridClass) ?> displ_grid sirka100" data-login-id="<?= h((string)$cbLoginId) ?>">
  <?php if (empty($karty)): ?>
    <section class="dash_card ram_normal bg_bila card_blue zaobleni_12" data-cb-dash-card="1">
      <div class="dash_card_body">
        <p class="small_note text_12"><?= h($emptyText) ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php
      // Všechny nano vždy pod sebou (pozice 1/1 atd.)
      $nanoIndex = 1;
      foreach ($kartyNanoPripravene as $nanoCardP) {
          // Dodáme hardcode col=1, line=n pro nano
          $nanoCardP['col'] = 1;
          $nanoCardP['line'] = $nanoIndex;
          echo cb_zobraz_kartu($nanoCardP);
          $nanoIndex++;
      }
      foreach ($kartyMiniPripravene as $miniCardP) {
          echo cb_zobraz_kartu($miniCardP);
      }
    ?>
  <?php endif; ?>
</div>
<?php endif; ?>

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
