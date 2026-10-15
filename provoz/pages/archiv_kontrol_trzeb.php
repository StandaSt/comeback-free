<?php
// Samostatny archiv kontrol trzeb a zobrazeni ulozenych hodnot bez prepocitavani zdroju.
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE !== 'provoz') { http_response_code(403); exit; }
require_once __DIR__ . '/../lib/kontrola_trzeb_formular.php';
$ktArchive = true;
$ktReadOnly = true;
$ktInput = [];
$ktFlash = $_SESSION['kt_flash'] ?? null;
unset($_SESSION['kt_flash']);
try {
    $ktBranches = cb_kt_pobocky(db());
    $ktFilters = cb_kt_filtry($_GET, $ktBranches);
    $ktReady = cb_kt_archiv_pripraven(db());
    $ktId = max(0, (int)($_GET['kt_id'] ?? 0));
    $ktDetail = null;
    $ktList = ['rows' => [], 'count' => 0, 'page' => 1];
    if ($ktReady && $ktId > 0) {
        $ktDetail = cb_kt_archiv_detail(db(), $ktId, $ktFilters['branch']);
        $ktRows = [];
        foreach ($ktDetail['rows'] as $row) {
            $ktRows[$row['polozka']] = ['left' => cb_kt_halere($row['odevzdane']),
                'right' => cb_kt_halere($row['spocitane']), 'note' => $row['poznamka']];
        }
        $ktSources = json_decode($ktDetail['podklady'], true, 512, JSON_THROW_ON_ERROR);
    } elseif ($ktReady) { $ktList = cb_kt_archiv_seznam(db(), $ktFilters['branch'], (int)($_GET['kt_strana'] ?? 1)); }
} catch (CbUserVisibleException $e) {
    echo '<p class="txt_cervena">' . h($e->getMessage()) . '</p>';
    return;
}
?>
<link rel="stylesheet" href="<?= h(cb_module_asset_url('style/kontrola_trzeb.css', 'provoz')) ?>?v=<?= filemtime(__DIR__ . '/../style/kontrola_trzeb.css') ?>">
<section class="kt_page">
  <?php if (is_array($ktFlash)): ?><p class="<?= !empty($ktFlash['success']) ? 'kt_success' : 'kt_warning' ?>" role="status"><?= h($ktFlash['message']) ?></p><?php endif; ?>
  <p><a href="<?= h(cb_kt_url($ktFilters)) ?>">Nová kontrola tržeb</a></p>
  <?php require __DIR__ . '/../includes/kontrola_trzeb_filtry.php'; ?>
  <?php if (!$ktReady): ?><p class="kt_warning">Archiv zatím není připraven k ukládání.</p>
  <?php elseif ($ktDetail !== null): ?>
    <div class="kt_heading"><strong><?= h($ktBranches[$ktFilters['branch']]['name']) ?> · <?= h(cb_format('d', $ktDetail['datum_od'])) ?> – <?= h(cb_format('d', $ktDetail['datum_do'])) ?></strong><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb')) ?>">Zpět do archivu</a></div>
    <p>Uložil <?= h($ktDetail['ulozil_jmeno']) ?> · <?= h(cb_format('dcs', $ktDetail['ulozeno'])) ?> · kontrola #<?= h((string)$ktId) ?></p>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_podklady.php'; ?>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_tabulka.php'; ?>
  <?php else: ?>
    <div class="kt_table_wrap"><table class="kt_table"><thead><tr><th>Období</th><th>Uložil</th><th>Uloženo</th><th>Akce</th></tr></thead><tbody>
      <?php if ($ktList['rows'] === []): ?><tr><td colspan="4">Pro tuto pobočku nejsou uložené žádné kontroly.</td></tr><?php endif; ?>
      <?php foreach ($ktList['rows'] as $row): ?><tr><td><?= h(cb_format('d', $row['datum_od'])) ?> – <?= h(cb_format('d', $row['datum_do'])) ?></td><td><?= h($row['ulozil_jmeno']) ?></td><td><?= h(cb_format('dcs', $row['ulozeno'])) ?></td><td><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_id' => $row['id_kontrola']])) ?>">Detail #<?= h((string)$row['id_kontrola']) ?></a></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="kt_pager"><?php if ($ktList['page'] > 1): ?><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_strana' => $ktList['page'] - 1])) ?>">‹ Předchozí</a><?php endif; ?><span>Strana <?= $ktList['page'] ?> / <?= max(1, (int)ceil($ktList['count'] / 30)) ?> · <?= $ktList['count'] ?> kontrol</span><?php if ($ktList['page'] * 30 < $ktList['count']): ?><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_strana' => $ktList['page'] + 1])) ?>">Další ›</a><?php endif; ?></p>
  <?php endif; ?>
</section>
