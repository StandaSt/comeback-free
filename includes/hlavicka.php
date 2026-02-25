<?php
// includes/hlavicka.php * Verze: V25 * Aktualizace: 24.2.2026

/*
 * HLAVIČKA (jen <div class="header"> … </div>)
 *
 * Co dělá:
 * - vykreslí horní panel aplikace (logo + 4 bloky)
 * - načte data pro infoblok přes cb_header_info()
 * - vloží login blok (includes/login_form.php)
 *
 * Volá / závisí na:
 * - cb_header_info(), cb_url(), h()
 * - includes/login_form.php
 */

declare(strict_types=1);

if (defined('COMEBACK_HEADER_RENDERED')) {
    return;
}
define('COMEBACK_HEADER_RENDERED', true);

/* technická data pro hlavičku */
$CB_HEADER = cb_header_info();
?>

<div class="header">

    <!-- LEVÁ ČÁST: LOGO -->
    <div class="header-left">
        <div class="header-left-inner">
            <a href="https://pizzacomeback.cz" target="_blank" rel="noopener">
                <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
            </a>
        </div>
    </div>

    <!-- CENTRÁLNÍ ČÁST: 4 TECH BLOKY -->
    <div class="header-central">

        <!-- BLOK 1: TECH DATA -->
        <div class="hc-col">
            <div class="hc-row"><span class="hc-label"></span><span class="hc-value">Server</span></div>
            <div class="hc-row"><span class="hc-label">Server:</span><span class="hc-value"><?= h($CB_HEADER['server'] ?? '---') ?></span></div>
            <div class="hc-row"><span class="hc-label">Host:</span><span class="hc-value"><?= h($CB_HEADER['host'] ?? '---') ?></span></div>
            <div class="hc-row"><span class="hc-label">PHP verze:</span><span class="hc-value"><?= h(PHP_VERSION) ?></span></div>
            <div class="hc-row"><span class="hc-label">Aktualizace:</span><span class="hc-value"><?= h($CB_HEADER['aktualizace'] ?? '---') ?></span></div>
        </div>

        <!-- BLOK 2 – STAV APLIKACE -->
        <div class="hc-col">
            <div class="hc-row"><span class="hc-label"></span><span class="hc-value">Databáze</span></div>
            <div class="hc-row"><span class="hc-label">Název DB:</span><span class="hc-value"><?= h($CB_HEADER['db'] ?? '---') ?></span></div>
            <div class="hc-row"><span class="hc-label">Verze DB:</span><span class="hc-value">10.4.32-MariaDB</span></div>
            <div class="hc-row"><span class="hc-label">Dotazů:</span><span class="hc-value">652 145 / 325</span></div>
            <div class="hc-row"><span class="hc-label">Velikost:</span><span class="hc-value">826 MB</span></div>
        </div>

        <!-- BLOK 3 – INTEGRACE -->
        <div class="hc-col">
            <div class="hc-row"><span class="hc-label"></span><span class="hc-value">Objednávky</span></div>
            <div class="hc-row"><span class="hc-label">Celkem:</span><span class="hc-value">38 468</span></div>
            <div class="hc-row"><span class="hc-label">Položky:</span><span class="hc-value">751</span></div>
            <div class="hc-row"><span class="hc-label">Něco:</span><span class="hc-value">---</span></div>
            <div class="hc-row"><span class="hc-label">Něco:</span><span class="hc-value">---</span></div>
        </div>

        <!-- BLOK 4 – PŘIHLÁŠENÍ -->
        <div class="hc-col">
            <?php
            $login = __DIR__ . '/login_form.php';
            if (is_file($login)) {
                require $login;
            }
            ?>
        </div>

    </div>

</div>

<?php
/* includes/hlavicka.php * Verze: V25 * Aktualizace: 24.2.2026 * Počet řádků: 85 */
// Konec souboru