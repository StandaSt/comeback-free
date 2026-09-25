<?php
declare(strict_types=1);

/*
 * Desktopová stránka pro zadání požadavků jednoho pracovníka na celý týden.
 */

$smFlash = $_SESSION['cb_smeny_pozadavky_flash'] ?? null;
unset($_SESSION['cb_smeny_pozadavky_flash']);
$smData = $smPerson ? cb_smeny_pozadavky_nacist($smDb, $smPerson, $smWeek) : ['blocks' => [], 'own_day' => '', 'occupied' => [], 'saved' => false, 'saved_at' => ''];
$smCopiedWeek = cb_smeny_pozadavky_kopie($smDb, $smPerson, $smWeeks, $smWeekIndex);
if ($smCopiedWeek !== null) {
    $smData['blocks'] = $smCopiedWeek['blocks'];
}
$smHasMainBranch = $smPerson !== null && (int)$smPerson['id_pob'] > 0;
$smHasHppSetup = $smHasMainBranch && (int)$smPerson['id_slot'] > 0;
$smCanSave = $smPerson !== null
    && !empty($smWeek['open'])
    && ((int)$smPerson['je_hpp'] === 1 ? $smHasHppSetup : $smHasMainBranch);
$smNextWeekSaved = false;
if ($smPerson !== null && $smWeekIndex < 3) {
    $smNextWeekData = cb_smeny_pozadavky_nacist($smDb, $smPerson, $smWeeks[$smWeekIndex + 1]);
    $smNextWeekSaved = !empty($smNextWeekData['saved']);
}
$smCanCopyWeek = $smPerson !== null
    && (int)$smPerson['je_hpp'] !== 1
    && !empty($smData['saved'])
    && $smData['blocks'] !== []
    && $smWeekIndex < 3
    && !empty($smWeeks[$smWeekIndex + 1]['open'])
    && !$smNextWeekSaved;
