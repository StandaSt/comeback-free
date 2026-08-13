<?php
declare(strict_types=1);

$renderStart = microtime(true);

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

if (!function_exists('cb_provoz_objednavky_format_datetime')) {
    function cb_provoz_objednavky_format_datetime(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }

        try {
            $dt = new DateTimeImmutable($raw);
        } catch (Throwable $e) {
            return $raw;
        }

        return $dt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_provoz_objednavky_shorten')) {
    function cb_provoz_objednavky_shorten(mixed $value, int $maxLength = 14): string
    {
        $text = trim((string)$value);
        if ($text === '' || strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength) . '...';
    }
}

$data = cb_db_objednavky_prehled_nacti();
$periodOd = $data['period_od'];
$periodDo = $data['period_do'];
$perOptions = $data['per_options'];
$perPage = (int)$data['per_page'];
$pageNum = (int)$data['page_num'];
$sort = (string)$data['sort'];
$dir = (string)$data['dir'];
$filters = $data['filters'];
$activeFilters = $data['active_filters'];
$rows = $data['rows'];
$totalRows = (int)$data['total_rows'];
$totalPages = (int)$data['total_pages'];
$firstRow = (int)$data['first_row'];
$lastRow = (int)$data['last_row'];
$serverRenderMs = (int)round((microtime(true) - $renderStart) * 1000);
?>
<div class="provoz_objednavky_page" data-objednavky-prehled="1">
    <div class="provoz_objednavky_head">
        <div>
            <p class="provoz_objednavky_meta">
                <?= h($periodOd->format('d.m.Y H:i')) ?> - <?= h($periodDo->format('d.m.Y H:i')) ?>,
                <?= h((string)$totalRows) ?> záznamů,
                server <?= h((string)$serverRenderMs) ?> ms,
                stránka <span data-objednavky-client-time>...</span>
            </p>
        </div>
    </div>

    <form data-objednavky-filter-form="1" method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="provoz">
        <input type="hidden" name="page" value="objednavky">
        <input type="hidden" name="obj_sort" value="<?= h($sort) ?>">
        <input type="hidden" name="obj_dir" value="<?= h($dir) ?>">
        <input type="hidden" name="obj_per" value="<?= h((string)$perPage) ?>">
        <input type="hidden" name="obj_p" value="1">

        <div class="provoz_objednavky_table_wrap">
            <table class="provoz_objednavky_table">
                <thead>
                    <tr class="provoz_objednavky_filter_row">
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[cislo]" value="<?= h($filters['cislo']) ?>"></th>
                        <th><input class="provoz_objednavky_filter provoz_objednavky_filter_datetime" type="search" name="obj_f[vytvoreno]" value="<?= h($filters['vytvoreno']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[pobocka]" value="<?= h($filters['pobocka']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[stav]" value="<?= h($filters['stav']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[typ]" value="<?= h($filters['typ']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[platba]" value="<?= h($filters['platba']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[zakaznik]" value="<?= h($filters['zakaznik']) ?>"></th>
                        <th><input class="provoz_objednavky_filter" type="search" name="obj_f[cena]" value="<?= h($filters['cena']) ?>"></th>
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
                            <tr>
                                <td title="<?= h((string)($row['restia_order_number'] ?? '')) ?>"><?= h(cb_provoz_objednavky_shorten($row['restia_order_number'] ?? '')) ?></td>
                                <td class="provoz_objednavky_nowrap"><?= h(cb_provoz_objednavky_format_datetime($row['vytvoreno'] ?? '')) ?></td>
                                <td><?= h((string)($row['pobocka_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['stav_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['typ_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['platba_nazev'] ?? '')) ?></td>
                                <td><?= h((string)($row['zakaznik_jmeno'] ?? '')) ?></td>
                                <td class="provoz_objednavky_num"><?= h(number_format((float)($row['cena_celk'] ?? 0), 0, ',', ' ')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div class="provoz_objednavky_pager">
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
        <form class="provoz_objednavky_per" data-objednavky-per-form="1" method="get" action="<?= h(cb_root_url('index.php')) ?>">
            <input type="hidden" name="m" value="provoz">
            <input type="hidden" name="page" value="objednavky">
            <input type="hidden" name="obj_sort" value="<?= h($sort) ?>">
            <input type="hidden" name="obj_dir" value="<?= h($dir) ?>">
            <?php foreach ($activeFilters as $key => $value): ?>
                <input type="hidden" name="obj_f[<?= h($key) ?>]" value="<?= h($value) ?>">
            <?php endforeach; ?>
            <label for="obj_per">Řádků</label>
            <select id="obj_per" name="obj_per" onchange="this.form.submit()">
                <?php foreach ($perOptions as $option): ?>
                    <option value="<?= h((string)$option) ?>" <?= $option === $perPage ? 'selected' : '' ?>><?= h((string)$option) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>
