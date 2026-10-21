<?php
// Zpracuje pouze potvrzenou zmenu platnosti kontroly s pravy 218/219 a pobockovym rozsahem.
declare(strict_types=1);
require_once __DIR__ . '/../db/db_kontrola_trzeb_platnost.php';

// POST i pri primem volani overuje prava, CRF, cilovou pobocku a povoleny stav.
function cb_kt_platnost_post(): never
{
    $url = cb_root_url('index.php?m=provoz&page=archiv_kontrol_trzeb');
    try {
        if (!cb_modul_ma_pristup('provoz') || !cb_kt_ma_pravo() || !cb_kt_ma_pravo(CB_KT_ULOZIT)) {
            throw new CbUserVisibleException('Nemáte právo měnit platnost kontroly.');
        }
        if (!cb_crf_platny()) { throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji a akci opakujte.'); }
        $branches = cb_kt_pobocky(db());
        $filters = cb_kt_filtry($_POST, $branches);
        $valid = (string)($_POST['kt_platny'] ?? '');
        $id = filter_var($_POST['kt_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || !in_array($valid, ['0','1'], true)) { throw new CbUserVisibleException('Neplatná změna platnosti.'); }
        cb_kt_platnost_uloz(db(), $id, $filters['branch'], (int)$valid, (int)$_SESSION['cb_user']['id_user']);
        $url = cb_kt_url($filters, 'archiv_kontrol_trzeb', ['kt_id'=>$id, 'kt_neplatne'=>1]);
        $_SESSION['kt_flash'] = ['success'=>true, 'message'=>$valid === '1' ? 'Kontrola je opět platná.' : 'Kontrola byla označena jako neplatná.'];
    } catch (Throwable $e) {
        $_SESSION['kt_flash'] = ['success'=>false, 'message'=>$e instanceof CbUserVisibleException ? $e->getMessage()
            : cb_chyba_uzivatel($e, ['module'=>'PROVOZ','action'=>'Změna platnosti kontroly tržeb'])];
    }
    header('Location: ' . $url, true, 303);
    exit;
}
