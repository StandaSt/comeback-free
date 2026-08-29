<?php
declare(strict_types=1);

/*
 * Modulový vstup Úkoly.
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
require_once __DIR__ . '/uk_lib/uk_pages.php';

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

$ukMenuItems = cb_ukoly_pages();
$ukCurrentPage = cb_ukoly_current_page($ukMenuItems);
$ukPage = $ukCurrentPage['key'];
$ukPageTitle = $ukCurrentPage['title'];

?>
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/uk_includes/uk_menu.php'; ?>
<?php endif; ?>

<?php if ($ukPage === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php else: ?>
<section class="pp ukoly_content" data-module="ukoly" data-page="<?= h($ukPage) ?>">
    <header class="pp_header">
        <h1><?= h($ukPageTitle) ?></h1>
    </header>
    <div class="ukoly_placeholder">
        <p class="ukoly_placeholder_text">Modul je připravený pro další doplnění obsahu.</p>
    </div>
</section>
<?php endif; ?>
