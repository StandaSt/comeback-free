<?php
declare(strict_types=1);

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';

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

$smMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'pozadavky', 'label' => 'Požadavky'],
    ['page' => 'hodnoceni', 'label' => 'Hodnocení'],
    ['page' => 'me_smeny', 'label' => 'Mé směny', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
    ['page' => 'planovani_smen', 'label' => 'Plánování směn', 'items' => ['Aktuální týden', 'Týden + 1']],
    ['page' => 'sablony', 'label' => 'Šablony'],
    ['page' => 'naplanovane_smeny', 'label' => 'Naplánované směny', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
    ['page' => 'zadane_pozadavky', 'label' => 'Zadané požadavky', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2', 'Historie']],
    ['page' => 'administrace', 'label' => 'Administrace'],
];
$smPage = strtolower(trim((string)($_GET['page'] ?? 'prehled')));
$smPageTitle = 'Přehled';
foreach ($smMenuItems as $smItem) {
    if ((string)$smItem['page'] === $smPage) {
        $smPageTitle = (string)$smItem['label'];
        break;
    }
}
if ($smPageTitle === 'Přehled') {
    $smPage = 'prehled';
}

?>
<?php require __DIR__ . '/sm_includes/sm_menu.php'; ?>

<section class="pp smeny_content" data-module="smeny" data-page="<?= h($smPage) ?>">
    <header class="pp_header">
        <h1><?= h($smPageTitle) ?></h1>
    </header>
    <p class="smeny_content_text">Modul Směny je připravený pro další napojení obsahu.</p>
</section>
