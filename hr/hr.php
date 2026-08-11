<?php
declare(strict_types=1);

/*
 * Modulový vstup HR.
 * Sem nepatří SQL dotazy, HTML bloky, AJAX handlery ani pomocné funkce.
 * Soubor má pouze připravit modul, předat akce dispatcheru, vybrat stránku/pohled a načíst modulový layout.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';
require_once __DIR__ . '/hr_includes/hr_data.php';
require_once __DIR__ . '/hr_lib/hr_pages.php';
require_once __DIR__ . '/hr_lib/hr_request_dispatch.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'hr';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul HR lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

$cbUser = $_SESSION['cb_user'] ?? [];
$roleId = is_array($cbUser) ? (int)($cbUser['id_role'] ?? 0) : 0;
$userId = is_array($cbUser) ? (int)($cbUser['id_user'] ?? 0) : 0;

if (!in_array($roleId, [1, 3, 5], true) && $userId !== 57) {
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}

cb_pobocky_bootstrap_session();

$currentPage = cb_hr_current_page();
$page = $currentPage['key'];
$pageTitle = $currentPage['title'];

$cbProfile = $_SESSION['cb_user_profile'] ?? [];
$userName = '';
$userRole = '';

if (is_array($cbUser)) {
    $userName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    if ($userName === '') {
        $userName = trim((string)($cbUser['email'] ?? ''));
    }
    if ($userName === '' && (int)($cbUser['id_user'] ?? 0) > 0) {
        $userName = 'Uživatel #' . (string)(int)$cbUser['id_user'];
    }

    $userRole = trim((string)($cbUser['role'] ?? ''));
}

if ($userRole === '' && is_array($cbProfile)) {
    $roles = $cbProfile['roles'] ?? [];
    if (is_array($roles) && isset($roles[0]) && is_array($roles[0])) {
        $userRole = trim((string)($roles[0]['name'] ?? ''));
    }
}

if ($userName === '') {
    $userName = 'Uživatel';
}
if ($userRole === '') {
    $userRole = 'Uživatel';
}
$db = db();
cb_hr_request_dispatch($db, $page, $cbUser, $roleId);

$flash = $_SESSION['hr_flash'] ?? null;
unset($_SESSION['hr_flash']);

?>
<?php require __DIR__ . '/hr_includes/hr_menu.php'; ?>

<?php if ($page === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php else: ?>
<section class="pp hr_pp" data-module="hr" data-page="<?= h($page) ?>">
    <header class="pp_header">
        <h1><?= h($pageTitle) ?></h1>
        <?php require __DIR__ . '/hr_includes/topbar.php'; ?>
    </header>
    <main class="hr_content">
        <?php if (is_array($flash) && isset($flash['text'])): ?>
            <div class="hr_notice <?= h((string)($flash['type'] ?? 'hr_info')) ?>"><?= h((string)$flash['text']) ?></div>
        <?php endif; ?>
        <?php require $currentPage['file']; ?>
    </main>
</section>
<?php endif; ?>
