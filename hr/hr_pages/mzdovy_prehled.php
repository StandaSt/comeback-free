<?php
declare(strict_types=1);

/*
 * Vykresleni mzdoveho prehledu. Filtry jsou zamerne v horni liste modulu,
 * aby nenarusovaly dvouradkovou skupinovou hlavicku tabulky.
 */
$mzdovyColumns = [
    ['key' => 'id', 'label' => 'ID'], ['key' => 'jmeno', 'label' => 'Jméno'],
    ['key' => 'prijmeni', 'label' => 'Příjmení'], ['key' => 'pobocka', 'label' => 'Pobočka'],
    ['key' => 'pozice', 'label' => 'Pozice'], ['key' => 'uvazek', 'label' => 'Typ úvazku'],
    ['key' => 'mzda', 'label' => 'Mzda', 'numeric' => true],
    ['key' => 'prumer', 'label' => 'Průměrný výdělek za h.', 'numeric' => true],
    ['key' => 'osatne', 'label' => 'Ošatné', 'numeric' => true],
    ['key' => 'stravenkovy_pausal', 'label' => 'Stravenkový paušál', 'numeric' => true],
    ['key' => 'celkem', 'label' => 'Celkem', 'numeric' => true],
    ['key' => 'nocni', 'label' => 'Noční', 'numeric' => true],
    ['key' => 'svatek', 'label' => 'Svátek', 'numeric' => true],
    ['key' => 'svatek_noc', 'label' => 'Svátek noc', 'numeric' => true],
    ['key' => 'vikend', 'label' => 'Víkend', 'numeric' => true],
    ['key' => 'dovolena_pocatecni', 'label' => 'Poč. stav', 'numeric' => true],
    ['key' => 'dovolena_cerpano', 'label' => 'Čerpáno', 'numeric' => true],
    ['key' => 'dovolena_zustatek', 'label' => 'Zůstatek', 'numeric' => true],
    ['key' => 'noc_kc', 'label' => 'Noc Kč', 'numeric' => true],
    ['key' => 'svatek_kc', 'label' => 'Svátek Kč', 'numeric' => true],
    ['key' => 'svatek_noc_kc', 'label' => 'Svátek noc Kč', 'numeric' => true],
    ['key' => 'vikend_kc', 'label' => 'Víkend Kč', 'numeric' => true],
    ['key' => 'bonus_isk_procento', 'label' => 'Bonus ISK %', 'numeric' => true],
    ['key' => 'bonus_isk_kc', 'label' => 'Bonus ISK Kč', 'numeric' => true],
    ['key' => 'bonus_trzba', 'label' => 'Bonus tržba', 'numeric' => true],
    ['key' => 'prescas_h', 'label' => 'Přesčas h', 'numeric' => true],
    ['key' => 'prescas_kc', 'label' => 'Přesčas Kč', 'numeric' => true],
    ['key' => 'hruba_mzda', 'label' => 'Hrubá mzda', 'numeric' => true],
    ['key' => 'cista_mzda', 'label' => 'Čistá mzda', 'numeric' => true],
    ['key' => 'zsp', 'label' => 'ZSP', 'numeric' => true],
];
$mzdovyCurrentParams = [
    'm' => 'hr', 'page' => 'mzdovy_prehled',
    'mzd_mesic' => sprintf('%02d', (int)$mzdovyPrehled['period_month']), 'mzd_rok' => (int)$mzdovyPrehled['period_year'],
    'mzd_sort' => (string)$mzdovyPrehled['sort'], 'mzd_dir' => (string)$mzdovyPrehled['dir'],
    'mzd_per' => (int)$mzdovyPrehled['per_page'],
];
if ($mzdovyPrehled['active_filters'] !== []) {
    $mzdovyCurrentParams['mzd_f'] = $mzdovyPrehled['active_filters'];
}
$mzdovyUrl = static fn(array $changes = []): string => cb_root_url('index.php?' . http_build_query(array_merge($mzdovyCurrentParams, $changes)));
?>
<section class="hr_panel hr_mzdovy_prehled">
    <form id="hr-mzdovy-filter-form" class="hr_mzdovy_filter_form" method="get" action="<?= h(cb_root_url('index.php')) ?>" autocomplete="off" data-cb-filter-default-per="100" data-cb-filter-default-sort="prijmeni" data-cb-filter-default-dir="asc">
        <input type="hidden" name="m" value="hr">
        <input type="hidden" name="page" value="mzdovy_prehled">
        <input type="hidden" name="mzd_sort" value="<?= h((string)$mzdovyPrehled['sort']) ?>">
        <input type="hidden" name="mzd_dir" value="<?= h((string)$mzdovyPrehled['dir']) ?>">
        <input type="hidden" name="mzd_p" value="1">
    <?php if ($mzdovyError !== ''): ?>
        <p class="hr_notice hr_error"><?= h($mzdovyError) ?></p>
    <?php else: ?>
        <div class="hr_table_wrap table-wrap">
            <table class="hr_table hr_mzdovy_table">
                <thead>
                    <tr class="hr_mzdovy_group_row"><th class="hr_table_cell" colspan="8">Základní informace</th><th class="hr_table_cell" colspan="2">Příplatky SP a ošatné</th><th class="hr_table_cell" colspan="5">Počet odpracovaných hodin</th><th class="hr_table_cell" colspan="3">Dovolená</th><th class="hr_table_cell" colspan="4">Příplatky Kč</th><th class="hr_table_cell" colspan="3">Bonusová složka</th><th class="hr_table_cell" colspan="2">Přesčasy</th><th class="hr_table_cell" colspan="2">Celková mzda</th><th class="hr_table_cell">Odvody</th></tr>
                    <tr>
                        <?php foreach ($mzdovyColumns as $column): ?>
                            <?php
                            $columnKey = (string)$column['key'];
                            $isCurrentSort = $mzdovyPrehled['sort'] === $columnKey;
                            $nextDir = $isCurrentSort && $mzdovyPrehled['dir'] === 'asc' ? 'desc' : 'asc';
                            $arrow = $isCurrentSort ? ($mzdovyPrehled['dir'] === 'asc' ? '↑' : '↓') : '↕';
                            ?>
                            <th class="hr_table_cell hr_table_head<?= !empty($column['numeric']) ? ' hr_mzdovy_num' : '' ?>"><a class="hr_mzdovy_sort_link" href="<?= h($mzdovyUrl(['mzd_sort' => $columnKey, 'mzd_dir' => $nextDir, 'mzd_p' => 1])) ?>"><?= h((string)$column['label']) ?> <span aria-hidden="true"><?= h($arrow) ?></span></a></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($mzdovyPrehled['rows'] === []): ?>
                        <tr><td class="hr_table_cell hr_employee_list_empty" colspan="30">Žádní zaměstnanci neodpovídají zvoleným filtrům.</td></tr>
                    <?php else: ?>
                        <?php foreach ($mzdovyPrehled['rows'] as $row): ?>
                            <?php
                            $mzdovyEmployeeUrl = cb_root_url('index.php?' . http_build_query(array_merge(
                                $mzdovyCurrentParams,
                                ['page' => 'zamestnanec', 'sekce' => 'prehled', 'from' => 'mzdovy_prehled', 'mzd_p' => (int)$mzdovyPrehled['page_num'], 'id' => (int)$row['id_person']]
                            )));
                            ?>
                            <tr><td class="hr_table_cell"><?= h((string)(int)$row['id_person']) ?></td><td class="hr_table_cell"><?= h((string)($row['jmeno'] ?? '')) ?></td><td class="hr_table_cell"><a class="hr_table_link" data-cb-filter-ignore="1" href="<?= h($mzdovyEmployeeUrl) ?>"><?= h((string)($row['prijmeni'] ?? '')) ?></a></td><td class="hr_table_cell"><?= h((string)($row['pobocka'] ?? '')) ?></td><td class="hr_table_cell"><?= h((string)($row['zarazeni'] ?? '')) ?></td><td class="hr_table_cell"><?= h((string)($row['uvazek_text'] ?? '')) ?></td><td class="hr_table_cell hr_mzdovy_num"><?= $row['mzda_castka'] === null ? '' : h(cb_format('p', (float)$row['mzda_castka'])) ?></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"><?= h(cb_format('n', (float)$row['odpracovano'])) ?></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td><td class="hr_table_cell hr_mzdovy_num"></td></tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot><tr class="hr_mzdovy_total_row"><td class="hr_table_cell" colspan="10">Celkem</td><td class="hr_table_cell hr_mzdovy_num"><?= h(cb_format('n', (float)$mzdovyPrehled['total_hours'])) ?></td><td class="hr_table_cell" colspan="19"></td></tr></tfoot>
            </table>
        </div>
        <div class="cb_table_footer hr_mzdovy_footer list-bottom">
            <div class="cb_table_footer_summary">Zobrazuji řádky <?= h((string)$mzdovyPrehled['first_row']) ?>–<?= h((string)$mzdovyPrehled['last_row']) ?> z celkem <strong><?= h((string)$mzdovyPrehled['total_rows']) ?></strong></div>
            <div class="pagination-icon">
                <?php $isFirstPage = $mzdovyPrehled['page_num'] <= 1; $isLastPage = $mzdovyPrehled['page_num'] >= $mzdovyPrehled['total_pages']; ?>
                <a class="icon-btn<?= $isFirstPage ? ' disabled' : '' ?>" href="<?= $isFirstPage ? '#' : h($mzdovyUrl(['mzd_p' => 1])) ?>">«</a><a class="icon-btn<?= $isFirstPage ? ' disabled' : '' ?>" href="<?= $isFirstPage ? '#' : h($mzdovyUrl(['mzd_p' => $mzdovyPrehled['page_num'] - 1])) ?>">‹</a>
                <span class="page-current"><?= h((string)$mzdovyPrehled['page_num']) ?> / <?= h((string)$mzdovyPrehled['total_pages']) ?></span>
                <a class="icon-btn<?= $isLastPage ? ' disabled' : '' ?>" href="<?= $isLastPage ? '#' : h($mzdovyUrl(['mzd_p' => $mzdovyPrehled['page_num'] + 1])) ?>">›</a><a class="icon-btn<?= $isLastPage ? ' disabled' : '' ?>" href="<?= $isLastPage ? '#' : h($mzdovyUrl(['mzd_p' => $mzdovyPrehled['total_pages']])) ?>">»</a>
            </div>
            <label class="cb_table_footer_size" for="mzd_per">Zobrazovat <select id="mzd_per" class="filter-input" name="mzd_per"><?php foreach ($mzdovyPrehled['per_options'] as $option): ?><option value="<?= h((string)$option) ?>"<?= $mzdovyPrehled['per_page'] === $option ? ' selected' : '' ?>><?= h((string)$option) ?> řádků</option><?php endforeach; ?></select></label>
        </div>
    <?php endif; ?>
    </form>
</section>
