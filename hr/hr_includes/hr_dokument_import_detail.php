<?php
/* Ucel souboru: Spolecny detail podpisu, casti a upozorneni importovaneho dokumentu pro seznam i kartu osoby. */
declare(strict_types=1);

/** Vykresli pouze metadata importu; vsechen obsah escapuje a PDF otevira pres chraneny handler. */
function hr_document_import_detail(array $document, string $openUrl): void
{
    $meta = $document['import_meta'] ?? [];
    if ($meta === []) { return; }
    $signature = (string)($meta['stav_podpisu'] ?? '');
    if ($signature !== '') { echo '<p>'.h($signature).'</p>'; }
    $warnings = (array)($meta['upozorneni'] ?? []);
    if ($warnings !== []) {
        echo '<details><summary>Upozornění / nutné dořešit</summary><ul>';
        foreach ($warnings as $warning) { echo '<li>'.h((string)$warning).'</li>'; }
        echo '</ul></details>';
    }
    $parts = (array)($meta['casti'] ?? []);
    if ($parts !== []) {
        echo '<details><summary>Části dokumentu ('.count($parts).')</summary><ul>';
        foreach ($parts as $part) {
            $first = (int)$part['strana_od']; $last = (int)$part['strana_do'];
            echo '<li><a target="_blank" rel="noopener" href="'.h($openUrl.'#page='.$first).'">'.h((string)$part['typ']).'</a> — str. '.$first.($last !== $first ? '–'.$last : '');
            echo ': '.h((string)($part['stav_podpisu'] ?? 'Bez samostatného podpisového pole'));
            if (!empty($part['chybejici_role'])) { echo ' — nutné dořešit: chybí '.h(implode(', ', $part['chybejici_role'])); }
            echo '</li>';
        }
        echo '</ul></details>';
    }
    if (!empty($document['import_udaje'])) {
        echo '<details><summary>Údaje a poznámky z podkladu</summary><pre>'.h((string)$document['import_udaje']).'</pre></details>';
    }
}
