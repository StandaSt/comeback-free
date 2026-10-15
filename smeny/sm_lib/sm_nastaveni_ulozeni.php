<?php
// Zpracuje pouze ulozeni uzaverky pozadavku do dvou sloupcu set_system s pravem 410.
declare(strict_types=1);
require_once __DIR__ . '/sm_nastaveni_pravo.php';
require_once __DIR__ . '/sm_uzaverka.php';

// Pravo a CRF overujeme i pri primem POST; ostatni systemova nastaveni zustavaji beze zmeny.
function cb_smeny_nastaveni_ulozit(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['action'] ?? '') !== 'smeny_nastaveni_ulozit') { return; }
    try {
        if (!cb_smeny_nastaveni_ma_pravo()) { throw new CbUserVisibleException('Nemáte právo měnit nastavení Směn.'); }
        if (!cb_crf_platny()) { throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji a akci opakujte.'); }
        $setting = cb_smeny_uzaverka_validace($_POST['uzaverka_den'] ?? null, $_POST['uzaverka_hodina'] ?? null);
        if (!cb_smeny_uzaverka_nacti(db())['pripraveno']) { throw new CbUserVisibleException('Nastavení uzávěrky zatím není připravené k ukládání.'); }
        $stmt = db()->prepare('UPDATE set_system SET smeny_pozadavky_den=?, smeny_pozadavky_hodina=? WHERE id_set=1');
        $stmt->bind_param('ii', $setting['den'], $setting['hodina']);
        $stmt->execute();
        $stmt->close();
        $_SESSION['sm_nastaveni_flash'] = ['ok' => true, 'text' => 'Uzávěrka požadavků byla uložena.'];
    } catch (Throwable $e) {
        $_SESSION['sm_nastaveni_flash'] = ['ok' => false, 'text' => $e instanceof CbUserVisibleException ? $e->getMessage()
            : cb_chyba_uzivatel($e, ['module' => 'smeny', 'action' => 'Uložení uzávěrky požadavků'])];
    }
    header('Location: ' . cb_root_url('index.php?m=smeny&page=nastaveni'), true, 303);
    exit;
}
