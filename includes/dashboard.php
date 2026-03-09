<?php
// includes/dashboard.php * Verze: V7 * Aktualizace: 08.03.2026
declare(strict_types=1);

/*
 * DASHBOARD
 *
 * V7:
 * - karty se nacitaji z DB tabulky `karty`
 * - jednoduchy rezim podle scope menu: home / manager / admin
 * - home: min_role >= 3
 * - manager: min_role >= 2
 * - admin: min_role >= 1
 * - razeni: poradi ASC, id_karta ASC
 *
 * Poznamka:
 * - kdyz tabulka `karty` nema zadne zaznamy, dashboard vypise informacni hlasku
 * - layout tridy jsou mapovane pro zname kody/soubory, jinak se pouzije default
 */

/**
 * Bezpecne prevede relativni cestu souboru karty na absolutni cestu v projektu.
 */
function cb_dashboard_resolve_file(string $soubor): ?string
{
    $rel = trim(str_replace('\\', '/', $soubor));
    if ($rel === '') {
        return null;
    }

    // Zakaz traversal mimo projekt.
    if (strpos($rel, '..') !== false) {
        return null;
    }

    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        return null;
    }

    $full = realpath($base . '/' . ltrim($rel, '/'));
    if ($full === false || !is_file($full)) {
        return null;
    }

    // Soubor musi zustat uvnitr projektu.
    if (strpos($full, $base) !== 0) {
        return null;
    }

    return $full;
}

/**
 * Vrati CSS tridy pro kartu (sirka + barevny styl).
 */
function cb_dashboard_card_classes(array $row): string
{
    $kod = (string)($row['kod'] ?? '');
    $soubor = (string)($row['soubor'] ?? '');

    // Mapa zachova puvodni vzhled znamych dashboard karet.
    $map = [
        'blok_02_trzba' => 'dash_col_4 dash_card card_blue',
        'blok_04_zisk' => 'dash_col_4 dash_card card_green',
        'blok_05_stav_systemu' => 'dash_col_4 dash_card card_cyan',
        'blok_06_report' => 'dash_col_8 dash_card card_orange',
        'blok_07_trend' => 'dash_col_4 dash_card card_purple',
        'blok_12_top_polozky' => 'dash_col_12 dash_card card_blue',
        'blok_16_kategorie' => 'dash_col_4 dash_card card_orange',
        'blok_23_tabulka_check' => 'dash_col_8 dash_card card_green',
    ];

    $key = $kod;
    if ($key === '') {
        $base = basename(str_replace('\\', '/', $soubor));
        $key = preg_replace('~\.php$~i', '', $base) ?: '';
    }

    return $map[$key] ?? 'dash_col_12 dash_card card_blue';
}

/*
 * Nacitani karet pro dashboard (jednoduchy rezim bez vyjimek):
 *
 * 1) Zjisti aktivni sekci menu ze scope (`home`, `manager`, `admin`).
 *    Pokud prijde neplatna hodnota, pouzije se bezpecny default `home`.
 *
 * 2) Podle sekce se nastavi jedna hranice `minRoleScope`:
 *    - home    -> 3
 *    - manager -> 2
 *    - admin   -> 1
 *
 * 3) Spusti se jeden SQL dotaz do tabulky `karty`:
 *    - bere jen aktivni karty (`aktivni = 1`)
 *    - bere jen karty splnujici hranici (`min_role >= ?`)
 *    - razeni je stabilni: `poradi ASC`, potom `id_karta ASC`
 *
 * 4) Vysledek se po radcich plni do pole `$karty`.
 *    Tohle pole je finalni seznam, ktery se pak vykresli v `dash_grid`.
 */
$scope = (string)($cb_dashboard_scope ?? 'home');
if (!in_array($scope, ['home', 'manager', 'admin'], true)) {
    $scope = 'home';
}

$karty = [];
if ($scope === 'home') {
    $minRoleScope = 3;
} elseif ($scope === 'manager') {
    $minRoleScope = 2;
} else {
    $minRoleScope = 1;
}

$stmt = db()->prepare('
    SELECT id_karta, kod, nazev, soubor, min_role, poradi
    FROM karty
    WHERE aktivni = 1
      AND min_role >= ?
    ORDER BY poradi ASC, id_karta ASC
');

if ($stmt) {
    $stmt->bind_param('i', $minRoleScope);
    $stmt->execute();
    $stmt->bind_result($idKarta, $kod, $nazev, $soubor, $minRole, $poradi);

    while ($stmt->fetch()) {
        $karty[] = [
            'id_karta' => (int)$idKarta,
            'kod' => (string)$kod,
            'nazev' => (string)$nazev,
            'soubor' => (string)$soubor,
            'min_role' => (int)$minRole,
            'poradi' => (int)$poradi,
        ];
    }

    $stmt->close();
}

// Požadovaný text od uživatele pro prázdný stav v jednotlivých sekcích.
$emptyMap = [
    'home' => 'Žádná karta k zobrazení (home)',
    'manager' => 'Žádná karta k zobrazení (manager)',
    'admin' => 'Žádná karta k zobrazení (admin)',
];
$emptyText = (string)($emptyMap[$scope] ?? $emptyMap['home']);
?>

<div class="dash_grid">
  <?php if (empty($karty)): ?>
    <section class="dash_col_12 dash_card card_blue">
      <div class="dash_card_body">
        <p class="small_note"><?= $emptyText ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($karty as $karta): ?>
      <?php
      $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
      $classes = cb_dashboard_card_classes($karta);
      ?>
      <section class="<?= h($classes) ?>">
        <?php if ($fullPath !== null): ?>
          <?php require $fullPath; ?>
        <?php else: ?>
          <div class="dash_card_body">
            <p class="small_note">
              Kartu <strong><?= h((string)($karta['nazev'] ?? $karta['kod'] ?? 'neznamy kod')) ?></strong>
              nelze nacist. Soubor nebyl nalezen nebo neni povoleny.
            </p>
            <p class="small_note">Cesta: <code><?= h((string)($karta['soubor'] ?? '')) ?></code></p>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
/* includes/dashboard.php * Verze: V7 * Aktualizace: 08.03.2026 */
// Konec souboru
