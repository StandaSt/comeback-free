<?php
declare(strict_types=1);

require_once __DIR__ . '/../www/lib/session_boot.php';
require_once __DIR__ . '/../www/config/secrets.php';
require_once __DIR__ . '/../www/lib/app.php';
require_once __DIR__ . '/hr_includes/hr_data.php';

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

$cbUser = $_SESSION['cb_user'] ?? [];
$roleId = is_array($cbUser) ? (int)($cbUser['id_role'] ?? 0) : 0;

if ($roleId !== 1) {
    ?><!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>PizzaComeback - HR</title>

<style>
* {
    box-sizing: border-box;
}

html,
body {
    width: 100%;
    height: 100%;
    margin: 0;
}

body {
    display: flex;
    flex-direction: column;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f5f5;
    overflow: hidden;
}

.menu {
    flex: 0 0 auto;
    padding: 15px;
    text-align: center;
    background: #ffffff;
    border-bottom: 1px solid #dcdcdc;
}

.menu a {
    display: inline-block;
    margin: 0 10px;
    padding: 10px 20px;
    text-decoration: none;
    font-weight: bold;
    color: #ffffff;
    background: #2f6fed;
    border-radius: 6px;
}

.menu a:hover {
    background: #1f56c5;
}

.container {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.container img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
</head>

<body>

<div class="menu">
    <a href="<?= h(cb_module_url('is')) ?>">IS</a>
    <a href="<?= h(cb_module_url('smeny')) ?>">Směny</a>
</div>

<div class="container">
    <img src="pripravujeme_hr.png" alt="HR se připravuje">
</div>

</body>
</html>
<?php
    exit;
}

$pages = [
    'dashboard' => [
        'file' => __DIR__ . '/hr_pages/dashboard.php',
        'title' => 'Přehled',
    ],
    'nabor' => [
        'file' => __DIR__ . '/hr_pages/placeholder.php',
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
$flash = $_SESSION['hr_flash'] ?? null;
unset($_SESSION['hr_flash']);
$hrCssUrl = ((string)($GLOBALS['PROSTREDI'] ?? '') === 'SERVER')
    ? 'https://www.comebacks.cz/style/hr/hr.css'
    : cb_root_url('style/hr/hr.css');

?><!DOCTYPE html>
<html lang="cs" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Comeback HR</title>
    <link rel="stylesheet" href="<?= h($hrCssUrl) ?>">
</head>
<body>
<div class="app">
    <?php require __DIR__ . '/hr_includes/menu.php'; ?>

    <div class="workspace">
        <?php require __DIR__ . '/hr_includes/topbar.php'; ?>

        <main class="content">
            <?php if (is_array($flash) && isset($flash['text'])): ?>
                <div class="notice <?= h((string)($flash['type'] ?? 'info')) ?>"><?= h((string)$flash['text']) ?></div>
            <?php endif; ?>
            <?php require $currentPage['file']; ?>
        </main>
    </div>
</div>

<script src="<?= h(cb_current_module_url('hr_assets/js/hr.js')) ?>"></script>
</body>
</html>
