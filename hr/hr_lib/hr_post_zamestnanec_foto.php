<?php
declare(strict_types=1);
/* Účel: Nahradí aktuální portrét podle ID osoby pod právem 307, bez verzování osobních údajů. */
require_once __DIR__ . '/hr_fotografie.php';

/** Při chybě auditu vrátí původní obsah; žádné trvalé historické kopie nevytváří. */
function hr_post_zamestnanec_foto(mysqli $db, int $idUser): void
{
    $idPerson=(int)($_POST['id_person'] ?? 0);
    $section=(string)($_POST['sekce'] ?? 'prehled');
    if (!in_array($section,['prehled','osobni_udaje','pracovni_pomer','dochazka','dokumenty','hodnoceni','vybaveni','onboarding','poznamky'],true)) $section='prehled';
    $url=cb_root_url('index.php?m=hr&page=zamestnanec&id='.$idPerson.'&sekce='.$section.((string)($_POST['upravit'] ?? '')==='1'?'&upravit=1':''));
    $path=null;
    $original=null;
    $file=dirname(__DIR__).'/hr_img/zamestnanci/'.$idPerson.'.jpg';
    try {
        if (!cb_pravo_ma(307)) throw new CbUserVisibleException('Nemáte právo měnit fotografii zaměstnance.');
        hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
        if (is_file($file)) {
            $original=file_get_contents($file);
            if ($original===false) throw new RuntimeException('Původní fotografii nelze načíst.');
        }
        $path=hr_store_employee_photo($_FILES['foto'] ?? [],$idPerson);
        if ($path===null) throw new CbUserVisibleException('Vyberte fotografii.');
        // Audit musí být potvrzen ještě uvnitř obsluhy, aby při jeho selhání nezůstal nový soubor navíc.
        hr_audit_dokoncit($db,true,'Fotografie zaměstnance byla uložena.');
    } catch (Throwable $e) {
        if (is_string($path)) {
            if (is_string($original)) file_put_contents($file,$original,LOCK_EX);
            elseif (is_file($file)) unlink($file);
        }
        hr_audit_form_finish($url,false,cb_hr_chyba_text($e,'Uložení fotografie zaměstnance'));
    }
    hr_audit_form_finish($url,true,'Fotografie zaměstnance byla uložena.');
}
