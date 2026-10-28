<?php
declare(strict_types=1);
/* Účel: Přijme čtvercový výřez portrétu, ověří právo 307 a potvrdí jej společně s auditem HR. */
require_once __DIR__ . '/../hr_db/hr_zamestnanec_foto.php';

/** Neúspěšná změna uklidí pouze nově přijatý soubor, nikdy historický portrét. */
function hr_post_zamestnanec_foto(mysqli $db, int $idUser): void
{
    $idPerson=(int)($_POST['id_person'] ?? 0);
    $section=(string)($_POST['sekce'] ?? 'prehled');
    if (!in_array($section,['prehled','osobni_udaje','pracovni_pomer','dochazka','dokumenty','hodnoceni','vybaveni','onboarding','poznamky'],true)) $section='prehled';
    $url=cb_root_url('index.php?m=hr&page=zamestnanec&id='.$idPerson.'&sekce='.$section.((string)($_POST['upravit'] ?? '')==='1'?'&upravit=1':''));
    $path=null;
    try {
        if (!cb_pravo_ma(307)) throw new CbUserVisibleException('Nemáte právo měnit fotografii zaměstnance.');
        hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
        $upload=$_FILES['foto'] ?? [];
        hr_foto_over_vyrez($upload);
        $path=hr_store_employee_photo($upload);
        if ($path===null) throw new CbUserVisibleException('Vyberte fotografii.');
        hr_zamestnanec_foto_ulozit($db,$idPerson,$idUser,$path);
        // Audit musí být potvrzen ještě uvnitř obsluhy, aby při jeho selhání nezůstal nový soubor navíc.
        hr_audit_dokoncit($db,true,'Fotografie zaměstnance byla uložena.');
    } catch (Throwable $e) {
        if (is_string($path)) {
            $root=realpath(dirname(__DIR__).'/img/zamestnanci');
            $file=realpath(dirname(__DIR__,2).'/'.$path);
            if ($root!==false && $file!==false && dirname($file)===$root && is_file($file)) unlink($file);
        }
        hr_audit_form_finish($url,false,cb_hr_chyba_text($e,'Uložení fotografie zaměstnance'));
    }
    hr_audit_form_finish($url,true,'Fotografie zaměstnance byla uložena.');
}
