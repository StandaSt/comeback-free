<?php
declare(strict_types=1);

/* Ucel souboru: Kompaktni nastaveni dne a cele hodiny uzaverky pozadavku s pravem 410. */

if (!cb_smeny_nastaveni_ma_pravo()) {
    http_response_code(403);
    ?>
    <section class="pp smeny_content" data-module="smeny" data-page="nastaveni">
        <header class="pp_header"><h1>Nastavení</h1></header>
        <p class="smeny_notice smeny_notice--error">Nemáte právo měnit nastavení Směn.</p>
    </section>
    <?php
    return;
}

require_once __DIR__ . '/../sm_lib/sm_uzaverka.php';
$smUzaverka = cb_smeny_uzaverka_nacti(db());
$smNastaveniFlash = $_SESSION['sm_nastaveni_flash'] ?? null;
unset($_SESSION['sm_nastaveni_flash']);
?>
<section class="pp smeny_content" data-module="smeny" data-page="nastaveni">
    <header class="pp_header">
        <h1>Nastavení směn</h1>
    </header>

    <div class="smeny_intro smeny_settings">
        <?php if (is_array($smNastaveniFlash)): ?><p class="smeny_notice <?= $smNastaveniFlash['ok'] ? 'smeny_notice--success' : 'smeny_notice--error' ?>" role="status"><?= h($smNastaveniFlash['text']) ?></p><?php endif; ?>
        <strong>Uzávěrka zadávání požadavků</strong>
        <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=nastaveni')) ?>" class="smeny_settings_form">
            <input type="hidden" name="action" value="smeny_nastaveni_ulozit">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <label>Den<select name="uzaverka_den"><?php foreach (cb_smeny_uzaverka_dny() as $day => $label): ?><option value="<?= $day ?>"<?= $day === $smUzaverka['den'] ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
            <label>Hodina<select name="uzaverka_hodina"><?php for ($hour = 0; $hour < 24; $hour++): ?><option value="<?= $hour ?>"<?= $hour === $smUzaverka['hodina'] ? ' selected' : '' ?>><?= sprintf('%02d:00', $hour) ?></option><?php endfor; ?></select></label>
            <button type="submit"<?= !$smUzaverka['pripraveno'] ? ' disabled' : '' ?>>Uložit</button>
        </form>
        <p>Uzávěrka platí v týdnu před plánovaným rozpisem pro všechny pobočky.</p>
        <?php if (!$smUzaverka['pripraveno']): ?><p class="smeny_notice">Nastavení uzávěrky zatím není připravené k ukládání.</p><?php endif; ?>
        <p>Pobočka začne používat interní směny automaticky ve chvíli, kdy v IS zveřejní rozpis daného týdne. Rozpracovaný plán externí směny nenahrazuje. Není nutné nastavovat testovací pobočky.</p>
    </div>
</section>
