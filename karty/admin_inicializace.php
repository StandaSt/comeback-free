<?php
// karty/admin_inicializace.php * Verze: V8 * Aktualizace: 18.03.2026
declare(strict_types=1);

$cbAktualniRok = (int)date('Y');
$cbRoky = range(2020, $cbAktualniRok);
rsort($cbRoky);

$cbMesice = [
    1 => 'Leden',
    2 => 'Unor',
    3 => 'Brezen',
    4 => 'Duben',
    5 => 'Kveten',
    6 => 'Cerven',
    7 => 'Cervenec',
    8 => 'Srpen',
    9 => 'Zari',
    10 => 'Rijen',
    11 => 'Listopad',
    12 => 'Prosinec',
];

$cbPobocky = [
    'vsechny' => 'Vsechny pobocky',
    'Chodov' => 'Chodov',
    'Malesice' => 'Malesice',
    'Zlicin' => 'Zlicin',
    'Libus' => 'Libus',
    'Prosek' => 'Prosek',
    'Plzen - Bolevec' => 'Plzen - Bolevec',
];

$cbVybranyRok = isset($_POST['cb_admin_smeny_rok']) ? (int)$_POST['cb_admin_smeny_rok'] : $cbAktualniRok;
$cbVybranyMesic = isset($_POST['cb_admin_smeny_mesic']) ? (int)$_POST['cb_admin_smeny_mesic'] : (int)date('n');
$cbVybranaPobocka = isset($_POST['cb_admin_smeny_pobocka']) ? trim((string)$_POST['cb_admin_smeny_pobocka']) : 'Plzen - Bolevec';
$cbImportLog = [];
$cbImportReport = null;
$cbImportSpusten = false;

$cbRestiaCount = 0;
$cbSmenyCount = 0;
$cbRestiaDate = 'Ne';
$cbSmenyDate = 'Ne';

$qRestia = db()->query('SELECT COALESCE(MAX(id_obj), 0) AS cnt, MAX(`import`) AS dt FROM objednavka');
if ($qRestia instanceof mysqli_result) {
    $r = $qRestia->fetch_assoc();
    $cbRestiaCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbRestiaDate = ($dt !== '') ? $dt : 'Ne';
    $qRestia->free();
}

$qSmeny = db()->query('SELECT COALESCE(MAX(id), 0) AS cnt, MAX(created_at) AS dt FROM smeny_akceptovane');
if ($qSmeny instanceof mysqli_result) {
    $r = $qSmeny->fetch_assoc();
    $cbSmenyCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbSmenyDate = ($dt !== '') ? $dt : 'Ne';
    $qSmeny->free();
}

if (isset($_POST['cb_admin_smeny_akce']) && $_POST['cb_admin_smeny_akce'] === 'import_tyden') {
    $cbImportSpusten = true;

    require_once __DIR__ . '/../admin_testy/st.php';

    $cbImportReport = cb_st_import_week(
        ['kod' => $cbVybranaPobocka],
        static function (string $line) use (&$cbImportLog): void {
            $cbImportLog[] = $line;
        }
    );
}

if (isset($_POST['cb_admin_smeny_akce']) && $_POST['cb_admin_smeny_akce'] === 'import_txt_db') {
    $cbImportSpusten = true;

    require_once __DIR__ . '/../admin_testy/txt_db_import.php';

    $cbImportReport = cb_txt_db_import(
        static function (string $line) use (&$cbImportLog): void {
            $cbImportLog[] = $line;
        }
    );
}

$card_min_html = ''
    . '<p class="card_text"><strong>Nalezené záznamy v DB</strong></p>'
    . '<div class="table-wrap">'
    . '<table class="table">'
    . '<thead><tr><th>Zdroj</th><th style="text-align:right;">záznamů</th><th style="text-align:right;">aktualizace</th></tr></thead>'
    . '<tbody>'
    . '<tr><td>Restia</td><td style="text-align:right;"><strong>' . h((string)$cbRestiaCount) . '</strong></td><td style="text-align:right;">' . h($cbRestiaDate) . '</td></tr>'
    . '<tr><td>Směny</td><td style="text-align:right;"><strong>' . h((string)$cbSmenyCount) . '</strong></td><td style="text-align:right;">' . h($cbSmenyDate) . '</td></tr>'
    . '<tr><td>Reporty</td><td style="text-align:right;"><strong>0</strong></td><td style="text-align:right;">Ne</td></tr>'
    . '</tbody>'
    . '</table>'
    . '</div>';

ob_start();
?>
<div class="card_section">
  <h4 class="card_section_title">Vyber obdobi</h4>

  <form method="post" action="<?= h(cb_url('/?sekce=1')) ?>">
    <input type="hidden" name="cb_admin_smeny_akce" value="import_tyden">

    <div style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
      <div class="ak_field">
        <label class="ak_label">Rok</label>
        <select name="cb_admin_smeny_rok" class="ak_select">
          <?php foreach ($cbRoky as $rok): ?>
            <option value="<?= h((string)$rok) ?>"<?= $cbVybranyRok === (int)$rok ? ' selected' : '' ?>>
              <?= h((string)$rok) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="ak_field">
        <label class="ak_label">Mesic</label>
        <select name="cb_admin_smeny_mesic" class="ak_select">
          <?php foreach ($cbMesice as $cislo => $nazev): ?>
            <option value="<?= h((string)$cislo) ?>"<?= $cbVybranyMesic === (int)$cislo ? ' selected' : '' ?>>
              <?= h($nazev) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="ak_field">
        <label class="ak_label">Pobocka</label>
        <select name="cb_admin_smeny_pobocka" class="ak_select">
          <?php foreach ($cbPobocky as $hodnota => $nazev): ?>
            <option value="<?= h($hodnota) ?>"<?= $cbVybranaPobocka === $hodnota ? ' selected' : '' ?>>
              <?= h($nazev) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <button type="submit" class="btn btn-primary">Spustit stazeni</button>
      </div>
    </div>
  </form>

  <p class="card_text">
    Test nyni uklada aktualni tyden vybrane pobocky do tabulky smeny_akceptovane.
  </p>

  <?php if (!$cbImportSpusten): ?>
    <div class="ak_actions" style="display:flex; flex-direction:column; gap:8px; max-width:260px;">
      <form method="post" action="<?= h(cb_url('/?sekce=1')) ?>">
        <input type="hidden" name="cb_admin_smeny_akce" value="import_txt_db">
        <button type="submit" class="btn btn-primary">Import TXT -> DB</button>
      </form>

      <form method="get" action="<?= h(cb_url('/admin_testy/plnime_smeny_akceptovane.php')) ?>">
        <button type="submit" class="btn btn-primary">Plnit smeny_akceptovane</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_inicializace.php * Verze: V8 * Aktualizace: 18.03.2026 */
?>
