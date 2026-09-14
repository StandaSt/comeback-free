<?php
declare(strict_types=1);

/*
 * Jednotná obsluha filtrů je provoz/js/filtry.js.
 * Viditelné datumy a částky formátuje cb_format(); do data-* atributů a URL
 * zůstávají původní technické hodnoty.
 */

require_once __DIR__ . '/../db/db_objednavky_prehled.php';

if (!function_exists('cb_provoz_objednavky_url')) {
    function cb_provoz_objednavky_url(array $params): string
    {
        $base = [
            'm' => 'provoz',
            'page' => 'objednavky',
        ];

        return cb_root_url('index.php?' . http_build_query(array_merge($base, $params)));
    }
}

if (!function_exists('cb_provoz_objednavky_order_link')) {
    function cb_provoz_objednavky_order_link(string $key, string $label, string $sort, string $dir, array $filters, int $perPage): string
    {
        $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        $arrow = '↕';
        if ($sort === $key) {
            $arrow = $dir === 'asc' ? '↑' : '↓';
        }

        $params = [
            'obj_sort' => $key,
            'obj_dir' => $nextDir,
            'obj_per' => $perPage,
        ];
        if ($filters !== []) {
            $params['obj_f'] = $filters;
        }

        return '<a class="provoz_objednavky_sort_link" href="' . h(cb_provoz_objednavky_url($params)) . '">' . h($label) . ' <span>' . h($arrow) . '</span></a>';
    }
}

if (!function_exists('cb_provoz_objednavky_stav_label')) {
    /**
     * Vrati cesky popisek stavu; interni kod zustava beze zmeny pro filtr i DB.
     */
    function cb_provoz_objednavky_stav_label(mixed $value): string
    {
        $status = trim((string)$value);
        $labels = [
            '__storno__' => 'Storno',
            'new' => 'Nová objednávka',
            'accepted' => 'Přijato',
            'not_accepted' => 'Nepřijato',
            'rejected' => 'Odmítnuto',
            'cancel_accepted' => 'Storno přijato',
            'canceled' => 'Zrušeno',
            'ready_pickup' => 'Připraveno',
            'dispatched' => 'Předáno kurýrovi',
            'arrived_customer' => 'Kurýr u zákazníka',
            'delivered' => 'Doručeno',
            'expired' => 'Vypršelo',
        ];

        return $labels[$status] ?? $status;
    }
}

if (!function_exists('cb_provoz_objednavky_is_cancelled')) {
    function cb_provoz_objednavky_is_cancelled(mixed $value): bool
    {
        return in_array(trim((string)$value), cb_db_objednavky_prehled_cancel_statuses(), true);
    }
}

