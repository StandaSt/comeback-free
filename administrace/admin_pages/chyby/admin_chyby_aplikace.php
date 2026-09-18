<?php
declare(strict_types=1);

/*
 * Pohled kategorie Aplikacni chyby.
 * Vykresli filtrovanou tabulku log_chyby a metadata z cis_chyby.
 */

require_once __DIR__ . '/../../admin_db/admin_log_chyby_db.php';

$nastaveni = cb_admin_log_chyby_filtry($_GET);
$data = cb_admin_log_chyby_nacti(db(), $nastaveni);
$moduly = cb_admin_log_chyby_moduly(db());
$filtry = $nastaveni['filtry'];
$notice = $_SESSION['cb_admin_log_chyby_notice'] ?? null;
unset($_SESSION['cb_admin_log_chyby_notice']);

/** Rozdeli datum a cas pro kompaktni zobrazeni ve dvou radcich. */
$formatDatumCas = static function (?string $kdy): array {
    if (!is_string($kdy) || $kdy === '') {
        return ['datum' => '—', 'cas' => ''];
    }
    $timestamp = strtotime($kdy);
    if ($timestamp === false) {
        return ['datum' => $kdy, 'cas' => ''];
    }
    return ['datum' => date('j. n. Y', $timestamp), 'cas' => date('H:i:s', $timestamp)];
};

