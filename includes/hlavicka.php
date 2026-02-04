<?php
// includes/hlavicka.php  * Verze: V16 * Aktualizace: 2.2.2026 * Počet řádků: 102
declare(strict_types=1);

if (defined('COMEBACK_HEADER_RENDERED')) {
    return;
}
define('COMEBACK_HEADER_RENDERED', true);

    require_once __DIR__ . '/../lib/bootstrap.php';
/*
 * Volba menu:
 * - ?menu=dropdown  (výchozí)
 * - ?menu=sidebar
 */
$cb_menu_mode = (string)($_GET['menu'] ?? 'dropdown');
$cb_menu_mode = ($cb_menu_mode === 'sidebar') ? 'sidebar' : 'dropdown';
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comeback</title>

    <!-- styly -->
    <link rel="stylesheet" href="<?= h(cb_url('style/global1.css')) ?>">
    <link rel="stylesheet" href="<?= h(cb_url('style/hlavicka1.css')) ?>">
    <link rel="stylesheet" href="<?= h(cb_url('style/central1.css')) ?>">
    <link rel="stylesheet" href="<?= h(cb_url('style/paticka1.css')) ?>">
    <link rel="stylesheet" href="<?= h(cb_url('style/menu1.css')) ?>">
    <link rel="stylesheet" href="<?= h(cb_url('style/ikony_svg1.css')) ?>">
</head>
<body>

<div class="container">

    <div class="header">

        <!-- LEVÁ ČÁST: LOGO -->
        <div class="header-left">
            <a href="https://pizzacomeback.cz" target="_blank" rel="noopener">
                <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
            </a>
        </div>

        <!-- CENTRÁLNÍ ČÁST: 3 TECH BLOKY -->
        <div class="header-central">

            <div class="hc-col hc-col">
                <div class="hc-row"><span class="hc-label">Server:</span><span class="hc-value">cosi.cz</span></div>
                <div class="hc-row"><span class="hc-label">Prostředí:</span><span class="hc-value"><?= h($PROSTREDI) ?></span></div>
                <div class="hc-row"><span class="hc-label">DB:</span><span class="hc-value">OK</span></div>
                <div class="hc-row"><span class="hc-label">Dotazy:</span><span class="hc-value">123</span></div>
            </div>

            <div class="hc-col hc-col">
                <div class="hc-row"><span class="hc-label">Aktualizace:</span><span class="hc-value">22.1.2026 14:30</span></div>
                <div class="hc-row"><span class="hc-label">Cache:</span><span class="hc-value">zapnuta</span></div>
                <div class="hc-row"><span class="hc-label">Verze IS:</span><span class="hc-value">DEV</span></div>
                <div class="hc-row"><span class="hc-label">Build:</span><span class="hc-value">---</span></div>
            </div>

            <div class="hc-col hc-col">
                <div class="hc-row"><span class="hc-label">API:</span><span class="hc-value">---</span></div>
                <div class="hc-row"><span class="hc-label">DB host:</span><span class="hc-value">---</span></div>
                <div class="hc-row"><span class="hc-label">PHP:</span><span class="hc-value"><?= h(PHP_VERSION) ?></span></div>
                <div class="hc-row"><span class="hc-label">Pozn.:</span><span class="hc-value">---</span></div>
            </div>

        </div>

        <!-- PRAVÁ ČÁST: LOGIN -->
        <div class="header-right">
            <div class="hr-col hr-login">
                <?php
                $login = __DIR__ . '/login_form.php';
                if (is_file($login)) {
                    require $login;
                }
                ?>
            </div>
        </div>

    </div>

    <!-- CENTRAL -->
    <div class="central menu-<?= h($cb_menu_mode) ?>">
        <div class="central-menu">
            <?php
            if ($cb_menu_mode === 'sidebar') {
                require_once __DIR__ . '/menu_s.php';
            } else {
                require_once __DIR__ . '/menu_d.php';
            }
            ?>
        </div>

        <div class="central-content">
            <main>
<?php
// includes/hlavicka.php  * Verze: V16 
// Aktualizace: 2.2.2026 * Počet řádků: 102 
// * konec souboru
