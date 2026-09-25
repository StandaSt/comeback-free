<?php
declare(strict_types=1);

/*
 * Připraví uložené bloky předchozího týdne k ručnímu uložení v následujícím týdnu.
 */

/**
 * @param array<int,array<string,mixed>> $weeks
 * @return array{blocks:array<string,array{od:string,do:string}>,source_label:string}|null
 */
function cb_smeny_pozadavky_kopie(mysqli $db, ?array $person, array $weeks, int $targetIndex): ?array
{
    if ($person === null || (int)$person['je_hpp'] === 1 || $targetIndex <= 0 || !isset($weeks[$targetIndex - 1], $weeks[$targetIndex])) {
        return null;
    }

    $sourceWeek = $weeks[$targetIndex - 1];
    $requestedSource = trim((string)($_GET['copy_from'] ?? ''));
    if ($requestedSource === '' || !hash_equals((string)$sourceWeek['start_day'], $requestedSource)) {
        return null;
    }

    $targetData = cb_smeny_pozadavky_nacist($db, $person, $weeks[$targetIndex]);
    if (!empty($targetData['saved'])) {
        return null;
    }

    $sourceData = cb_smeny_pozadavky_nacist($db, $person, $sourceWeek);
    if (empty($sourceData['saved']) || $sourceData['blocks'] === []) {
        return null;
    }

    $blocks = [];
    foreach ($sourceWeek['days'] as $dayIndex => $sourceDay) {
        $sourceBlock = $sourceData['blocks'][(string)$sourceDay['date']] ?? null;
        if ($sourceBlock === null || !isset($weeks[$targetIndex]['days'][$dayIndex])) {
            continue;
        }
        $targetDate = (string)$weeks[$targetIndex]['days'][$dayIndex]['date'];
        $blocks[$targetDate] = ['od' => (string)$sourceBlock['od'], 'do' => (string)$sourceBlock['do']];
    }

    return [
        'blocks' => $blocks,
        'source_label' => $sourceWeek['start']->format('j. n.') . '–' . $sourceWeek['end']->format('j. n. Y'),
    ];
}
