<?php
// Archiv všech povolených poboček s volitelným výběrem a kompaktním detailem s horní navigací.
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE !== 'provoz') { http_response_code(403); exit; }
require_once __DIR__ . '/../lib/kontrola_trzeb_formular.php';
require_once __DIR__ . '/../lib/kontrola_trzeb_archiv_filtry.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
$ktArchive = true;
$ktReadOnly = true;
$ktInput = [];
$ktFlash = $_SESSION['kt_flash'] ?? null;
unset($_SESSION['kt_flash']);
try {
    $ktBranches = cb_kt_pobocky(db());
    $ktFilters = cb_kt_filtry($_GET, $ktBranches);
    $ktArchiveFilters = cb_kt_archiv_filtry($_GET, $ktBranches);
    $ktReady = cb_kt_archiv_pripraven(db());
    $ktValidityReady = $ktReady && cb_kt_platnost_pripravena(db());
    $ktInvalid = ($_GET['kt_neplatne'] ?? '') === '1';
    $ktId = max(0, (int)($_GET['kt_id'] ?? 0));
    // Nova kontrola navazuje na jednu z vybranych pobocek seznamu.
    if ($ktId === 0 && $ktArchiveFilters['ids'] !== [] && !in_array($ktFilters['branch'], $ktArchiveFilters['ids'], true)) {
        $ktFilters['branch'] = $ktArchiveFilters['ids'][0];
    }
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
    } elseif ($ktReady) {
        $ktList = cb_kt_archiv_seznam(db(), $ktArchiveFilters['ids'], $ktArchiveFilters['page'], $ktInvalid, $ktArchiveFilters['per']);
        $ktArchiveFilters['page'] = $ktList['page'];
    }
} catch (CbUserVisibleException $e) {
    echo '<p class="txt_cervena">' . h($e->getMessage()) . '</p>';
    return;
}
?>
<link rel="stylesheet" href="<?= h(cb_module_asset_url('style/kontrola_trzeb.css', 'provoz')) ?>?v=<?= filemtime(__DIR__ . '/../style/kontrola_trzeb.css') ?>">
<section class="kt_page<?= $ktDetail !== null ? ' kt_archive_detail' : '' ?>">
  <?php if (is_array($ktFlash)): ?><p class="<?= !empty($ktFlash['success']) ? 'kt_success' : 'kt_warning' ?>" role="status"><?= h($ktFlash['message']) ?></p><?php endif; ?>
  <div class="kt_heading kt_archive_actions"><a class="kt_archive_button" href="<?= h(cb_kt_url($ktFilters)) ?>">Nová kontrola tržeb</a>
    <?php if ($ktDetail !== null): ?><a class="kt_archive_button" href="<?= h(cb_kt_archiv_url($ktFilters, $ktArchiveFilters)) ?>">Zpět do archivu</a><?php endif; ?>
  </div>
  <?php require __DIR__ . '/../includes/kontrola_trzeb_filtry.php'; ?>
  <?php if (!$ktReady): ?><p class="kt_warning">Archiv zatím není připraven k ukládání.</p>
  <?php elseif ($ktDetail !== null): ?>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_podklady.php'; ?>
    <div class="kt_heading"><span>Uložil <?= h($ktDetail['ulozil_jmeno']) ?> · <?= h(cb_format('dcs', $ktDetail['ulozeno'])) ?></span></div>
    <div class="kt_archive_validity"><strong><?= (int)($ktDetail['platny'] ?? 1) === 1 ? 'Platná kontrola' : 'Neplatná kontrola' ?></strong>
    <?php if ($ktValidityReady && cb_kt_ma_pravo(CB_KT_ULOZIT)): ?>
      <form class="kt_inline" method="post" action="<?= h(cb_root_url('index.php?m=provoz&page=kontrola_trzeb')) ?>" data-kt-validity>
        <input type="hidden" name="cb_action" value="kt_platnost"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <input type="hidden" name="kt_id" value="<?= $ktId ?>"><input type="hidden" name="kt_pob" value="<?= $ktFilters['branch'] ?>">
        <?php // Zmena platnosti nesmi zahodit vyber seznamu pri navratu z detailu. ?>
        <input type="hidden" name="kt_vyber" value="1"><input type="hidden" name="kt_per" value="<?= $ktArchiveFilters['per'] ?>"><input type="hidden" name="kt_strana" value="<?= $ktArchiveFilters['page'] ?>">
        <?php foreach ($ktArchiveFilters['ids'] as $branchId): ?><input type="hidden" name="kt_pobocky[]" value="<?= $branchId ?>"><?php endforeach; ?>
        <input type="hidden" name="kt_platny" value="<?= (int)($ktDetail['platny'] ?? 1) === 1 ? '0' : '1' ?>">
        <button type="submit"><?= (int)($ktDetail['platny'] ?? 1) === 1 ? 'Označit jako neplatnou' : 'Obnovit platnost' ?></button>
      </form>
    <?php endif; ?></div>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_tabulka.php'; ?>
  <?php else: ?>
    <?php require __DIR__ . '/../includes/kontrola_trzeb_archiv_seznam.php'; ?>
  <?php endif; ?>
</section>
