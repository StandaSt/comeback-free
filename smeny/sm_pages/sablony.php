<?php
declare(strict_types=1);

/* Účel souboru: Zobrazí souhrny a editor šablon s přímou úpravou časů; volitelné HR sloty přidává jen do zvoleného dne. */

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
<section class="pp smeny_content <?= cb_smeny_styl_trida($smStyl) ?> smeny_templates" data-module="smeny" data-page="sablony">
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
                    <?php
                    $smCardUsed=[];
                    foreach($smTemplateRow['souhrn_minuty'] as $smDaily) foreach($smDaily as $smSlotId=>$smMinutes) if($smMinutes>0) $smCardUsed[]=$smSlotId;
                    $smCardPositions = cb_smeny_sloty_zobrazene($smTemplateBranches[(int)$smTemplateRow['id_pob']],$smCardUsed);
                    ?>
                    <article class="smeny_template_card">
                        <div class="smeny_template_card_row">
                          <a class="smeny_template_card_title" href="<?= h(cb_root_url('index.php?m=smeny&page=sablony&id=' . (int)$smTemplateRow['id_smeny_sablona'])) ?>"><span>Šablona:</span> <strong><?= h((string)$smTemplateRow['nazev']) ?></strong></a>
                          <div class="smeny_template_week_totals">
                          <?php foreach($smCardPositions as $smPositionId=>$smPositionName): $smTotal=0; foreach($smTemplateRow['souhrn_minuty'] as $smDaily) $smTotal+=(int)($smDaily[$smPositionId] ?? 0); ?>
                            <span><?= h($smPositionName) ?> <strong><?= h($smDurationText($smTotal)) ?></strong></span>
                          <?php endforeach; ?>
                          </div>
                          <button type="button" data-smeny-template-detail aria-expanded="false" aria-controls="smeny-template-summary-<?= (int)$smTemplateRow['id_smeny_sablona'] ?>">Detail</button>
                        </div>
                        <div class="smeny_template_card_summary" id="smeny-template-summary-<?= (int)$smTemplateRow['id_smeny_sablona'] ?>" hidden>
                            <?php foreach ($smDayNames as $smDay => $smDayName): ?>
                                <span class="smeny_template_card_day">
                                    <strong><?= h($smDayName) ?></strong>
                                    <?php foreach ($smCardPositions as $smPositionId => $smPositionName): ?>
                                        <span class="smeny_template_card_total"><small><?= h($smPositionName) ?>:</small> <strong><?= h($smDurationText((int)($smTemplateRow['souhrn_minuty'][$smDay][$smPositionId] ?? 0))) ?></strong></span>
                                    <?php endforeach; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </article>
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
            <input type="hidden" name="otisk" value="<?= h(cb_smeny_sablona_otisk($smTemplate)) ?>">

            <div class="smeny_template_toolbar">
                <label class="smeny_template_name"><span>Název šablony</span><input type="text" name="nazev" maxlength="120" value="<?= h((string)$smTemplate['nazev']) ?>" required></label>
                <label class="smeny_template_resolution"><input type="checkbox" data-smeny-half-hours> po 30 min.</label>
            </div>

            <div class="smeny_template_week">
                <?php foreach ($smDayNames as $smDay => $smDayName): ?>
                    <?php $smClosing = cb_smeny_sablony_zaviraci_cas($smTemplateBranch, $smDay); $smBlockIndex = 0; ?>
                    <article class="smeny_template_day" data-smeny-template-day="<?= $smDay ?>" data-closing="<?= h($smClosing) ?>">
                        <header class="smeny_template_day_header">
                            <strong class="smeny_template_day_name"><?= h($smDayName) ?></strong>
                            <div class="smeny_classic_tools">
                                <?php foreach (cb_smeny_sablony_zakladni_pozice($smTemplateBranch) as $smPositionId => $smPositionName): ?>
                                    <button type="button" class="smeny_template_add" data-smeny-template-add data-slot="<?= $smPositionId ?>">+ <?= h($smPositionId === 5 ? 'Operátor' : $smPositionName) ?></button>
                                <?php endforeach; ?>
                                <?php $smOptionalPositions=array_diff_key($smTemplatePositions,cb_smeny_sablony_zakladni_pozice($smTemplateBranch)); if($smOptionalPositions!==[]): ?>
                                <details class="smeny_slot_picker"><summary>+ Další slot</summary><div class="smeny_slot_picker_options">
                                    <?php foreach($smOptionalPositions as $smPositionId=>$smPositionName): ?><button type="button" data-smeny-template-add data-slot="<?= $smPositionId ?>">+ <?= h($smPositionName) ?></button><?php endforeach; ?>
                                </div></details>
                                <?php endif; ?>
                            </div>
                        </header>
                        <div class="smeny_template_columns">
                            <?php foreach ($smTemplatePositions as $smPositionId => $smPositionName): ?>
                                <section class="smeny_template_column" data-smeny-template-column data-day="<?= $smDay ?>" data-slot="<?= $smPositionId ?>" data-optional="<?= in_array($smPositionId,cb_smeny_sloty_zakladni_id($smTemplateBranch),true)?'0':'1' ?>">
                                    <header><strong><?= h($smPositionName) ?></strong></header>
                                    <div class="smeny_template_column_slots" data-smeny-template-column-slots>
                                        <?php foreach ($smTemplate['blocks'][$smDay] as $smBlock): ?>
                                            <?php if ((int)$smBlock['id_slot'] !== $smPositionId) { continue; } ?>
                                            <div class="smeny_template_slot" data-smeny-template-slot>
                                                <input type="hidden" name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][id]" value="<?= (int)$smBlock['id_smeny_sablona_blok'] ?>" data-smeny-field="id">
                                                <input type="hidden" name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][id_slot]" value="<?= $smPositionId ?>" data-smeny-field="id_slot">
                                                <input type="time" step="60" required name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][cas_od]" value="<?= h((string)$smBlock['cas_od']) ?>" aria-label="Začátek slotu <?= h($smPositionName) ?>" data-smeny-field="cas_od">
                                                <span aria-hidden="true">–</span>
                                                <input type="time" step="60" required name="blocks[<?= $smDay ?>][<?= $smBlockIndex ?>][cas_do]" value="<?= h((string)$smBlock['cas_do']) ?>" aria-label="Konec slotu <?= h($smPositionName) ?>" data-smeny-field="cas_do">
                                                <button type="button" class="smeny_template_remove" data-smeny-template-remove aria-label="Odstranit slot <?= h($smPositionName) ?>">×</button>
                                            </div>
                                            <?php $smBlockIndex++; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <template data-smeny-template-slot-prototype>
                                        <div class="smeny_template_slot" data-smeny-template-slot>
                                            <input type="hidden" value="0" data-smeny-field="id">
                                            <input type="hidden" value="<?= $smPositionId ?>" data-smeny-field="id_slot">
                                            <input type="time" step="60" required value="10:00" aria-label="Začátek slotu <?= h($smPositionName) ?>" data-smeny-field="cas_od">
                                            <span aria-hidden="true">–</span>
                                            <input type="time" step="60" required value="<?= h($smClosing) ?>" aria-label="Konec slotu <?= h($smPositionName) ?>" data-smeny-field="cas_do">
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

            <div class="smeny_template_save_state" role="status" aria-live="polite" data-smeny-template-status></div>
        </form>
    <?php endif; ?>
</section>
