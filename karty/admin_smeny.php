<?php
// karty/admin_smeny.php * Verze: V6 * Aktualizace: 16.3.2026
declare(strict_types=1);

$cbAktualniRok = (int)date('Y');
$cbRoky = range(2020, $cbAktualniRok);
rsort($cbRoky);

$cbMesice = [
    1 => 'Leden',
    2 => 'Únor',
    3 => 'Březen',
    4 => 'Duben',
    5 => 'Květen',
    6 => 'Červen',
    7 => 'Červenec',
    8 => 'Srpen',
    9 => 'Září',
    10 => 'Říjen',
    11 => 'Listopad',
    12 => 'Prosinec',
];

$cbPobocky = [
    'vsechny' => 'Všechny pobočky',
    'Chodov' => 'Chodov',
    'Malešice' => 'Malešice',
    'Zličín' => 'Zličín',
    'Libuš' => 'Libuš',
    'Prosek' => 'Prosek',
    'Plzeň - Bolevec' => 'Plzeň - Bolevec',
];

$cbVybranyRok = isset($_POST['cb_admin_smeny_rok']) ? (int)$_POST['cb_admin_smeny_rok'] : $cbAktualniRok;
$cbVybranyMesic = isset($_POST['cb_admin_smeny_mesic']) ? (int)$_POST['cb_admin_smeny_mesic'] : (int)date('n');
$cbVybranaPobocka = isset($_POST['cb_admin_smeny_pobocka']) ? trim((string)$_POST['cb_admin_smeny_pobocka']) : 'Plzeň - Bolevec';
$cbImportLog = [];
$cbImportReport = null;
$cbImportSpusten = false;

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

$cbExpandedClass = $cbImportSpusten ? '' : ' is-hidden';
?>

<article class="card_shell cb-admin-smeny">
  <div class="card_top">
    <div>
      <h3 class="card_title"><?= h((string)($cb_card_title ?? 'Historie směn')) ?></h3>
      <p class="card_subtitle">
        <span class="card_code"><?= h((string)($cb_card_code ?? '')) ?></span>
        Stažení historických dat ze Směn
      </p>
    </div>

    <div class="card_tools">
      <button
        type="button"
        class="card_tool_btn"
        data-card-toggle="1"
        aria-expanded="<?= $cbImportSpusten ? 'true' : 'false' ?>"
        title="Rozbalit / sbalit"
      >⤢</button>
    </div>
  </div>

  <div class="card_compact" data-card-compact>
    <p class="card_text">Zde se stahují historická data ze Směn</p>
  </div>

  <div class="card_expanded<?= $cbExpandedClass ?>" data-card-expanded>

    <div class="card_section">
      <h4 class="card_section_title">Výběr období</h4>

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
            <label class="ak_label">Měsíc</label>
            <select name="cb_admin_smeny_mesic" class="ak_select">
              <?php foreach ($cbMesice as $cislo => $nazev): ?>
                <option value="<?= h((string)$cislo) ?>"<?= $cbVybranyMesic === (int)$cislo ? ' selected' : '' ?>>
                  <?= h($nazev) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="ak_field">
            <label class="ak_label">Pobočka</label>
            <select name="cb_admin_smeny_pobocka" class="ak_select">
              <?php foreach ($cbPobocky as $hodnota => $nazev): ?>
                <option value="<?= h($hodnota) ?>"<?= $cbVybranaPobocka === $hodnota ? ' selected' : '' ?>>
                  <?= h($nazev) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <button type="submit" class="btn btn-primary">
              Spustit stažení
            </button>
          </div>

        </div>
      </form>

      <p class="card_text">
        Test nyní ukládá aktuální týden vybrané pobočky do tabulky smeny_akceptovane.
      </p>

      <?php if (!$cbImportSpusten): ?>

        <div class="ak_actions" style="display:flex; flex-direction:column; gap:8px; max-width:260px;">

          <form method="post" action="<?= h(cb_url('/?sekce=1')) ?>">
            <input type="hidden" name="cb_admin_smeny_akce" value="import_txt_db">
            <button type="submit" class="btn btn-primary">
              Import TXT → DB
            </button>
          </form>

          <form method="get" action="<?= h(cb_url('/admin_testy/plnime_smeny_akceptovane.php')) ?>">
            <button type="submit" class="btn btn-primary">
              Plnit smeny_akceptovane
            </button>
          </form>

        </div>

      <?php endif; ?>

    </div>

  </div>
</article>