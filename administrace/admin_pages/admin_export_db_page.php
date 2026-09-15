<?php
declare(strict_types=1);

/* Účel souboru: Zobrazí výběr pevných skupin pro stažení SQL exportu databáze. */

require_once __DIR__ . '/../admin_lib/admin_db_export.php';
require_once __DIR__ . '/../admin_lib/admin_server_data_export.php';

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(109)) {
    http_response_code(403);
    echo '<section class="blok"><h2 class="blok_title">Přístup zamítnut</h2><p>Nemáte právo exportovat databázi.</p></section>';
    return;
}

$adminDbExportOverview = cb_admin_db_export_group_overview(cb_admin_db_export_available_tables(db()));
$adminDbExportEnvironment = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') ? 'local' : 'server';
$adminDbExportFilename = 'export_' . $adminDbExportEnvironment . '.sql';
$adminServerDataExport = (($GLOBALS['PROSTREDI'] ?? '') === 'SERVER');
$adminServerDataExportTables = cb_admin_server_data_export_tables();
?>
<div class="admin_db_export">
    <section class="blok admin_db_export_card">
        <p class="admin_db_export_description">Vyberte celé skupiny tabulek. Překryvy se automaticky odstraní a každá tabulka bude v exportu pouze jednou.</p>
        <p class="admin_db_export_target">Výchozí název souboru: <strong><?= h($adminDbExportFilename) ?></strong>. Název a umístění můžete změnit při ukládání.</p>

        <form class="admin_db_export_form" method="post" action="<?= h(cb_root_url('administrace/admin_export/admin_db_export_download.php')) ?>">
            <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
            <fieldset class="admin_db_export_groups">
                <legend>Skupiny tabulek</legend>
                <?php foreach ($adminDbExportOverview as $adminDbExportKey => $adminDbExportGroup): ?>
                    <div class="admin_db_export_group">
                        <label>
                            <input type="checkbox" name="groups[]" value="<?= h($adminDbExportKey) ?>" checked>
                            <span><?= h($adminDbExportGroup['label']) ?> (<?= count($adminDbExportGroup['tables']) ?> tabulek)</span>
                        </label>
                        <details>
                            <summary>Zobrazit tabulky</summary>
                            <p><?= h(implode(', ', $adminDbExportGroup['tables'])) ?></p>
                        </details>
                    </div>
                <?php endforeach; ?>
            </fieldset>

            <button class="admin_db_export_button" type="submit">Vytvořit export a uložit jako…</button>
        </form>
    </section>

    <?php if ($adminServerDataExport): ?>
        <section class="blok admin_db_export_card">
            <h2 class="admin_db_export_title">Export dat ze serveru na lokál</h2>
            <p class="admin_db_export_description">Exportuje pouze provozní data schválená pro přenos ze serveru na lokál. Serverové tokeny budou anonymizovány.</p>
            <p class="admin_db_export_target">Výchozí název souboru: <strong>export_server_data_pro_lokal.sql</strong>. Název a umístění můžete změnit při ukládání.</p>

            <details class="admin_db_export_table_list">
                <summary>Zobrazit tabulky (<?= count($adminServerDataExportTables) ?>)</summary>
                <p><?= h(implode(', ', $adminServerDataExportTables)) ?></p>
            </details>

            <form class="admin_db_export_form" method="post" action="<?= h(cb_root_url('administrace/admin_export/admin_server_data_export_download.php')) ?>">
                <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
                <button class="admin_db_export_button" type="submit">Vytvořit export a uložit jako…</button>
            </form>
        </section>
    <?php endif; ?>
</div>
