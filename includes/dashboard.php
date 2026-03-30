<?php
// includes/dashboard.php * Verze: V9 * Aktualizace: 25.03.2026
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
$nanoKde = 0;
$nanoCardIds = [];

if ($idUser > 0) {
    $stmtCols = db()->prepare('SELECT pocet_sl, nano_kde FROM `user_set` WHERE id_user = ? LIMIT 1');
    if ($stmtCols) {
        $stmtCols->bind_param('i', $idUser);
        $stmtCols->execute();
        $stmtCols->bind_result($pocetSl, $nanoKdeDb);
        if ($stmtCols->fetch() && (int)$pocetSl === 4) {
            $dashColsClass = 'dash_cols_4';
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
$dashGridClass = $dashColsClass . ' dash_nano_kde_' . $nanoKde;

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

$renderCard = static function (array $karta, bool $isNano, string $gridStyle = ''): string {
    $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
    $title = (string)($karta['nazev'] ?? '');
    $subtitleMin = (string)($karta['subtitle_min'] ?? '');
    $subtitleMax = (string)($karta['subtitle_max'] ?? '');
    $cardId = (int)($karta['id_karta'] ?? 0);
    $cardMode = $isNano ? 'nano' : 'mini';
    $cardLineHeightClass = $isNano ? ' radek_normalni' : ' radek_volnejsi';

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
      <article class="card_shell<?= h($cardLineHeightClass) ?> odstup_vnitrni_0" data-card-id="<?= h((string)$cardId) ?>" data-card-mode="<?= h($cardMode) ?>"<?= $startExpanded ? ' data-card-start-expanded="1"' : '' ?>>
        <div class="card_top<?= h($cardTopRoleClass) ?> mezera_mezi_10 odstup_vnitrni_10 displ_flex jc_mezi">
          <div>
            <h3 class="card_title txt_seda text_titulek_15 odstup_vnejsi_0"><?= h($title) ?></h3>
            <?php if (!$isNano): ?>
              <p
                class="card_subtitle text_normalni_12"
                data-card-subtitle="1"
                data-subtitle-min="<?= h($subtitleMin) ?>"
                data-subtitle-max="<?= h($subtitleMax) ?>"
              ><?= h($subtitleMin) ?></p>
            <?php endif; ?>
          </div>
          <div class="card_tools mezera_mezi_4 displ_flex flex_sloupec">
            <?php if ($isNano): ?>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_velky_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-nano-target="mini" title="Prepnout na mini">&#8722;</button>
            <?php else: ?>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_velky_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-toggle="1" aria-expanded="false" title="Prepnout na maxi/mini">&#10530;</button>
              <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_velky_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-to-nano="1" title="Prepnout na nano">&bull;</button>
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

<div class="dash_grid mezera_mezi_2 <?= h($dashGridClass) ?> displ_grid sirka100">
  <?php if (empty($karty)): ?>
    <section class="dash_card ram_normal bg_bila card_blue zaobleni_12" data-cb-dash-card="1">
      <div class="dash_card_body">
        <p class="small_note text_normalni_12"><?= h($emptyText) ?></p>
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
          echo '<div class="dash_nano_group mezera_mezi_2 displ_flex flex_sloupec">';
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

<?php
/* includes/dashboard.php * Verze: V9 * Aktualizace: 25.03.2026 */
// Konec souboru
