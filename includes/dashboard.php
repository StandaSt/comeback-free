<?php
// includes/dashboard.php * Verze: V7 * Aktualizace: 08.03.2026
declare(strict_types=1);

/*
 * DASHBOARD
 *
 * V7:
 * - karty se nacitaji z DB tabulky `karty`
 * - jednoduchy rezim podle sekce: 3=home, 2=manager, 1=admin
 * - nacita jen karty dane sekce (min_role = sekce)
 * - razeni: poradi ASC, id_karta ASC
 *
 * Poznamka:
 * - kdyz tabulka `karty` nema zadne zaznamy, dashboard vypise informacni hlasku
 * - layout tridy jsou mapovane podle id_karta, jinak se pouzije default
 */

/**
 * Bezpecne prevede nazev souboru karty na absolutni cestu v projektu.
 * V DB je ulozen jen nazev bez "karty/" a bez ".php" (napr. "zadani_reportu").
 */
function cb_dashboard_resolve_file(string $soubor): ?string
{
    $raw = trim(str_replace('\\', '/', $soubor));
    if ($raw === '') {
        return null;
    }

    // Kompatibilita: kdyby v DB zustal starsi format cesty, prevede se na basename.
    $name = basename($raw);
    $name = preg_replace('~\.php$~i', '', $name) ?: '';
    if (!preg_match('~^[a-z0-9_]{2,80}$~', $name)) {
        return null;
    }

    $base = realpath(__DIR__ . '/..');
    if ($base === false) {
        return null;
    }

    $rel = 'karty/' . $name . '.php';
    $full = realpath($base . '/' . $rel);
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
    $idKarta = (int)($row['id_karta'] ?? 0);

    // Mapa podle ID karty (lze doplnovat podle potreby).
    $map = [
    ];

    return $map[$idKarta] ?? 'dash_col_12 dash_card card_blue';
}

/*
 * Nacitani karet pro dashboard:
 * - sekce je cislo 3/2/1 (home/manager/admin)
 * - nactou se jen karty se stejnou hodnotou min_role
 */
$sekce = (int)($cb_dashboard_sekce ?? 3);
if (!in_array($sekce, [1, 2, 3], true)) {
    $sekce = 3;
}

$karty = [];

$stmt = db()->prepare('
    SELECT id_karta, nazev, soubor, min_role, poradi
    FROM karty
    WHERE aktivni = 1
      AND min_role = ?
    ORDER BY poradi ASC, id_karta ASC
');

if ($stmt) {
    $stmt->bind_param('i', $sekce);
    $stmt->execute();
    $stmt->bind_result($idKarta, $nazev, $soubor, $minRole, $poradi);

    while ($stmt->fetch()) {
        $karty[] = [
            'id_karta' => (int)$idKarta,
            'nazev' => (string)$nazev,
            'soubor' => (string)$soubor,
            'min_role' => (int)$minRole,
            'poradi' => (int)$poradi,
        ];
    }

    $stmt->close();
}

// Pozadovany text od uzivatele pro prazdny stav v jednotlivych sekcich.
$emptyMap = [
    3 => 'Zadna karta k zobrazeni (home)',
    2 => 'Zadna karta k zobrazeni (manager)',
    1 => 'Zadna karta k zobrazeni (admin)',
];
$emptyText = (string)($emptyMap[$sekce] ?? $emptyMap[3]);
?>

<div class="dash_grid">
  <?php if (empty($karty)): ?>
    <section class="dash_col_12 dash_card card_blue" data-cb-dash-card="1">
      <div class="dash_card_body">
        <p class="small_note"><?= $emptyText ?></p>
      </div>
    </section>
  <?php else: ?>
    <?php foreach ($karty as $karta): ?>
      <?php
      $fullPath = cb_dashboard_resolve_file((string)($karta['soubor'] ?? ''));
      $classes = cb_dashboard_card_classes($karta);
      $cb_card_code = 'K' . (string)($karta['id_karta'] ?? 0);
      $cb_card_title = (string)($karta['nazev'] ?? '');
      ?>
      <section class="<?= h($classes) ?>" data-cb-dash-card="1">
        <?php if ($fullPath !== null): ?>
          <?php require $fullPath; ?>
        <?php else: ?>
          <div class="dash_card_body">
            <p class="small_note">
              Kartu <strong><?= h((string)($karta['nazev'] ?? ('ID ' . (string)($karta['id_karta'] ?? 0)))) ?></strong>
              nelze nacist. Soubor nebyl nalezen nebo neni povoleny.
            </p>
            <p class="small_note">Soubor: <code>karty/<?= h((string)($karta['soubor'] ?? '')) ?>.php</code></p>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
/* includes/dashboard.php * Verze: V7 * Aktualizace: 08.03.2026 */
// Konec souboru
