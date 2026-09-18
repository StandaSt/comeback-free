<?php
declare(strict_types=1);

/*
 * Pohled kategorie AI analytik.
 * Ukazuje bezpecny souhrn neuspesnych auditu a odkazuje na jejich ID.
 */

require_once __DIR__ . '/../../admin_db/admin_ai_chyby_db.php';

$limit = in_array((int)($_GET['limit'] ?? 50), [20, 50, 100, 250], true) ? (int)$_GET['limit'] : 50;
$aiChyby = cb_admin_ai_chyby_nacti(db(), $limit);
?>
<form class="admin_chyby_limit" method="get" action="<?= h(cb_root_url('index.php')) ?>">
    <input type="hidden" name="m" value="administrace">
    <input type="hidden" name="page" value="log_chyby">
    <input type="hidden" name="cat" value="ai">
    <label>Zobrazit posledních
        <select name="limit" onchange="this.form.submit()">
            <?php foreach ([20, 50, 100, 250] as $value): ?>
                <option value="<?= $value ?>"<?= $limit === $value ? ' selected' : '' ?>><?= $value ?></option>
            <?php endforeach; ?>
        </select>
        záznamů
    </label>
</form>

<section class="admin_rights_editor_panel">
    <div class="admin_matrix_wrap table-wrap">
        <table class="admin_matrix admin_chyby_simple_table">
            <thead><tr><th>ID auditu</th><th>Začátek</th><th>Uživatel</th><th>Model</th><th>Výsledek</th><th>Co se stalo</th><th>Doba</th></tr></thead>
            <tbody>
                <?php if ($aiChyby === []): ?>
                    <tr><td colspan="7" class="admin_rights_editor_empty">Nebyl nalezen žádný neúspěšný AI audit.</td></tr>
                <?php endif; ?>
                <?php foreach ($aiChyby as $row): ?>
                    <?php
                    $jmeno = trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? ''));
                    $detail = array_filter([
                        trim((string)($row['error_type'] ?? '')) !== '' ? 'Typ: ' . (string)$row['error_type'] : '',
                        trim((string)($row['error_code'] ?? '')) !== '' ? 'Kód: ' . (string)$row['error_code'] : '',
                    ]);
                    ?>
                    <tr>
                        <td><?= h((string)(int)$row['id_ai_analytik_audit']) ?></td>
                        <td><?= h($formatKdy($row['created_at'] ?? null)) ?></td>
                        <td><?= h($jmeno !== '' ? $jmeno . ' · ID ' . (string)$row['id_user'] : 'ID ' . (string)$row['id_user']) ?></td>
                        <td><?= h((string)$row['model']) ?></td>
                        <td><?= h(cb_admin_ai_chyba_stav((string)$row['status'])) ?></td>
                        <td>
                            <?= h((string)($row['error_message'] ?: 'Podrobnost nebyla uložena.')) ?>
                            <?php if ($detail !== []): ?><details><summary>Technický detail</summary><pre><?= h(implode("\n", $detail)) ?></pre></details><?php endif; ?>
                        </td>
                        <td><?= h(number_format(((int)$row['duration_ms']) / 1000, 1, ',', ' ')) ?> s</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

