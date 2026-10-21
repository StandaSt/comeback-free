<?php
// Formular kontroly trzeb za jednu pobocku a tyden nebo vlastni obdobi.
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE !== 'provoz') { http_response_code(403); exit; }
require_once __DIR__ . '/../lib/kontrola_trzeb_formular.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
$ktArchive = false;
$ktReadOnly = !cb_kt_ma_pravo(CB_KT_ULOZIT);
$ktFlash = $_SESSION['kt_flash'] ?? null;
unset($_SESSION['kt_flash']);
try {
    $ktBranches = cb_kt_pobocky(db());
    $ktFilters = cb_kt_filtry($_GET, $ktBranches);
    $ktInput = [];
    $ktResume = is_string($_GET['kt_resume'] ?? null) ? $_GET['kt_resume'] : '';
    $ktForm = $_SESSION['kt_forms'][$ktResume] ?? null;
    if (is_array($ktForm) && ($ktForm['time'] ?? 0) >= time() - 43200
        && $ktForm['snapshot']['branch'] === $ktFilters['branch']
        && $ktForm['snapshot']['from'] === $ktFilters['from'] && $ktForm['snapshot']['to'] === $ktFilters['to']) {
        $ktData = ['token' => $ktResume, 'snapshot' => $ktForm['snapshot']];
        $ktInput = $ktForm['input'] ?? [];
    } else { $ktData = cb_kt_formular_data(db(), $ktFilters); }
    $ktSnapshot = $ktData['snapshot'];
    $ktSources = $ktSnapshot['sources'];
    $ktRight = $ktSnapshot['automatic'];
    $ktRows = [];
    foreach (cb_kt_polozky() as $key => [$label, $manual]) {
        if ($manual) {
            try { $ktRight[$key] = cb_kt_halere((string)($ktInput['spocitane'][$key] ?? '')); }
            catch (CbUserVisibleException $e) { $ktRight[$key] = null; }
        }
        $ktRows[$key] = ['left' => $ktSnapshot['left'][$key], 'right' => $ktRight[$key] ?? null, 'note' => ''];
    }
    $ktRows = cb_kt_souhrn($ktRows);
    $ktReady = cb_kt_archiv_pripraven(db());
} catch (CbUserVisibleException $e) {
    echo '<p class="txt_cervena">' . h($e->getMessage()) . '</p>';
    return;
}
?>
<link rel="stylesheet" href="<?= h(cb_module_asset_url('style/kontrola_trzeb.css', 'provoz')) ?>?v=<?= filemtime(__DIR__ . '/../style/kontrola_trzeb.css') ?>">
<section class="kt_page">
  <?php if (is_array($ktFlash)): ?><p class="<?= !empty($ktFlash['success']) ? 'kt_success' : 'kt_warning' ?>" role="status"><?= h($ktFlash['message']) ?></p><?php endif; ?>
  <?php require __DIR__ . '/../includes/kontrola_trzeb_filtry.php'; ?>
  <?php if (!$ktReady): ?><p class="kt_warning">Archiv zatím není připraven k ukládání.</p><?php endif; ?>
  <form method="post" action="<?= h(cb_root_url('index.php?m=provoz&page=kontrola_trzeb')) ?>" data-kt-form>
    <input type="hidden" name="cb_action" value="kt_ulozit"><input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>"><input type="hidden" name="kt_token" value="<?= h($ktData['token']) ?>">
    <?php require __DIR__ . '/../includes/kontrola_trzeb_tabulka.php'; ?>
  </form>
</section>
