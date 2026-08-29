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
require_once __DIR__ . '/sm_lib/sm_pages.php';

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

?>
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/sm_includes/sm_menu.php'; ?>
<?php endif; ?>

<?php if ($smPage === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php else: ?>
<section class="pp smeny_content" data-module="smeny" data-page="<?= h($smPage) ?>">
    <header class="pp_header">
        <h1><?= h($smPageTitle) ?></h1>
    </header>
    <p class="smeny_content_text">Modul Směny je připravený pro další napojení obsahu.</p>
</section>
<?php endif; ?>
