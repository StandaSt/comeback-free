<?php
// includes/dashboard.php * Verze: V8 * Aktualizace: 17.03.2026
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

$sekce = (int)($cb_dashboard_sekce ?? 3);
if (!in_array($sekce, [1, 2, 3], true)) {
    $sekce = 3;
}

$idUser = (int)(($_SESSION['cb_user']['id_user'] ?? 0));
$dashColsClass = 'dash_cols_3';

if ($idUser > 0) {
    $stmtCols = db()->prepare('SELECT pocet_sl FROM `user` WHERE id_user = ? LIMIT 1');
    if ($stmtCols) {
        $stmtCols->bind_param('i', $idUser);
        $stmtCols->execute();
        $stmtCols->bind_result($pocetSl);
        if ($stmtCols->fetch() && (int)$pocetSl === 4) {
            $dashColsClass = 'dash_cols_4';
        }
        $stmtCols->close();
    }
}

$karty = [];
$stmt = db()->prepare('
    SELECT id_karta, nazev, subtitle_min, subtitle_max, soubor, min_role, aktivni, poradi
    FROM karty
    WHERE aktivni = 1
      AND min_role = ?
    ORDER BY poradi ASC, id_karta ASC
');

if ($stmt) {
    $stmt->bind_param('i', $sekce);
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
    3 => 'Zadna karta k zobrazeni (home)',
    2 => 'Zadna karta k zobrazeni (manager)',
    1 => 'Zadna karta k zobrazeni (admin)',
];
$emptyText = (string)($emptyMap[$sekce] ?? $emptyMap[3]);
?>

<div class="dash_grid <?= h($dashColsClass) ?>">
  <?php if (empty($karty)): ?>
    <section class="dash_card card_blue" data-cb-dash-card="1">
      <div class="dash_card_body">
        <p class="small_note"><?= h($emptyText) ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($karty as $karta): ?>
      <?php
      $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
      $title = (string)($karta['nazev'] ?? '');
      $subtitleMin = (string)($karta['subtitle_min'] ?? '');
      $subtitleMax = (string)($karta['subtitle_max'] ?? '');
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
      ?>
      <section class="dash_card card_blue" data-cb-dash-card="1">
        <article class="card_shell"<?= $startExpanded ? ' data-card-start-expanded="1"' : '' ?>>
          <div class="card_top">
            <div>
              <h3 class="card_title"><?= h($title) ?></h3>
              <p
                class="card_subtitle"
                data-card-subtitle="1"
                data-subtitle-min="<?= h($subtitleMin) ?>"
                data-subtitle-max="<?= h($subtitleMax) ?>"
              ><?= h($subtitleMin) ?></p>
            </div>
            <div class="card_tools">
              <button
                type="button"
                class="card_tool_btn"
                data-card-toggle="1"
                aria-expanded="false"
                title="Rozbalit/sbalit"
              >⤢</button>
            </div>
          </div>

          <div class="card_body">
            <div class="card_min card_compact" data-card-compact>
              <?= $card_min_html ?>
            </div>
            <div class="card_max card_expanded is-hidden" data-card-expanded>
              <?= $card_max_html ?>
            </div>
          </div>
        </article>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
/* includes/dashboard.php * Verze: V8 * Aktualizace: 17.03.2026 */
// Konec souboru
