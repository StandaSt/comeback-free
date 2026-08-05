<?php
declare(strict_types=1);

/**
 * Vstupni bod HR modulu: overi pristup, vybere stranku a nacte layout.
 */
require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';
require_once __DIR__ . '/hr_includes/hr_data.php';

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

$pages = [
    'dashboard' => [
        'file' => __DIR__ . '/hr_pages/dashboard.php',
        'title' => 'Přehled',
    ],
    'nabor' => [
        'file' => __DIR__ . '/hr_pages/nabor.php',
        'title' => 'Nábor',
    ],
    'zamestnanci' => [
        'file' => __DIR__ . '/hr_pages/zamestnanci.php',
        'title' => 'Zaměstnanci',
    ],
    'zamestnanec' => [
        'file' => __DIR__ . '/hr_pages/zamestnanec.php',
        'title' => 'Karta zaměstnance',
    ],
    'novy_zamestnanec' => [
        'file' => __DIR__ . '/hr_pages/novy_zamestnanec.php',
        'title' => 'Nový zaměstnanec',
    ],
    'pozadavky' => [
        'file' => __DIR__ . '/hr_pages/pozadavky.php',
        'title' => 'Požadavky',
    ],
    'pracovni_pomery' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Pracovní poměry',
    ],
    'dokumenty' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Dokumenty',
    ],
    'skoleni' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Školení',
    ],
    'prohlidky' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Lékařské prohlídky',
    ],
    'dovolene' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Dovolené',
    ],
    'reporty' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Reporty',
    ],
    'nastaveni' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
        'title' => 'Nastavení',
    ],
];

$page = strtolower(trim((string)($_GET['page'] ?? 'dashboard')));
if (!isset($pages[$page])) {
    $page = 'dashboard';
}

$currentPage = $pages[$page];
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
$hrIsShellRequest = isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE']);
$hrIsFormPost = ($_SERVER['REQUEST_METHOD'] === 'POST') && !$hrIsShellRequest;

if ($page === 'nabor' && $hrIsFormPost) {
    hr_post_nabor($db);
}

if ($page === 'pozadavky' && $hrIsFormPost && in_array($roleId, [1, 5], true)) {
    hr_post_pozadavky($db, $cbUser, $roleId);
}

if ($page === 'novy_zamestnanec' && $hrIsFormPost) {
    hr_post_zamestnanec($db, $roleId);
}

$flash = $_SESSION['hr_flash'] ?? null;
unset($_SESSION['hr_flash']);

?>
<section class="module_shell hr_module_page">
    <?php require __DIR__ . '/hr_includes/hr_menu.php'; ?>

    <section class="module_content hr_module_content">
        <?php require __DIR__ . '/hr_includes/topbar.php'; ?>

        <main class="content">
            <?php if (is_array($flash) && isset($flash['text'])): ?>
                <div class="notice <?= h((string)($flash['type'] ?? 'info')) ?>"><?= h((string)$flash['text']) ?></div>
            <?php endif; ?>
            <?php require $currentPage['file']; ?>
        </main>
    </section>
</section>
