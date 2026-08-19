<?php
/*
 * Ucel souboru: Sklada obsah stranky prehled modulu HR z jej samostatnych bloku.
 * Neobsahuje PP ani hlavni layout; data dostava z jednoho datoveho poskytovatele.
 */
declare(strict_types=1);

require_once __DIR__ . '/../hr_lib/hr_prehled_data.php';

/*
 * Kontext je vytvoren pouze z interne pripravenych dat modulu HR.
 * Jednotlive bloky tak dostavaji stejne promenne jako pred rozdelenim stranky.
 */
$hrPrehledContext = hr_prehled_data($db);
extract($hrPrehledContext, EXTR_SKIP);
?>
<?php require __DIR__ . '/../hr_blocks/hr_prehled_statistiky.php'; ?>

<?php require __DIR__ . '/../hr_blocks/hr_prehled_agendy.php'; ?>

<section class="hr_prehled_grid">
    <?php require __DIR__ . '/../hr_blocks/hr_prehled_posledni_zamestnanci.php'; ?>
    <?php require __DIR__ . '/../hr_blocks/hr_prehled_rychle_odkazy.php'; ?>
</section>

<?php require __DIR__ . '/../hr_blocks/hr_prehled_tabulka_zamestnancu.php'; ?>