/** Sestavi URL se zachovanim filtru a zvolenym razenim. */
$url = static function (array $zmena = []) use ($baseUrl, $nastaveni, $filtry): string {
    $params = [
        'cat' => 'aplikace',
        'err_f' => $filtry,
        'err_per' => $nastaveni['per_page'],
        'err_p' => $nastaveni['strana'],
        'err_sort' => $nastaveni['sort'],
        'err_dir' => $nastaveni['dir'],
    ];
    foreach ($zmena as $key => $value) {
        $params[$key] = $value;
    }
    return $baseUrl . '&' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$resetUrl = $baseUrl . '&' . http_build_query([
    'cat' => 'aplikace',
    'err_f' => ['stav' => 'nevyr'],
    'err_per' => $nastaveni['per_page'],
    'err_p' => 1,
    'err_sort' => 'kdy',
    'err_dir' => 'DESC',
], '', '&', PHP_QUERY_RFC3986);
$sortColumns = [
    'id' => 'ID',
    'kdy' => 'Kdy',
    'modul' => 'Modul / oblast',
    'akce' => 'Akce',
    'zprava' => 'Co se stalo',
    'uzivatel' => 'Uživatel',
    'stav' => 'Stav',
];
?>
<?php if (is_array($notice)): ?>
    <p class="admin_script_result<?= !empty($notice['success']) ? '' : ' is-error' ?>"><?= h((string)($notice['message'] ?? '')) ?></p>
<?php endif; ?>

<section class="admin_rights_editor_panel">
    <form method="get" action="<?= h(cb_root_url('index.php')) ?>" autocomplete="off">
        <input type="hidden" name="m" value="administrace">
        <input type="hidden" name="page" value="log_chyby">
        <input type="hidden" name="cat" value="aplikace">
        <input type="hidden" name="err_p" value="1">
        <input type="hidden" name="err_sort" value="<?= h((string)$nastaveni['sort']) ?>">
        <input type="hidden" name="err_dir" value="<?= h((string)$nastaveni['dir']) ?>">

        <div class="admin_matrix_wrap table-wrap">
            <table class="admin_matrix admin_log_chyby_app_table">
                <colgroup>
                    <col class="admin_chyby_col_id">
                    <col class="admin_chyby_col_datetime">
                    <col class="admin_chyby_col_module">
                    <col class="admin_chyby_col_action_name">
                    <col class="admin_chyby_col_message">
                    <col class="admin_chyby_col_user">
                    <col class="admin_chyby_col_status">
                    <col class="admin_chyby_col_actions">
                </colgroup>
                <thead>
                    <tr class="admin_log_chyby_filter_row filter-row">
                        <th><input class="filter-input" type="text" name="err_f[id]" value="<?= h($filtry['id']) ?>" placeholder="ID" autocomplete="off"></th>
                        <th><input class="filter-input" type="text" name="err_f[kdy]" value="<?= h($filtry['kdy']) ?>" placeholder="datum a čas" autocomplete="off"></th>
                        <th>
                            <select class="filter-input" name="err_f[modul]">
                                <option value="">Všechny</option>
                                <?php foreach ($moduly as $modul): ?>
                                    <option value="<?= h($modul) ?>"<?= $filtry['modul'] === $modul ? ' selected' : '' ?>><?= h(cb_admin_log_chyby_modul_label($modul)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input class="filter-input" type="text" name="err_f[akce]" value="<?= h($filtry['akce']) ?>" placeholder="akce" autocomplete="off"></th>
                        <th><input class="filter-input" type="text" name="err_f[zprava]" value="<?= h($filtry['zprava']) ?>" placeholder="chyba nebo kód" autocomplete="off"></th>
                        <th><input class="filter-input" type="text" name="err_f[uzivatel]" value="<?= h($filtry['uzivatel']) ?>" placeholder="uživatel" autocomplete="off"></th>
                        <th>
                            <select class="filter-input" name="err_f[stav]">
                                <option value="nevyr"<?= $filtry['stav'] === 'nevyr' ? ' selected' : '' ?>>Nevyřešené</option>
                                <option value="vyr"<?= $filtry['stav'] === 'vyr' ? ' selected' : '' ?>>Vyřešené</option>
                                <option value="vse"<?= $filtry['stav'] === 'vse' ? ' selected' : '' ?>>Všechny</option>
                            </select>
                        </th>
                        <th class="admin_log_chyby_action_head"><a href="<?= h($resetUrl) ?>" class="filter-reset-btn" title="Zrušit filtr" aria-label="Zrušit filtr">×</a></th>
                    </tr>
                    <tr>
                        <?php foreach ($sortColumns as $key => $label): ?>
                            <?php
                            $active = $nastaveni['sort'] === $key;
                            $nextDir = $active && $nastaveni['dir'] === 'ASC' ? 'DESC' : 'ASC';
                            $sortUrl = $url(['err_p' => 1, 'err_sort' => $key, 'err_dir' => $nextDir]);
                            $arrow = $active ? ($nastaveni['dir'] === 'ASC' ? '↑' : '↓') : '↕';
                            ?>
                            <th class="admin_log_chyby_sort<?= $active ? ' is-active' : '' ?>"><a href="<?= h($sortUrl) ?>"><?= h($label) ?> <span><?= h($arrow) ?></span></a></th>
                        <?php endforeach; ?>
                        <th class="admin_log_chyby_action_head">Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($data['chyby'] === []): ?>
                        <tr><td colspan="8" class="admin_rights_editor_empty">Zvoleným filtrům neodpovídá žádná chyba.</td></tr>
                    <?php else: ?>
                        <?php foreach ($data['chyby'] as $chyba): ?>
                            <?php
                            $jmeno = trim((string)($chyba['user_jmeno'] ?? '') . ' ' . (string)($chyba['user_prijmeni'] ?? ''));
                            $uzivatel = $jmeno !== '' ? $jmeno : trim((string)($chyba['user_email'] ?? ''));
                            if ($uzivatel === '') {
                                $uzivatel = !empty($chyba['id_user']) ? 'Uživatel ID ' . (string)$chyba['id_user'] : 'Systém';
                            }
                            $detail = trim((string)($chyba['detail'] ?? ''));
                            $poznamka = trim((string)($chyba['poznamka'] ?? ''));
                            $technickyDetail = array_filter([
                                $detail !== '' ? 'Detail: ' . $detail : '',
                                !empty($chyba['soubor']) ? 'Soubor: ' . (string)$chyba['soubor'] . (!empty($chyba['radek']) ? ':' . (string)$chyba['radek'] : '') : '',
                                !empty($chyba['url']) ? 'URL: ' . (string)$chyba['url'] : '',
                                $poznamka !== '' ? 'Poznámka: ' . $poznamka : '',
                            ]);
                            $oblast = trim((string)($chyba['cis_oblast'] ?? ''));
                            $popis = trim((string)($chyba['cis_popis'] ?? ''));
                            $hint = trim((string)($chyba['cis_hint'] ?? ''));
                            $datumCas = $formatDatumCas($chyba['kdy'] ?? null);
                            ?>
                            <tr>
                                <td><?= h((string)(int)$chyba['id_log_chyby']) ?></td>
                                <td class="admin_log_chyby_datetime"><span><?= h($datumCas['datum']) ?></span><?php if ($datumCas['cas'] !== ''): ?><small><?= h($datumCas['cas']) ?></small><?php endif; ?></td>
                                <td><?= h($oblast !== '' ? $oblast : cb_admin_log_chyby_modul_label((string)$chyba['modul'])) ?></td>
                                <td><?= h((string)($chyba['akce'] ?: '—')) ?></td>
                                <td>
                                    <strong><?= h($popis !== '' ? $popis : (string)($chyba['zprava'] ?: 'Neznámá chyba')) ?></strong>
                                    <?php if (!empty($chyba['kod'])): ?><br><small>Kód: <?= h((string)$chyba['kod']) ?></small><?php endif; ?>
                                    <?php if ($hint !== ''): ?><br><small>Doporučení: <?= h($hint) ?></small><?php endif; ?>
                                    <?php if ($technickyDetail !== []): ?>
                                        <details><summary>Technický detail</summary><pre><?= h(implode("\n", $technickyDetail)) ?></pre></details>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($uzivatel) ?></td>
                                <td class="<?= !empty($chyba['vyreseno']) ? 'txt_zelena' : 'txt_cervena' ?>"><?= !empty($chyba['vyreseno']) ? 'Vyřešeno' : 'Nevyřešeno' ?></td>
                                <td class="admin_log_chyby_action">
                                    <button type="submit" form="admin-log-chyby-delete" name="id_log_chyby" value="<?= h((string)(int)$chyba['id_log_chyby']) ?>" class="admin_log_chyby_delete" onclick="return window.confirm('Opravdu odstranit tento jeden záznam chyby?');">Odstranit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="admin_log_chyby_pagination list-bottom">
            <div class="per-form">
                <span>Zobrazuji <?= h((string)$data['first_row']) ?>–<?= h((string)$data['last_row']) ?> z celkem <strong><?= h((string)$data['celkem']) ?></strong></span>
                <select name="err_per" class="filter-input">
                    <?php foreach ([20, 50, 100, 500] as $perPage): ?>
                        <option value="<?= h((string)$perPage) ?>"<?= $nastaveni['per_page'] === $perPage ? ' selected' : '' ?>><?= h((string)$perPage) ?> řádků</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pagination-icon">
                <?php $previousDisabled = $data['strana'] <= 1; $nextDisabled = $data['strana'] >= $data['stran']; ?>
                <a class="icon-btn<?= $previousDisabled ? ' disabled' : '' ?>" href="<?= $previousDisabled ? '#' : h($url(['err_p' => 1])) ?>">«</a>
                <a class="icon-btn<?= $previousDisabled ? ' disabled' : '' ?>" href="<?= $previousDisabled ? '#' : h($url(['err_p' => $data['strana'] - 1])) ?>">‹</a>
                <span class="icon-btn page-current"><?= h((string)$data['strana']) ?> / <?= h((string)$data['stran']) ?></span>
                <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($url(['err_p' => $data['strana'] + 1])) ?>">›</a>
                <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($url(['err_p' => $data['stran']])) ?>">»</a>
            </div>
        </div>
    </form>
    <form id="admin-log-chyby-delete" method="post" action="<?= h($baseUrl . '&cat=aplikace') ?>">
        <input type="hidden" name="cb_action" value="admin_log_chyby_delete">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
    </form>
</section>
