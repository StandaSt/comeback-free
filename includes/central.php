<?php
// includes/central.php * Verze: V1 * Aktualizace: 24.2.2026

/*
 * CENTRAL (menu + obsah)
 *
 * Co dělá:
 * - podle session zvolí režim menu (dropdown | sidebar)
 * - načte příslušné menu (includes/menu_d.php | includes/menu_s.php)
 * - vykreslí obal central + otevře a uzavře <main>
 * - vloží obsah stránky (pages/<pageKey>.php) předaný z index.php
 *
 * Volá / závisí na:
 * - session: $_SESSION['cb_menu_mode']
 * - includes/menu_d.php, includes/menu_s.php
 * - proměnné z index.php: $cb_page_exists, $cb_page_file
 */

declare(strict_types=1);

$cb_menu_mode = (string)($_SESSION['cb_menu_mode'] ?? 'dropdown');
if ($cb_menu_mode !== 'sidebar') {
    $cb_menu_mode = 'dropdown';
}
?>

<div class="central menu-<?= h($cb_menu_mode) ?>">
    <?php
    if ($cb_menu_mode === 'sidebar') {
        require_once __DIR__ . '/menu_s.php';
    } else {
        require_once __DIR__ . '/menu_d.php';
    }
    ?>

    <div class="central-content">
        <main>
            <?php
            if (!empty($cb_page_exists) && !empty($cb_page_file) && is_file($cb_page_file)) {
                require $cb_page_file;
            } else {
                echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
                echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
            }
            ?>
        </main>
    </div>
</div>

<?php
/* includes/central.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 52 */
// Konec souboru