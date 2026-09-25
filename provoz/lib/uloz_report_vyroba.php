<?php
// lib/uloz_report_vyroba.php * Zápis reportu Výroby z vlastního formuláře.
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['vyroba_report_save'])) {
    return;
}

require_once __DIR__ . '/denni_report_vyroba.php';

$vyrobaUser = $_SESSION['cb_user'] ?? [];
$vyrobaActor = is_array($vyrobaUser) ? (int)($vyrobaUser['id_user'] ?? 0) : 0;
try {
    cb_vyroba_save(db(), $vyrobaActor, $_POST);
    $vyrobaDate = (string)$_POST['datum_reportu'];
    header('Location: ' . cb_root_url('index.php') . '?m=provoz&page=denni_report&zr_id_pob=7&datum_reportu=' . rawurlencode($vyrobaDate) . '&vyroba_saved=1');
    exit;
} catch (CbUserVisibleException $error) {
    $GLOBALS['cbVyrobaFormError'] = $error->getMessage();
} catch (Throwable $error) {
    error_log('Vyroba report save failed: ' . $error->getMessage());
    $GLOBALS['cbVyrobaFormError'] = 'Report se nepodařilo uložit. Zkus to prosím znovu.';
}
