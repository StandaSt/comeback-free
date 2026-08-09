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

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'ukoly';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Úkoly lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

cb_pobocky_bootstrap_session();

$ukMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'nove_zadani', 'label' => 'Nové zadání'],
    ['page' => 'prehled_ukolu', 'label' => 'Přehled úkolů'],
    ['page' => 'ukoly_pro_me', 'label' => 'Úkoly pro mě'],
    ['page' => 'me_pozadavky', 'label' => 'Mé požadavky'],
];
$ukPage = strtolower(trim((string)($_GET['page'] ?? 'prehled')));
$ukPageTitle = 'Přehled';
foreach ($ukMenuItems as $ukItem) {
    if ((string)$ukItem['page'] === $ukPage) {
        $ukPageTitle = (string)$ukItem['label'];
        break;
    }
}
if ($ukPageTitle === 'Přehled') {
    $ukPage = 'prehled';
}

?>
<?php require __DIR__ . '/uk_includes/uk_menu.php'; ?>

<section class="blok_pp ukoly_content">
    <header class="blok_pp_header">
        <h1 class="blok_pp_title"><?= h($ukPageTitle) ?></h1>
    </header>
    <div class="ukoly_placeholder">
        <p class="ukoly_placeholder_text">Modul je připravený pro další doplnění obsahu.</p>
    </div>
</section>
