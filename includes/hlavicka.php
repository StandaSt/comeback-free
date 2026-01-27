<?php
// includes/hlavicka.php V15 – počet řádků: 100 – aktuální čas v ČR: 22.1.2026
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
    <link rel="stylesheet" href="<?= h(cb_url('style/menu.css')) ?>">
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

        <!-- PRAVÁ ČÁST: 4 BLOKY (3× technické údaje + 1× login) -->
        <div class="header-right">

            <div class="hr-col hc-col">
                <div class="hc-row"><span class="hc-label">Server:</span><span class="hc-value">cosi.cz</span></div>
                <div class="hc-row"><span class="hc-label">Prostředí:</span><span class="hc-value"><?= h($PROSTREDI) ?></span></div>
                <div class="hc-row"><span class="hc-label">DB:</span><span class="hc-value">OK</span></div>
                <div class="hc-row"><span class="hc-label">Dotazy:</span><span class="hc-value">123</span></div>
            </div>

            <div class="hr-col hc-col">
                <div class="hc-row"><span class="hc-label">Aktualizace:</span><span class="hc-value">22.1.2026 14:30</span></div>
                <div class="hc-row"><span class="hc-label">Cache:</span><span class="hc-value">zapnuta</span></div>
                <div class="hc-row"><span class="hc-label">Verze IS:</span><span class="hc-value">DEV</span></div>
                <div class="hc-row"><span class="hc-label">Build:</span><span class="hc-value">---</span></div>
            </div>

            <div class="hr-col hc-col">
                <div class="hc-row"><span class="hc-label">API:</span><span class="hc-value">---</span></div>
                <div class="hc-row"><span class="hc-label">DB host:</span><span class="hc-value">---</span></div>
                <div class="hc-row"><span class="hc-label">PHP:</span><span class="hc-value"><?= h(PHP_VERSION) ?></span></div>
                <div class="hc-row"><span class="hc-label">Pozn.:</span><span class="hc-value">---</span></div>
            </div>

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
    <div class="cb-central cb-central--<?= h($cb_menu_mode) ?>">
        <div class="cb-central-menu">
            <?php
            if ($cb_menu_mode === 'sidebar') {
                require_once __DIR__ . '/menu_s.php';
            } else {
                require_once __DIR__ . '/menu_d.php';
            }
            ?>
        </div>

        <div class="cb-central-content">
            <main>

<?php
/* includes/hlavicka.php V15 – konec souboru */ 
