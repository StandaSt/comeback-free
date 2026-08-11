<?php
declare(strict_types=1);

if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') {
    try {
        $db = db();
        $res = $db->query('SELECT MAX(finished_at) AS last_sync FROM smeny_aktualizace');
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }

        $lastSync = trim((string)($row['last_sync'] ?? ''));
        $runSync = true;
        if ($lastSync !== '') {
            $lastSyncTs = strtotime($lastSync);
            $runSync = $lastSyncTs === false || $lastSyncTs < (time() - 3600);
        }

        if ($runSync) {
            if (!defined('CB_SMENY_PLAN_KONTROLA_AUTO_RUN')) {
                define('CB_SMENY_PLAN_KONTROLA_AUTO_RUN', false);
            }
            require_once __DIR__ . '/../../common/lib/smeny_plan_kontrola.php';
            cb_smeny_plan_kontrola(true);
        }
    } catch (Throwable $e) {
        error_log('Local smeny plan sync selhal: ' . $e->getMessage());
    }
}
?>
<div class="provoz_denni_report_page">
    <?php require __DIR__ . '/../bloky/denni_report_zadani.php'; ?>
</div>
