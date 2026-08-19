<?php
/*
 * Ucel souboru: Spolecne vykresluje pracovni plochu PP z datove definice stranky.
 * Ridi jen konstrukci PP, titulek, flash zpravu, rozlozeni a poradi bloku.
 * Nezna konkretni modul, jeho DB logiku ani obsah jednotlivych bloku.
 */
declare(strict_types=1);

/*
 * Bezpecne pripravi text pro HTML vystup renderu PP.
 */
function cb_pp_h(string $value): string
{
    if (function_exists('h')) {
        return h($value);
    }

    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/*
 * Vykresli jednu obecnou flash zpravu, pokud ji vola stranka predala.
 * Vzhled urcuje common CSS; modul dodava jen typ a text zpravy.
 */
function cb_render_pp_flash(?array $flash): void
{
    if (!is_array($flash)) {
        return;
    }

    $text = trim((string)($flash['text'] ?? ''));
    if ($text === '') {
        return;
    }

    $type = strtolower(trim((string)($flash['type'] ?? 'info')));
    if (!in_array($type, ['success', 'error', 'info', 'warning'], true)) {
        $type = 'info';
    }

    echo '<div class="pp_flash pp_flash--' . cb_pp_h($type) . '" role="status">' . cb_pp_h($text) . '</div>';
}

/*
 * Normalizuje obecne rozlozeni bloku stranky.
 * Stack ma vzdy jeden sloupec, grid muze mit dva az ctyri sloupce.
 */
function cb_pp_normalize_layout(mixed $layout): array
{
    if (is_string($layout)) {
        $layout = ['type' => $layout];
    }

    if (!is_array($layout)) {
        throw new RuntimeException('Neplatne rozlozeni stranky PP.');
    }

    $type = strtolower(trim((string)($layout['type'] ?? 'stack')));
    if (!in_array($type, ['stack', 'grid'], true)) {
        throw new RuntimeException('Neplatne rozlozeni stranky PP.');
    }

    $columns = $type === 'stack' ? 1 : (int)($layout['columns'] ?? 2);
    if ($columns < 1 || $columns > 4) {
        throw new RuntimeException('Neplatny pocet sloupcu PP.');
    }

    return [
        'type' => $type,
        'columns' => $columns,
    ];
}

/*
 * Vykresli jeden blok definovany modulem.
 * Blok predava pouze svuj klic a absolutni cestu k obsahu; data dostava v kontextu.
 */
function cb_render_pp_block(array $block, array $context, int $columns): void
{
    $key = trim((string)($block['key'] ?? ''));
    $file = trim((string)($block['file'] ?? ''));
    $span = (int)($block['span'] ?? 1);

    if ($key === '' || $file === '' || !is_file($file) || $span < 1 || $span > $columns) {
        throw new RuntimeException('Neplatna definice bloku PP.');
    }

    extract($context, EXTR_SKIP);

    echo '<div class="pp_block pp_block--span-' . (string)$span . '" data-pp-block="' . cb_pp_h($key) . '">';
    require $file;
    echo '</div>';
}

/*
 * Vykresli celou PP z definice stranky a dat pro jeji bloky.
 * Povinne udaje stranky jsou modul, klic, titulek, rozlozeni a seznam bloku.
 */
function cb_render_pp(array $page, array $context = []): void
{
    $module = trim((string)($page['module'] ?? ''));
    $pageKey = trim((string)($page['key'] ?? ''));
    $title = trim((string)($page['title'] ?? ''));
    $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : null;

    if ($module === '' || $pageKey === '' || $title === '' || !is_array($blocks)) {
        throw new RuntimeException('Neplatna definice stranky PP.');
    }

    $layout = cb_pp_normalize_layout($page['layout'] ?? 'stack');
    $flash = is_array($context['flash'] ?? null) ? $context['flash'] : null;

    echo '<section class="pp" data-module="' . cb_pp_h($module) . '" data-page="' . cb_pp_h($pageKey) . '">';
    echo '<header class="pp_header"><h1>' . cb_pp_h($title) . '</h1></header>';
    cb_render_pp_flash($flash);
    echo '<div class="pp_blocks pp_blocks--' . cb_pp_h($layout['type']) . ' pp_blocks--columns-' . (string)$layout['columns'] . '">';

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            throw new RuntimeException('Neplatna polozka seznamu bloku PP.');
        }
        cb_render_pp_block($block, $context, $layout['columns']);
    }

    echo '</div></section>';
}