$smSavedAtLabel = '';
if ((string)$smData['saved_at'] !== '') {
    $smSavedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$smData['saved_at'], new DateTimeZone('Europe/Prague'));
    $smSavedAtLabel = $smSavedAt ? $smSavedAt->format('j. n. Y \v H:i') : (string)$smData['saved_at'];
}
?>
<section class="pp smeny_content smeny_requests" data-module="smeny" data-page="pozadavky">
    <header class="pp_header smeny_requests_header">
        <h1>Požadavky na směny</h1>
        <div class="smeny_week_navigation" aria-label="Volba týdne">
            <?php if ($smWeekIndex > 0): ?>
                <a class="smeny_week_arrow" href="<?= h(cb_root_url('index.php?m=smeny&page=pozadavky&week=' . ($smWeekIndex - 1))) ?>" aria-label="Předchozí týden">‹</a>
            <?php else: ?>
                <span class="smeny_week_arrow smeny_week_arrow--disabled" aria-hidden="true">‹</span>
            <?php endif; ?>
            <strong><?= h($smWeek['start']->format('j. n.')) ?>–<?= h($smWeek['end']->format('j. n. Y')) ?></strong>
            <?php if ($smWeekIndex < 3): ?>
                <a class="smeny_week_arrow" href="<?= h(cb_root_url('index.php?m=smeny&page=pozadavky&week=' . ($smWeekIndex + 1))) ?>" aria-label="Následující týden">›</a>
            <?php else: ?>
                <span class="smeny_week_arrow smeny_week_arrow--disabled" aria-hidden="true">›</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (is_array($smFlash)): ?>
        <p class="smeny_notice <?= ($smFlash['type'] ?? '') === 'success' ? 'smeny_notice--success' : 'smeny_notice--error' ?>"><?= h((string)($smFlash['text'] ?? '')) ?></p>
    <?php endif; ?>

    <?php if ($smPerson === null): ?>
        <p class="smeny_notice smeny_notice--error">Přihlášený účet není propojený s aktivní osobou v HR. Požadavky proto nelze zadat.</p>
    <?php else: ?>
        <div class="smeny_requests_meta">
            <span><strong><?= h((string)($smPerson['jmeno'] ?: 'Pracovník')) ?></strong></span>
            <span>Hlavní pobočka: <strong><?= h((string)($smPerson['pobocka'] ?: 'není nastavena')) ?></strong></span>
            <span>Pozice: <strong><?= h((string)($smPerson['pozice'] ?: 'není nastavena')) ?></strong></span>
            <span>Termín: <strong>středa <?= h($smWeek['deadline']->format('j. n. Y')) ?> ve 20:00</strong></span>
            <?php if ($smCopiedWeek !== null): ?>
                <span class="smeny_request_state smeny_request_state--changed" data-smeny-save-state>Požadavky byly převzaty z týdne <?= h($smCopiedWeek['source_label']) ?> a zatím nejsou uložené.</span>
            <?php elseif (!empty($smData['saved'])): ?>
                <span class="smeny_request_state smeny_request_state--saved" data-smeny-save-state>Uložil <strong><?= h((string)($smPerson['jmeno'] ?: 'Pracovník')) ?></strong> dne <strong><?= h($smSavedAtLabel) ?></strong>.</span>
            <?php else: ?>
                <span class="smeny_request_state" data-smeny-save-state>Požadavky pro tento týden zatím nejsou uložené.</span>
            <?php endif; ?>
        </div>

        <?php if (empty($smWeek['open'])): ?>
            <p class="smeny_notice smeny_notice--error">Termín pro tento týden už skončil. Uložené požadavky jsou pouze k nahlédnutí.</p>
        <?php endif; ?>

        <form method="post" action="<?= h(cb_root_url('index.php?m=smeny&page=pozadavky&week=' . $smWeekIndex)) ?>" class="smeny_requests_form" data-smeny-requests-form data-smeny-saved="<?= !empty($smData['saved']) ? '1' : '0' ?>">
            <input type="hidden" name="action" value="smeny_pozadavky_ulozit">
            <input type="hidden" name="week" value="<?= $smWeekIndex ?>">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">

            <?php if ((int)$smPerson['je_hpp'] === 1): ?>
                <div class="smeny_requests_help">
                    <strong>HPP – volba jednoho dne volna</strong>
                    <span>Vyberte nejvýše jeden celý den. Den, který už zvolil jiný HPP pracovník na stejné pobočce a pozici, nelze vybrat.</span>
                </div>
                <?php if (!$smHasHppSetup): ?>
                    <p class="smeny_notice smeny_notice--error">V HR chybí hlavní pobočka nebo hlavní pozice. Bez nich nelze den volna uložit.</p>
                <?php endif; ?>
                <div class="smeny_hpp_days">
                    <label class="smeny_hpp_day smeny_hpp_day--none">
                        <input type="radio" name="datum_volna" value=""<?= $smData['own_day'] === '' ? ' checked' : '' ?><?= !$smCanSave ? ' disabled' : '' ?>>
                        <span>Bez požadavku na volno</span>
                    </label>
                    <?php foreach ($smWeek['days'] as $smDay): ?>
                        <?php $smOccupied = !empty($smData['occupied'][$smDay['date']]); ?>
                        <label class="smeny_hpp_day<?= $smOccupied ? ' smeny_hpp_day--occupied' : '' ?>">
                            <input type="radio" name="datum_volna" value="<?= h($smDay['date']) ?>"<?= $smData['own_day'] === $smDay['date'] ? ' checked' : '' ?><?= (!$smCanSave || $smOccupied || !$smHasHppSetup) ? ' disabled' : '' ?>>
                            <span><strong><?= h($smDay['name']) ?></strong><small><?= h($smDay['date_label']) ?></small></span>
                            <?php if ($smOccupied): ?><em>Obsazeno</em><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="smeny_requests_help">
                    <strong>Nastavte pouze dny, kdy chcete pracovat</strong>
                </div>
                <?php if (!$smHasMainBranch): ?>
                    <p class="smeny_notice smeny_notice--error">V HR chybí hlavní pobočka. Bez ní nelze požadavky uložit ani určit výchozí konec pracovní doby.</p>
                <?php endif; ?>
                <div class="smeny_request_days">
                    <?php foreach ($smWeek['days'] as $smDayIndex => $smDay): ?>
                        <?php
                        $smBlock = $smData['blocks'][$smDay['date']] ?? null;
                        $smOpeningIndex = cb_smeny_pozadavky_cas_na_index('10:00');
                        $smRangeStep = 2;
                        $smMinimumDuration = 12;
                        $smClosing = trim((string)($smPerson[$smDay['closing_key']] ?? ''));
                        $smClosingLabel = $smClosing !== '' ? substr($smClosing, 0, 5) : 'není nastaven';
                        $smClosingIndex = $smClosing !== '' ? cb_smeny_pozadavky_cas_na_index($smClosing, true) : $smOpeningIndex + 1;
                        $smDayAvailable = $smCanSave && ($smClosingIndex - $smOpeningIndex) >= $smMinimumDuration;
                        $smStartIndex = $smBlock ? cb_smeny_pozadavky_cas_na_index($smBlock['od']) : $smOpeningIndex;
                        $smEndIndex = $smBlock ? cb_smeny_pozadavky_cas_na_index($smBlock['do'], true) : $smClosingIndex;
                        if ($smBlock) {
                            $smStartIndex = $smOpeningIndex + (int)(floor(($smStartIndex - $smOpeningIndex) / $smRangeStep) * $smRangeStep);
                            if ($smEndIndex !== $smClosingIndex) {
                                $smEndIndex = $smOpeningIndex + (int)(ceil(($smEndIndex - $smOpeningIndex) / $smRangeStep) * $smRangeStep);
                            }
                        }
                        $smStartIndex = max($smOpeningIndex, min($smStartIndex, $smClosingIndex - $smMinimumDuration));
                        $smEndIndex = max($smStartIndex + $smMinimumDuration, min($smEndIndex, $smClosingIndex));
                        if ($smEndIndex > $smClosingIndex) {
                            $smEndIndex = $smClosingIndex;
                            $smStartIndex = $smOpeningIndex + (int)(floor(($smEndIndex - $smMinimumDuration - $smOpeningIndex) / $smRangeStep) * $smRangeStep);
                            $smStartIndex = max($smOpeningIndex, $smStartIndex);
                        }
                        $smStartTime = cb_smeny_pozadavky_index_na_cas($smStartIndex);
                        $smEndTime = cb_smeny_pozadavky_index_na_cas($smEndIndex);
                        $smRangeLength = max(1, $smClosingIndex - $smOpeningIndex);
                        $smStartPercent = (($smStartIndex - $smOpeningIndex) / $smRangeLength) * 100;
                        $smEndPercent = (($smEndIndex - $smOpeningIndex) / $smRangeLength) * 100;
                        ?>
                        <article class="smeny_request_day<?= $smBlock ? ' smeny_request_day--active' : '' ?>" data-smeny-request-day>
                            <div class="smeny_request_day_name">
                                <span class="smeny_request_day_text"><strong><?= h($smDay['name']) ?></strong><small><?= h($smDay['date_label']) ?></small></span>
                                <?php if ($smDayIndex < 6): ?>
                                    <button type="button" class="smeny_request_copy_next" data-smeny-copy-next aria-label="Zkopírovat čas z <?= h($smDay['name']) ?> do následujícího dne"<?= (!$smBlock || !$smDayAvailable) ? ' disabled' : '' ?>>↓</button>
                                <?php endif; ?>
                            </div>
                            <div class="smeny_request_range">
                                <div class="smeny_request_range_labels">
                                    <span>10:00</span>
                                    <output data-smeny-selection><?= $smBlock ? h($smStartTime . '–' . $smEndTime) : 'Nezadáno' ?></output>
                                    <span><?= h($smClosingLabel) ?></span>
                                </div>
                                <div class="smeny_request_range_track" data-smeny-range-track style="--smeny-range-start:<?= h(number_format($smStartPercent, 3, '.', '')) ?>%;--smeny-range-end:<?= h(number_format($smEndPercent, 3, '.', '')) ?>%">
                                    <input type="range" min="<?= $smOpeningIndex ?>" max="<?= $smClosingIndex ?>" step="2" value="<?= $smStartIndex ?>" aria-label="Začátek požadavku" data-smeny-start<?= !$smDayAvailable ? ' disabled' : '' ?>>
                                    <input type="range" min="<?= $smOpeningIndex ?>" max="<?= $smClosingIndex ?>" step="2" value="<?= $smEndIndex ?>" aria-label="Konec požadavku" data-smeny-end<?= !$smDayAvailable ? ' disabled' : '' ?>>
                                </div>
                                <input type="hidden" name="blocks[<?= h($smDay['date']) ?>][od]" value="<?= $smBlock ? h($smStartTime) : '' ?>" data-smeny-start-value>
                                <input type="hidden" name="blocks[<?= h($smDay['date']) ?>][do]" value="<?= $smBlock ? h($smEndTime) : '' ?>" data-smeny-end-value>
                            </div>
                            <button type="button" class="smeny_request_clear" data-smeny-clear<?= (!$smBlock || !$smDayAvailable) ? ' disabled' : '' ?>>Vymazat den</button>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($smCanSave || $smCanCopyWeek): ?>
                <div class="smeny_requests_actions" data-smeny-actions<?= (!$smCanCopyWeek && $smCopiedWeek === null) ? ' hidden' : '' ?>>
                    <?php if ($smCanCopyWeek): ?>
                        <a class="smeny_requests_copy_week" data-smeny-copy-week href="<?= h(cb_root_url('index.php?m=smeny&page=pozadavky&week=' . ($smWeekIndex + 1) . '&copy_from=' . rawurlencode((string)$smWeek['start_day']))) ?>">Použít pro další týden</a>
                    <?php endif; ?>
                    <?php if ($smCanSave): ?>
                        <button type="submit" class="smeny_requests_save" data-smeny-save<?= $smCopiedWeek === null ? ' hidden' : '' ?>><?= !empty($smData['saved']) ? 'Uložit upravené požadavky' : 'Uložit celý týden' ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</section>
