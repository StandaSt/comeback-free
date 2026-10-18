<?php
// Zpracuje a atomicky zaloguje ulozeni uzaverky pozadavku s pravem 410.
declare(strict_types=1);
require_once __DIR__ . '/sm_nastaveni_pravo.php';
require_once __DIR__ . '/sm_uzaverka.php';
require_once __DIR__ . '/sm_aktivita.php';

// Pravo a CRF overujeme i pri primem POST; ostatni systemova nastaveni zustavaji beze zmeny.
function cb_smeny_nastaveni_ulozit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['action'] ?? '') !== 'smeny_nastaveni_ulozit') { return; }
    $transaction=false;
    try {
        if (!cb_smeny_nastaveni_ma_pravo()) { throw new CbUserVisibleException('Nemáte právo měnit nastavení Směn.'); }
        if (!cb_crf_platny()) { throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji a akci opakujte.'); }
        $setting = cb_smeny_uzaverka_validace($_POST['uzaverka_den'] ?? null, $_POST['uzaverka_hodina'] ?? null);
        if (!cb_smeny_uzaverka_nacti(db())['pripraveno']) { throw new CbUserVisibleException('Nastavení uzávěrky zatím není připravené k ukládání.'); }
        db()->begin_transaction(); $transaction=true;
        $oldStmt=db()->query('SELECT smeny_pozadavky_den,smeny_pozadavky_hodina FROM set_system WHERE id_set=1 FOR UPDATE');
        $old=$oldStmt->fetch_assoc(); $oldStmt->close();
        $stmt = db()->prepare('UPDATE set_system SET smeny_pozadavky_den=?, smeny_pozadavky_hodina=? WHERE id_set=1');
        $stmt->bind_param('ii', $setting['den'], $setting['hodina']);
        $stmt->execute();
        $stmt->close();
        if(!cb_aktivita_server_zapis('smeny','uzaverka_ulozena',cb_smeny_aktivita_kontext(),9,'set_system',1,true,json_encode($old,JSON_THROW_ON_ERROR),json_encode($setting,JSON_THROW_ON_ERROR))) throw new RuntimeException('Zápis historie činnosti se nezdařil.');
        db()->commit(); $transaction=false;
        $_SESSION['sm_nastaveni_flash'] = ['ok' => true, 'text' => 'Uzávěrka požadavků byla uložena.'];
    } catch (Throwable $e) {
        if($transaction) db()->rollback();
        cb_smeny_aktivita_chyba($e);
        $_SESSION['sm_nastaveni_flash'] = ['ok' => false, 'text' => $e instanceof CbUserVisibleException ? $e->getMessage()
            : cb_chyba_uzivatel($e, ['module' => 'smeny', 'action' => 'Uložení uzávěrky požadavků'])];
    }
    header('Location: ' . cb_root_url('index.php?m=smeny&page=nastaveni'), true, 303);
    exit;
}
