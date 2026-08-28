<?php
declare(strict_types=1);

if (!function_exists('hr_employee_list_url')) {
    function hr_employee_list_url(array $params): string
    {
        $base = ['m' => 'hr', 'page' => 'zamestnanci'];
        return cb_root_url('index.php?' . http_build_query(array_merge($base, $params)));
    }
}

if (!function_exists('hr_employee_list_order_link')) {
    function hr_employee_list_order_link(string $key, string $label, string $sort, string $dir, array $filters, int $perPage): string
    {
        $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        $arrow = $sort === $key ? ($dir === 'asc' ? '↑' : '↓') : '↕';
        $params = ['hr_emp_sort' => $key, 'hr_emp_dir' => $nextDir, 'hr_emp_per' => $perPage];
        if ($filters !== []) {
            $params['hr_emp_f'] = $filters;
        }
        return '<a class="hr_employee_list_sort_link" href="' . h(hr_employee_list_url($params)) . '">' . h($label) . ' <span>' . h($arrow) . '</span></a>';
    }
}

$employeeList = hr_fetch_employee_list($db);
$employees = $employeeList['rows'];
$perOptions = $employeeList['per_options'];
$perPage = (int)$employeeList['per_page'];
$pageNum = (int)$employeeList['page_num'];
$sort = (string)$employeeList['sort'];
$dir = (string)$employeeList['dir'];
$filters = $employeeList['filters'];
$activeFilters = $employeeList['active_filters'];
$totalRows = (int)$employeeList['total_rows'];
$totalPages = (int)$employeeList['total_pages'];
$firstRow = (int)$employeeList['first_row'];
$lastRow = (int)$employeeList['last_row'];
$filterOptions = $employeeList['filter_options'];
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div>
            <h2 class="hr_panel_title">Seznam zaměstnanců</h2>
            <p class="hr_muted"><?= h((string)$totalRows) ?> záznamů</p>
        </div>
        <a class="hr_primary_button hr_panel_button_primary" href="<?= h(cb_root_url('index.php?m=hr&page=novy_zamestnanec')) ?>">+ Nový zaměstnanec</a>
    </div>

    <form class="hr_employee_list_filter_form" data-hr-employee-filter-form method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="hr">
        <input type="hidden" name="page" value="zamestnanci">
        <input type="hidden" name="hr_emp_sort" value="<?= h($sort) ?>">
        <input type="hidden" name="hr_emp_dir" value="<?= h($dir) ?>">
        <input type="hidden" name="hr_emp_per" value="<?= h((string)$perPage) ?>">
        <input type="hidden" name="hr_emp_p" value="1">
        <div class="hr_table_wrap">
            <table class="hr_table hr_employee_list_table">
                <thead>
                    <tr class="hr_employee_list_filter_row">
                        <th class="hr_table_cell" style="width: 6ch;"><input class="hr_employee_list_filter" style="width: 6ch; min-width: 6ch;" type="search" name="hr_emp_f[id]" value="<?= h($filters['id']) ?>" aria-label="Filtrovat ID"></th>
                        <th class="hr_table_cell"><input class="hr_employee_list_filter" type="search" name="hr_emp_f[zamestnanec]" value="<?= h($filters['zamestnanec']) ?>" aria-label="Filtrovat zaměstnance"></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[zarazeni]" aria-label="Filtrovat zařazení"><option value="">Vše</option><?php foreach ($filterOptions['zarazeni'] as $option): ?><option value="<?= h($option) ?>" <?= $option === $filters['zarazeni'] ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[pracoviste]" aria-label="Filtrovat pracoviště"><option value="">Vše</option><?php foreach ($filterOptions['pracoviste'] as $option): ?><option value="<?= h($option) ?>" <?= $option === $filters['pracoviste'] ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[vztah]" aria-label="Filtrovat typ vztahu"><option value="">Vše</option><?php foreach ($filterOptions['vztah'] as $option): ?><option value="<?= h($option) ?>" <?= $option === $filters['vztah'] ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></th>
                        <th class="hr_table_cell"><input class="hr_employee_list_filter hr_employee_list_filter_date" type="search" name="hr_emp_f[nastup]" value="<?= h($filters['nastup']) ?>" aria-label="Filtrovat datum nástupu"></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[stav]" aria-label="Filtrovat stav"><option value="aktivni" <?= $filters['stav'] === 'aktivni' ? 'selected' : '' ?>>Aktivní</option><option value="neaktivni" <?= $filters['stav'] === 'neaktivni' ? 'selected' : '' ?>>Neaktivní</option><option value="vse" <?= $filters['stav'] === 'vse' ? 'selected' : '' ?>>Vše</option></select></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[overen]" aria-label="Filtrovat ověření"><option value="overeny" <?= $filters['overen'] === 'overeny' ? 'selected' : '' ?>>Ověřený</option><option value="neovereny" <?= $filters['overen'] === 'neovereny' ? 'selected' : '' ?>>Neověřený</option><option value="vse" <?= $filters['overen'] === 'vse' ? 'selected' : '' ?>>Vše</option></select></th>
                        <th class="hr_table_cell"><select class="hr_employee_list_filter" name="hr_emp_f[kompletni]" aria-label="Filtrovat kompletnost"><option value="kompletni" <?= $filters['kompletni'] === 'kompletni' ? 'selected' : '' ?>>Kompletní</option><option value="nekompletni" <?= $filters['kompletni'] === 'nekompletni' ? 'selected' : '' ?>>Nekompletní</option><option value="vse" <?= $filters['kompletni'] === 'vse' ? 'selected' : '' ?>>Vše</option></select></th>
                    </tr>
                    <tr>
                        <th class="hr_table_cell hr_table_head" style="width: 6ch;"><?= hr_employee_list_order_link('id', 'ID', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('zamestnanec', 'Zaměstnanec', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('zarazeni', 'Zařazení', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('pracoviste', 'Pracoviště', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('vztah', 'Typ vztahu', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('nastup', 'Datum nástupu', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('stav', 'Stav', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('overen', 'Ověření', $sort, $dir, $activeFilters, $perPage) ?></th>
                        <th class="hr_table_cell hr_table_head"><?= hr_employee_list_order_link('kompletni', 'Kompletnost', $sort, $dir, $activeFilters, $perPage) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($employees === []): ?>
                        <tr><td class="hr_table_cell hr_employee_list_empty" colspan="9">Žádní zaměstnanci neodpovídají zvolenému filtru.</td></tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <?php
                            $workplaces = array_values(array_filter(
                                array_map('trim', explode(', ', (string)($employee['pracoviste'] ?? ''))),
                                static fn (string $name): bool => $name !== ''
                            ));
                            $mainWorkplace = (string)($employee['pracoviste_hlavni'] ?? '');
                            $visibleWorkplaces = array_slice($workplaces, 0, 3);
                            $hiddenWorkplaces = array_slice($workplaces, 3);
                            ?>
                            <tr>
                                <td class="hr_table_cell" style="width: 6ch;"><?= h((string)$employee['id_person']) ?></td>
                                <td class="hr_table_cell"><a class="hr_table_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$employee['id_person']))) ?>"><?= h($employee['cele_jmeno']) ?></a></td>
                                <td class="hr_table_cell"><?= h((string)($employee['zarazeni'] ?? '-')) ?></td>
                                <td class="hr_table_cell"<?= $hiddenWorkplaces !== [] ? ' title="' . h(implode(', ', $hiddenWorkplaces)) . '"' : '' ?>>
                                    <?php if ($visibleWorkplaces === []): ?>
                                        -
                                    <?php else: ?>
                                        <?php foreach ($visibleWorkplaces as $index => $workplace): ?><?= $index > 0 ? ', ' : '' ?><?php if ($workplace === $mainWorkplace): ?><strong><?= h($workplace) ?></strong><?php else: ?><?= h($workplace) ?><?php endif; ?><?php endforeach; ?>
                                        <?php if ($hiddenWorkplaces !== []): ?> <span>+<?= h((string)count($hiddenWorkplaces)) ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="hr_table_cell"><?= h((string)($employee['vztah_kod'] ?? '-')) ?></td>
                                <td class="hr_table_cell"><?= h(hr_format_date((string)($employee['datum_nastupu'] ?? ''))) ?></td>
                                <td class="hr_table_cell"><span class="hr_badge <?= h($employee['stav_badge']) ?>"><?= h($employee['stav_label']) ?></span></td>
                                <td class="hr_table_cell"><span class="hr_badge <?= (int)($employee['overen'] ?? 0) === 1 ? 'hr_success' : 'hr_neutral' ?>"><?= (int)($employee['overen'] ?? 0) === 1 ? 'Ověřený' : 'Neověřený' ?></span></td>
                                <td class="hr_table_cell"><span class="hr_badge <?= (int)($employee['kompletni'] ?? 0) === 1 ? 'hr_success' : 'hr_neutral' ?>"><?= (int)($employee['kompletni'] ?? 0) === 1 ? 'Kompletní' : 'Nekompletní' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <div class="hr_employee_list_pager">
        <span><?= h((string)$firstRow) ?>-<?= h((string)$lastRow) ?> / <?= h((string)$totalRows) ?></span>
        <div class="hr_employee_list_pager_links">
            <?php $pagerParams = ['hr_emp_sort' => $sort, 'hr_emp_dir' => $dir, 'hr_emp_per' => $perPage]; if ($activeFilters !== []) { $pagerParams['hr_emp_f'] = $activeFilters; } ?>
            <a class="hr_employee_list_page_link <?= $pageNum <= 1 ? 'is-disabled' : '' ?>" href="<?= h(hr_employee_list_url(array_merge($pagerParams, ['hr_emp_p' => 1]))) ?>">«</a>
            <a class="hr_employee_list_page_link <?= $pageNum <= 1 ? 'is-disabled' : '' ?>" href="<?= h(hr_employee_list_url(array_merge($pagerParams, ['hr_emp_p' => max(1, $pageNum - 1)]))) ?>">‹</a>
            <span class="hr_employee_list_page_current"><?= h((string)$pageNum) ?> / <?= h((string)$totalPages) ?></span>
            <a class="hr_employee_list_page_link <?= $pageNum >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h(hr_employee_list_url(array_merge($pagerParams, ['hr_emp_p' => min($totalPages, $pageNum + 1)]))) ?>">›</a>
            <a class="hr_employee_list_page_link <?= $pageNum >= $totalPages ? 'is-disabled' : '' ?>" href="<?= h(hr_employee_list_url(array_merge($pagerParams, ['hr_emp_p' => $totalPages]))) ?>">»</a>
        </div>
        <form class="hr_employee_list_per" data-hr-employee-per-form method="get" action="<?= h(cb_root_url('index.php')) ?>">
            <input type="hidden" name="m" value="hr">
            <input type="hidden" name="page" value="zamestnanci">
            <input type="hidden" name="hr_emp_sort" value="<?= h($sort) ?>">
            <input type="hidden" name="hr_emp_dir" value="<?= h($dir) ?>">
            <?php foreach ($activeFilters as $key => $value): ?><input type="hidden" name="hr_emp_f[<?= h($key) ?>]" value="<?= h($value) ?>"><?php endforeach; ?>
            <label for="hr_emp_per">Řádků</label>
            <select id="hr_emp_per" name="hr_emp_per" onchange="this.form.submit()"><?php foreach ($perOptions as $option): ?><option value="<?= h((string)$option) ?>" <?= $option === $perPage ? 'selected' : '' ?>><?= h((string)$option) ?></option><?php endforeach; ?></select>
        </form>
    </div>
</section>
