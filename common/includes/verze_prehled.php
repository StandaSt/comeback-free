<?php
/* Účel: Vykreslí ručně zadané změny bez interních údajů o autorovi a oprávněních. */
declare(strict_types=1);
$typy = cb_verze_typy();
?>
<?php if ($radky === []): ?><p>Zatím nejsou zadané žádné změny.</p><?php endif; ?>
<?php foreach ($radky as $radek): ?>
    <article class="cb_verze_release">
        <div class="cb_verze_meta"><strong>Verze <?= h(cb_verze_cislo($radek)) ?></strong><span><?= h($typy[(int)$radek['typ']] ?? '') ?></span></div>
        <div class="cb_verze_text"><?= h((string)$radek['obsah']) ?></div>
    </article>
<?php endforeach; ?>
