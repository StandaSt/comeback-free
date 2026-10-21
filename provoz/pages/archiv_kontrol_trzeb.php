<?php
// Archiv s filtrem platnosti, obnovou kontroly a souhrny z ulozenych polozek bez novych zdroju.
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE !== 'provoz') { http_response_code(403); exit; }
require_once __DIR__ . '/../lib/kontrola_trzeb_formular.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
$ktArchive = true;
$ktReadOnly = true;
$ktInput = [];
$ktFlash = $_SESSION['kt_flash'] ?? null;
unset($_SESSION['kt_flash']);
try {
    $ktBranches = cb_kt_pobocky(db());
    $ktFilters = cb_kt_filtry($_GET, $ktBranches);
    $ktReady = cb_kt_archiv_pripraven(db());
    $ktValidityReady = $ktReady && cb_kt_platnost_pripravena(db());
    $ktInvalid = ($_GET['kt_neplatne'] ?? '') === '1';
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
        $ktRows = cb_kt_souhrn($ktRows);
        $ktSources = json_decode($ktDetail['podklady'], true, 512, JSON_THROW_ON_ERROR);
    } elseif ($ktReady) { $ktList = cb_kt_archiv_seznam(db(), $ktFilters['branch'], (int)($_GET['kt_strana'] ?? 1), $ktInvalid); }
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
    <div class="kt_heading"><strong><?= h($ktBranches[$ktFilters['branch']]['name']) ?> · <?= h(cb_format('d', $ktDetail['datum_od'])) ?> – <?= h(cb_format('d', $ktDetail['datum_do'])) ?></strong><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_neplatne' => $ktInvalid ? 1 : 0])) ?>">Zpět do archivu</a></div>
    <p>Uložil <?= h($ktDetail['ulozil_jmeno']) ?> · <?= h(cb_format('dcs', $ktDetail['ulozeno'])) ?> · kontrola #<?= h((string)$ktId) ?></p>
    <div><strong><?= (int)($ktDetail['platny'] ?? 1) === 1 ? 'Platná kontrola' : 'Neplatná kontrola' ?></strong>
    <?php if ($ktValidityReady && cb_kt_ma_pravo(CB_KT_ULOZIT)): ?>
      <form class="kt_inline" method="post" action="<?= h(cb_root_url('index.php?m=provoz&page=kontrola_trzeb')) ?>" data-kt-validity>
        <input type="hidden" name="cb_action" value="kt_platnost"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <input type="hidden" name="kt_id" value="<?= $ktId ?>"><input type="hidden" name="kt_pob" value="<?= $ktFilters['branch'] ?>">
        <input type="hidden" name="kt_platny" value="<?= (int)($ktDetail['platny'] ?? 1) === 1 ? '0' : '1' ?>">
        <button type="submit"><?= (int)($ktDetail['platny'] ?? 1) === 1 ? 'Označit jako neplatnou' : 'Obnovit platnost' ?></button>
      </form>
    <?php endif; ?></div>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_podklady.php'; ?>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_tabulka.php'; ?>
  <?php else: ?>
    <div class="kt_table_wrap"><table class="kt_table"><thead><tr><th>Období</th><th>Uložil</th><th>Uloženo</th><th>Akce</th></tr></thead><tbody>
      <?php if ($ktList['rows'] === []): ?><tr><td colspan="4">Pro tuto pobočku nejsou uložené žádné kontroly.</td></tr><?php endif; ?>
      <?php foreach ($ktList['rows'] as $row): ?><tr<?= (int)$row['platny'] === 0 ? ' class="kt_invalid"' : '' ?>><td><?= h(cb_format('d', $row['datum_od'])) ?> – <?= h(cb_format('d', $row['datum_do'])) ?><?= (int)$row['platny'] === 0 ? ' · neplatná' : '' ?></td><td><?= h($row['ulozil_jmeno']) ?></td><td><?= h(cb_format('dcs', $row['ulozeno'])) ?></td><td><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_id' => $row['id_kontrola'], 'kt_neplatne'=>$ktInvalid ? 1 : 0])) ?>">Detail #<?= h((string)$row['id_kontrola']) ?></a></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="kt_pager"><?php if ($ktList['page'] > 1): ?><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_strana' => $ktList['page'] - 1, 'kt_neplatne' => $ktInvalid ? 1 : 0])) ?>">‹ Předchozí</a><?php endif; ?><span>Strana <?= $ktList['page'] ?> / <?= max(1, (int)ceil($ktList['count'] / 30)) ?> · <?= $ktList['count'] ?> kontrol</span><?php if ($ktList['page'] * 30 < $ktList['count']): ?><a href="<?= h(cb_kt_url($ktFilters, 'archiv_kontrol_trzeb', ['kt_strana' => $ktList['page'] + 1, 'kt_neplatne' => $ktInvalid ? 1 : 0])) ?>">Další ›</a><?php endif; ?></p>
  <?php endif; ?>
</section>
