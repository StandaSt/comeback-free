<?php
declare(strict_types=1);

/* Účel souboru: Zobrazí seznam šablon a kompaktní týdenní editor slotů. */

$smTemplateFlash = cb_smeny_sablony_flash_nacist();
$smTemplates = cb_smeny_sablony_seznam($smDb, $smTemplateBranches);
$smTemplateId = max(0, (int)($_GET['id'] ?? 0));
$smTemplate = $smTemplateId > 0 ? cb_smeny_sablona_nacist($smDb, $smTemplateId, $smTemplateBranches) : null;
$smDayNames = [1 => 'Pondělí', 2 => 'Úterý', 3 => 'Středa', 4 => 'Čtvrtek', 5 => 'Pátek', 6 => 'Sobota', 7 => 'Neděle'];
$smDurationText = static function (int $minutes): string {
    $hours = intdiv(max(0, $minutes), 60);
    $remainingMinutes = max(0, $minutes) % 60;
    return $remainingMinutes === 0 ? $hours . ' h' : $hours . ' h ' . $remainingMinutes . ' min';
};
?>
<section class="pp smeny_content smeny_templates" data-module="smeny" data-page="sablony">
    <header class="pp_header smeny_templates_header">
        <div class="smeny_templates_title">
            <?php if ($smTemplate !== null): ?>
                <a class="smeny_templates_back" href="<?= h(cb_root_url('index.php?m=smeny&page=sablony')) ?>" title="Zpět na seznam šablon" aria-label="Zpět na seznam šablon">←</a>
            <?php endif; ?>
            <h1>Šablony směn</h1>
            <?php if ($smTemplate !== null): ?>
                <span><?= h((string)$smTemplateBranches[(int)$smTemplate['id_pob']]['nazev']) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($smTemplateFlash !== null): ?>
        <p class="smeny_notice <?= $smTemplateFlash['type'] === 'success' ? 'smeny_notice--success' : 'smeny_notice--error' ?>"><?= h($smTemplateFlash['text']) ?></p>
    <?php endif; ?>

    <?php if ($smTemplateBranches === []): ?>
        <p class="smeny_notice smeny_notice--error">Nemáte vybranou žádnou povolenou pobočku.</p>
    <?php elseif ($smTemplateId > 0 && $smTemplate === null): ?>
        <p class="smeny_notice smeny_notice--error">Šablona nebyla nalezena nebo nepatří do vybraných poboček.</p>
    <?php elseif ($smTemplate === null): ?>
        <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=sablony')) ?>" class="smeny_template_create">
            <input type="hidden" name="action" value="smeny_sablona_ulozit">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <label><span>Název nové šablony</span><input type="text" name="nazev" maxlength="120" required></label>
            <label><span>Pobočka</span><select name="id_pob" required><?php foreach ($smTemplateBranches as $smBranch): ?><option value="<?= h((string)$smBranch['id_pob']) ?>"><?= h((string)$smBranch['nazev']) ?></option><?php endforeach; ?></select></label>
            <button type="submit" class="smeny_primary_button">Vytvořit šablonu</button>
        </form>

        <div class="smeny_template_list">
            <?php if ($smTemplates === []): ?>
                <p class="smeny_template_empty">Pro vybrané pobočky zatím není vytvořena žádná šablona.</p>
            <?php else: ?>
                <?php foreach ($smTemplates as $smTemplateRow): ?>
                    <?php $smCardPositions = cb_smeny_sablony_pozice($smTemplateBranches[(int)$smTemplateRow['id_pob']]); ?>
                    <a class="smeny_template_card" href="<?= h(cb_root_url('index.php?m=smeny&page=sablony&id=' . (int)$smTemplateRow['id_smeny_sablona'])) ?>">
                        <strong class="smeny_template_card_title"><span>Název šablony:</span> <?= h((string)$smTemplateRow['nazev']) ?></strong>
                        <span class="smeny_template_card_summary">
                            <?php foreach ($smDayNames as $smDay => $smDayName): ?>
                                <span class="smeny_template_card_day">
                                    <strong><?= h($smDayName) ?></strong>
                                    <?php foreach ($smCardPositions as $smPositionId => $smPositionName): ?>
                                        <span class="smeny_template_card_total"><small><?= h($smPositionName) ?>:</small> <strong><?= h($smDurationText((int)($smTemplateRow['souhrn_minuty'][$smDay][$smPositionId] ?? 0))) ?></strong></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endforeach; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php $smTemplateBranch = $smTemplateBranches[(int)$smTemplate['id_pob']]; $smTemplatePositions = cb_smeny_sablony_pozice($smTemplateBranch); ?>
        <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=sablony&id=' . $smTemplateId)) ?>" class="smeny_template_editor" data-smeny-template-form>
            <input type="hidden" name="action" value="smeny_sablona_tyden_ulozit">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <input type="hidden" name="id_smeny_sablona" value="<?= $smTemplateId ?>">
            <input type="hidden" name="id_pob" value="<?= (int)$smTemplate['id_pob'] ?>">

            <label class="smeny_template_name"><span>Název šablony</span><input type="text" name="nazev" maxlength="120" value="<?= h((string)$smTemplate['nazev']) ?>" required></label>

            <div class="smeny_template_week">
                <?php foreach ($smDayNames as $smDay => $smDayName): ?>
                    <?php $smClosing = cb_smeny_sablony_zaviraci_cas($smTemplateBranch, $smDay); $smStartTimes = cb_smeny_sablony_casy($smClosing, false); $smEndTimes = cb_smeny_sablony_casy($smClosing, true); $smBlockIndex = 0; ?>
                    <article class="smeny_template_day" data-smeny-template-day="<?= $smDay ?>">
                        <strong class="smeny_template_day_name"><?= h($smDayName) ?></strong>
                        <div class="smeny_template_columns">
                            <?php foreach ($smTemplatePositions as $smPositionId => $smPositionName): ?>
                                <section class="smeny_template_column" data-smeny-template-column data-day="<?= $smDay ?>" data-slot="<?= $smPositionId ?>">
                                    <header><strong><?= h($smPositionName) ?></strong><button type="button" class="smeny_template_add" data-smeny-template-add>+ Přidat slot</button></header>
                                    <div class="smeny_template_column_slots" data-smeny-template-column-slots>
                                        <?php foreach ($smTemplate['blocks'][$smDay] as $smBlock): ?>
                                            <?php if ((int)$smBlock['id_slot'] !== $smPositionId) { continue; } ?>
                                            <div class="smeny_template_slot" data-smeny-template-slot>
                                                <input type="hidden" name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][id]" value="<?= (int)$smBlock['id_smeny_sablona_blok'] ?>" data-smeny-field="id">
                                                <input type="hidden" name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][id_slot]" value="<?= $smPositionId ?>" data-smeny-field="id_slot">
                                                <select name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][cas_od]" aria-label="Začátek slotu <?= h($smPositionName) ?>" data-smeny-field="cas_od"><?php foreach ($smStartTimes as $smTime): ?><option value="<?= h($smTime) ?>"<?= (string)$smBlock['cas_od'] === $smTime ? ' selected' : '' ?>><?= h($smTime) ?></option><?php endforeach; ?></select>
                                                <span aria-hidden="true">–</span>
                                                <select name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][cas_do]" aria-label="Konec slotu <?= h($smPositionName) ?>" data-smeny-field="cas_do"><?php foreach ($smEndTimes as $smTime): ?><option value="<?= h($smTime) ?>"<?= (string)$smBlock['cas_do'] === $smTime ? ' selected' : '' ?>><?= h($smTime) ?></option><?php endforeach; ?></select>
                                                <button type="button" class="smeny_template_remove" data-smeny-template-remove aria-label="Odstranit slot <?= h($smPositionName) ?>">×</button>
                                            </div>
                                            <?php $smBlockIndex++; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <template data-smeny-template-slot-prototype>
                                        <div class="smeny_template_slot" data-smeny-template-slot>
                                            <input type="hidden" value="0" data-smeny-field="id">
                                            <input type="hidden" value="<?= $smPositionId ?>" data-smeny-field="id_slot">
                                            <select aria-label="Začátek slotu <?= h($smPositionName) ?>" data-smeny-field="cas_od"><?php foreach ($smStartTimes as $smTime): ?><option value="<?= h($smTime) ?>"<?= $smTime === '10:00' ? ' selected' : '' ?>><?= h($smTime) ?></option><?php endforeach; ?></select>
                                            <span aria-hidden="true">–</span>
                                            <select aria-label="Konec slotu <?= h($smPositionName) ?>" data-smeny-field="cas_do"><?php foreach ($smEndTimes as $smTime): ?><option value="<?= h($smTime) ?>"<?= $smTime === $smClosing ? ' selected' : '' ?>><?= h($smTime) ?></option><?php endforeach; ?></select>
                                            <button type="button" class="smeny_template_remove" data-smeny-template-remove aria-label="Odstranit slot <?= h($smPositionName) ?>">×</button>
                                        </div>
                                    </template>
                                </section>
                            <?php endforeach; ?>
                        </div>
                        <span class="smeny_template_closing">zavírací čas <?= h($smClosing) ?></span>
                    </article>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="smeny_template_save_all" data-smeny-template-actions hidden>Uložit upravenou šablonu</button>
        </form>
    <?php endif; ?>
</section>
