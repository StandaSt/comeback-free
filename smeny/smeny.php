<?php
declare(strict_types=1);

/*
 * Modulový vstup Směny.
 * Sem nepatří SQL dotazy, HTML bloky, AJAX handlery ani pomocné funkce.
 * Soubor má pouze připravit modul, předat akce dispatcheru, vybrat stránku/pohled a načíst modulový layout.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_pravo.php';
require_once __DIR__ . '/sm_lib/sm_sablony_pravo.php';
require_once __DIR__ . '/sm_lib/sm_planovani_pravo.php';
require_once __DIR__ . '/sm_lib/sm_pages.php';
require_once __DIR__ . '/sm_lib/sm_nastaveni_pravo.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_osoba.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_tydny.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_nacteni.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_kopie.php';
require_once __DIR__ . '/sm_lib/sm_audit_zapis.php';
require_once __DIR__ . '/sm_lib/sm_pozadavky_ulozeni.php';
require_once __DIR__ . '/sm_lib/sm_sablony_flash.php';
require_once __DIR__ . '/sm_lib/sm_sablony_kontext.php';
require_once __DIR__ . '/sm_lib/sm_sablony_nacteni.php';
require_once __DIR__ . '/sm_lib/sm_sablona_ulozeni.php';
require_once __DIR__ . '/sm_lib/sm_sablona_tyden_ulozeni.php';
require_once __DIR__ . '/sm_lib/sm_sablony_akce.php';
require_once __DIR__ . '/sm_lib/sm_planovani_data.php';
require_once __DIR__ . '/sm_lib/sm_planovani_zamek.php';
require_once __DIR__ . '/sm_lib/sm_planovani_obsazeni.php';
require_once __DIR__ . '/sm_lib/sm_planovani_akce.php';
require_once __DIR__ . '/sm_lib/sm_publikace.php';
require_once __DIR__ . '/sm_lib/sm_verejne.php';
require_once __DIR__ . '/sm_lib/sm_verejne_filtry.php';
require_once __DIR__ . '/sm_lib/sm_historie.php';
require_once __DIR__ . '/sm_lib/sm_oznameni_detail.php';
require_once __DIR__ . '/sm_lib/sm_oznameni_potvrzeni.php';
require_once __DIR__ . '/sm_lib/sm_prehled_tyden.php';
require_once __DIR__ . '/sm_lib/sm_zadane_pozadavky.php';
require_once __DIR__ . '/sm_lib/sm_prehled_data.php';
require_once __DIR__ . '/sm_lib/sm_zruseni_nacteni.php';
require_once __DIR__ . '/sm_lib/sm_zruseni_zadost.php';
require_once __DIR__ . '/sm_lib/sm_zruseni_vyrizeni.php';
require_once __DIR__ . '/sm_lib/sm_zruseni_publikace.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'smeny';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Směny lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

cb_pobocky_bootstrap_session();

$smMenuItems = cb_smeny_pages();
$smCurrentPage = cb_smeny_current_page($smMenuItems);
$smPage = $smCurrentPage['key'];
$smPageTitle = $smCurrentPage['title'];

if ($smPage === 'bez_prava') {
    http_response_code(403);
}

// Čtecí přehledy smějí do historie, ale zápisové týdny plánovače se tím nemění.
if (in_array($smPage, ['prehled','zadane_pozadavky'], true)) {
    $smDb = db();
    $smReadWeeks = cb_smeny_planovani_tydny();
    $smReadWeek = cb_smeny_prehled_tyden($smReadWeeks, $_GET);
    $smReadBranches = cb_smeny_planovani_vidi() ? cb_smeny_sablony_pobocky($smDb) : [];
}

if ($smPage === 'pozadavky') {
    $smDb = db();
    $smPerson = cb_smeny_pozadavky_osoba($smDb);
    $smWeeks = cb_smeny_pozadavky_tydny();
    $smWeekIndex = cb_smeny_pozadavky_index($smWeeks);
    $smWeek = $smWeeks[$smWeekIndex];
    try {
        cb_smeny_pozadavky_ulozit($smDb, $smPerson, $smWeeks);
    } catch (Throwable $e) {
        cb_smeny_pozadavky_flash('error', cb_chyba_uzivatel($e, [
            'module' => 'smeny',
            'action' => 'Uložení požadavků na směny',
            'table' => 'smeny_pozadavek',
        ]));
        $postedWeek = filter_var($_POST['week'] ?? $smWeekIndex, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]]);
        $postedWeek = $postedWeek === false ? $smWeekIndex : $postedWeek;
        header('Location: ' . cb_root_url('index.php?m=smeny&page=pozadavky&week=' . $postedWeek));
        exit;
    }
}

if ($smPage === 'sablony') {
    $smDb = db();
    $smTemplateBranches = cb_smeny_sablony_pobocky($smDb);
    cb_smeny_sablony_akce($smDb, $smTemplateBranches);
}

if ($smPage === 'planovani_smen') {
    $smDb = db();
    $smPlanBranches = cb_smeny_sablony_pobocky($smDb);
    $smPlanWeeks = cb_smeny_planovani_tydny();
    cb_smeny_planovani_akce($smDb, $smPlanBranches, $smPlanWeeks);
}

if (in_array($smPage, ['me_smeny','naplanovane_smeny'], true)) {
    $smDb = db();
    $smPublicPerson = cb_smeny_sablony_id_person($smDb);
    $smPublicWeeks = cb_smeny_planovani_tydny();
    $smPublicBranches = $smPage === 'naplanovane_smeny' && cb_smeny_planovani_vidi() ? cb_smeny_sablony_pobocky($smDb) : [];
    if ($smPage === 'me_smeny') {
        cb_smeny_verejne_potvrdit($smDb, $smPublicPerson);
        cb_smeny_zruseni_zadost($smDb, $smPublicPerson);
    }
}

// Historie má vlastní jednoduchý výběr pobočky a záměrně nereaguje na globální výběr v hlavičce.
if ($smPage === 'historie_smen') {
    $smDb = db();
    $smHistoryWeeks = cb_smeny_historie_tydny();
    $smHistoryWeek = cb_smeny_historie_tyden($smHistoryWeeks, $_GET);
    $smHistoryContext = cb_smeny_historie_kontext($smDb, $smHistoryWeek['start_day'], $_GET);
}

?>
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/sm_includes/sm_menu.php'; ?>
<?php endif; ?>

<?php if ($smPage === 'prehled'): ?>
    <?php require __DIR__ . '/sm_pages/prehled.php'; ?>
<?php elseif ($smPage === 'zadane_pozadavky'): ?>
    <?php require __DIR__ . '/sm_pages/zadane_pozadavky.php'; ?>
<?php elseif ($smPage === 'historie_smen'): ?>
    <?php require __DIR__ . '/sm_pages/historie_smen.php'; ?>
<?php elseif ($smPage === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php elseif ($smPage === 'bez_prava'): ?>
    <?php require __DIR__ . '/sm_pages/bez_prava.php'; ?>
<?php elseif ($smPage === 'pozadavky'): ?>
    <?php require __DIR__ . '/sm_pages/pozadavky.php'; ?>
<?php elseif ($smPage === 'sablony'): ?>
    <?php require __DIR__ . '/sm_pages/sablony.php'; ?>
<?php elseif ($smPage === 'planovani_smen'): ?>
    <?php require __DIR__ . '/sm_pages/planovani_smen.php'; ?>
<?php elseif ($smPage === 'nastaveni'): ?>
    <?php require __DIR__ . '/sm_pages/nastaveni.php'; ?>
<?php elseif (in_array($smPage, ['me_smeny','naplanovane_smeny'], true)): ?>
    <?php require __DIR__ . '/sm_pages/verejne_smeny.php'; ?>
<?php else: ?>
<section class="pp smeny_content" data-module="smeny" data-page="<?= h($smPage) ?>">
    <header class="pp_header">
        <h1><?= h($smPageTitle) ?></h1>
    </header>
    <p class="smeny_content_text">Modul Směny je připravený pro další napojení obsahu.</p>
</section>
<?php endif; ?>
