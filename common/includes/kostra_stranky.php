<?php
/*
 * Ucel souboru: Vykresli zakladni HTML kostru prihlasene aplikace.
 * Vsechna data a URL pripravuje common/lib/priprava_kostry_stranky.php.
 */
declare(strict_types=1);
?>
<!doctype html>
<html lang="cs" data-theme-level="<?= h((string)$cbThemeLevel) ?>">
<?php require __DIR__ . '/nacti_styly.php'; ?>
<body class="cb-context--<?= h($cbVisualModule) ?>">
<main id="obal_main" class="obal_main cb-context--<?= h($cbVisualModule) ?>" data-obal-main="1">
<?php
// Vykresli viditelnou hlavicku aplikace.
require_once __DIR__ . '/hlavicka.php';

// Vykresli aktivni modul do hlavni pracovni plochy.
cb_modul_nacti($cbInitialModule);
?>
</main>
<?php
// Vypise konfiguraci pro prohlizec a vsechny spolecne skripty.
require __DIR__ . '/nacti_skripty.php';
?>
</body>
</html>