$data = cb_db_objednavky_prehled_nacti();
$perOptions = $data['per_options'];
$perPage = (int)$data['per_page'];
$pageNum = (int)$data['page_num'];
$sort = (string)$data['sort'];
$dir = (string)$data['dir'];
$filters = $data['filters'];
$filterOptions = $data['filter_options'];
$activeFilters = $data['active_filters'];
$rows = $data['rows'];
$totalRows = (int)$data['total_rows'];
$totalPages = (int)$data['total_pages'];
$firstRow = (int)$data['first_row'];
$lastRow = (int)$data['last_row'];
?>
<div class="provoz_objednavky_page" data-objednavky-prehled="1">
    <form method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="provoz">
        <input type="hidden" name="page" value="objednavky">
        <input type="hidden" name="obj_sort" value="<?= h($sort) ?>">
        <input type="hidden" name="obj_dir" value="<?= h($dir) ?>">
        <input type="hidden" name="obj_p" value="1">

        <div class="provoz_objednavky_list">
        <div class="provoz_objednavky_table_wrap table-wrap">
            <table class="provoz_objednavky_table">
                <thead>
                    <tr class="provoz_objednavky_filter_row">
                        <th><input class="provoz_objednavky_filter filter-input" type="search" name="obj_f[cislo]" value="<?= h($filters['cislo']) ?>"></th>
                        <th><input class="provoz_objednavky_filter provoz_objednavky_filter_datetime filter-input" type="search" name="obj_f[vytvoreno]" value="<?= h($filters['vytvoreno']) ?>"></th>
                        <th><select class="provoz_objednavky_filter filter-input" name="obj_f[pobocka]" aria-label="Filtrovat pobočku"><option value="">Vše</option><?php foreach ($filterOptions['pobocka'] as $option): ?><option value="<?= h($option) ?>"<?= $option === $filters['pobocka'] ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th><select class="provoz_objednavky_filter filter-input" name="obj_f[stav]" aria-label="Filtrovat stav"><option value="">Vše</option><?php foreach ($filterOptions['stav'] as $option): ?><option value="<?= h($option) ?>"<?= $option === $filters['stav'] ? ' selected' : '' ?>><?= h(cb_provoz_objednavky_stav_label($option)) ?></option><?php endforeach; ?></select></th>
                        <th><select class="provoz_objednavky_filter filter-input" name="obj_f[typ]" aria-label="Filtrovat typ"><option value="">Vše</option><?php foreach ($filterOptions['typ'] as $option): ?><option value="<?= h($option) ?>"<?= $option === $filters['typ'] ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th><select class="provoz_objednavky_filter filter-input" name="obj_f[platba]" aria-label="Filtrovat platbu"><option value="">Vše</option><?php foreach ($filterOptions['platba'] as $option): ?><option value="<?= h($option) ?>"<?= $option === $filters['platba'] ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th><input class="provoz_objednavky_filter filter-input" type="search" name="obj_f[zakaznik]" value="<?= h($filters['zakaznik']) ?>"></th>
                        <th><input class="provoz_objednavky_filter filter-input" type="search" name="obj_f[cena]" value="<?= h($filters['cena']) ?>"></th>
                    </tr>
                    <tr>
                        <th><?= cb_provoz_objednavky_order_link('cislo', 'Objednávka', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('vytvoreno', 'Vytvořeno', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('pobocka', 'Pobočka', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('stav', 'Stav', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('typ', 'Typ', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('platba', 'Platba', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('zakaznik', 'Zákazník', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th><?= cb_provoz_objednavky_order_link('cena', 'Cena', $sort, $dir, $activeFilters, $perPage) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td class="provoz_objednavky_empty" colspan="8">Žádné objednávky pro zvolené období.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $objednavkyIdObj = (int)($row['id_obj'] ?? 0);
                            $objednavkyCislo = cb_objednavka_cislo($row);
                            $objednavkySleva = abs((float)($row['sleva'] ?? 0));
                            $objednavkyDetailId = 'provoz_objednavky_detail_' . $objednavkyIdObj;
                            ?>
                            <tr<?= cb_provoz_objednavky_is_cancelled($row['stav_nazev'] ?? '') ? ' class="provoz_objednavky_row--cancelled"' : '' ?>>
                                <td<?= $objednavkyCislo['tooltip'] !== '' ? ' title="' . h($objednavkyCislo['tooltip']) . '"' : '' ?>>
                                    <button type="button" class="provoz_objednavky_toggle" data-provoz-objednavky-toggle aria-expanded="false" aria-controls="<?= h($objednavkyDetailId) ?>"><?= h($objednavkyCislo['zkracene']) ?></button>
                                </td>
                                <td class="provoz_objednavky_nowrap"><?= h(cb_format('dcs', $row['vytvoreno'] ?? '')) ?></td>
                                <td><?= h((string)($row['pobocka_nazev'] ?? '')) ?></td>
                                <td><?= h(cb_provoz_objednavky_stav_label($row['stav_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['typ_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['platba_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['zakaznik_jmeno'] ?? '')) ?></td>
                                <td class="provoz_objednavky_num"><?= h(cb_format('p', $row['cena_celk'] ?? 0)) ?></td>
                            </tr>
                            <tr id="<?= h($objednavkyDetailId) ?>" data-provoz-objednavky-detail hidden>
                                <td colspan="8" class="zr_storno_detail_cell">
                                    <?php if ((array)($row['polozky'] ?? []) === []): ?>
                                        <span class="txt_seda">Položky objednávky nejsou dostupné.</span>
                                    <?php else: ?>
                                        <div class="zr_storno_items_title">Položky v objednávce: <?= h($objednavkyCislo['cele']) ?></div>
                                        <table class="zr_storno_items">
                                            <tbody>
                                            <?php foreach ((array)$row['polozky'] as $objednavkyItem): ?>
                                                <tr>
                                                    <td class="zr_storno_item_qty"><?= h((string)($objednavkyItem['mnozstvi'] ?? 0)) ?>×</td>
                                                    <td><?= h((string)($objednavkyItem['nazev'] ?? 'Položka')) ?><?= trim((string)($objednavkyItem['poznamka'] ?? '')) !== '' ? ' — ' . h((string)$objednavkyItem['poznamka']) : '' ?></td>
                                                    <td class="txt_r"><?= h(cb_format('p', $objednavkyItem['cena_celk'] ?? 0)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if ($objednavkySleva > 0): ?>
                                                <tr>
                                                    <td></td>
                                                    <td>Sleva</td>
                                                    <td class="txt_r zr_storno_item_discount_value">−<?= h(cb_format('p', $objednavkySleva)) ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="provoz_objednavky_pager list-bottom">
        <span><?= h((string)$firstRow) ?>-<?= h((string)$lastRow) ?> / <?= h((string)$totalRows) ?></span>
        <div class="provoz_objednavky_pager_links">
            <?php
            $pagerParams = [
                'obj_sort' => $sort,
                'obj_dir' => $dir,
                'obj_per' => $perPage,
            ];
            if ($activeFilters !== []) {
                $pagerParams['obj_f'] = $activeFilters;
            }
            ?>
            <a class="provoz_objednavky_page_link <?= $pageNum <= 1 ? 'is-disabled' : '' ?>" href="<?= h(cb_provoz_objednavky_url(array_merge($pagerParams, ['obj_p' => 1]))) ?>">«</a>
            <a class="provoz_objednavky_page_link <?= $pageNum <= 1 ? 'is-disabled' : '' ?>" href="<?= h(cb_provoz_objednavky_url(array_merge($pagerParams, ['obj_p' => max(1, $pageNum - 1)]))) ?>">‹</a>
            <span class="provoz_objednavky_page_current"><?= h((string)$pageNum) ?> / <?= h((string)$totalPages) ?></span>
            <a class="provoz_objednavky_page_link <?= $pageNum >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h(cb_provoz_objednavky_url(array_merge($pagerParams, ['obj_p' => min($totalPages, $pageNum + 1)]))) ?>">›</a>
            <a class="provoz_objednavky_page_link <?= $pageNum >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h(cb_provoz_objednavky_url(array_merge($pagerParams, ['obj_p' => $totalPages]))) ?>">»</a>
        </div>
        <div class="provoz_objednavky_per">
            <label for="obj_per">Řádků</label>
            <select id="obj_per" name="obj_per" class="filter-input">
                <?php foreach ($perOptions as $option): ?>
                    <option value="<?= h((string)$option) ?>" <?= $option === $perPage ? 'selected' : '' ?>><?= h((string)$option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        </div>
        </div>
    </form>
</div>
