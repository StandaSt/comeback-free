<?php
declare(strict_types=1);
/* Účel: Vymezuje povolené činnosti Helpdesku a převádí ověřené ovládání na parametry jediného společného zapisovače. */
require_once __DIR__.'/../../common/lib/cinnost.php';
require_once __DIR__.'/hl_pages.php';

/** ID patří doplňkovým číselníkům Helpdesku; názvy a texty se ukládají pouze v SQL číselnících. */
final class CbHelpdeskCinnost
{
    public const POLE_UPRAVENO = 6001;
    public const VOLBA_ZMENENA = 6002;
    public const REAKCE_PRIDANA = 6003;
    public const PRILOHA_PRIDANA = 6004;
    public const PRILOHA_KLIK = 6005;
    public const UZAVRENE = 6001;
    public const TYP = 6002;
    public const OBLAST = 6003;
    public const URCENI = 6004;
    public const STAV = 6005;
    public const SLEDOVANI = 6006;
    public const PREDMET = 6007;
    public const POPIS = 6008;
    public const ODPOVED = 6009;
    public const PRILOHY = 6010;
}

/** Odpovídá skutečným pohledům modulu; chybějící pohled se nesmí vydávat za přehled. */
function cb_helpdesk_cinnost_stranky(): array
{
    return ['all'=>6001,'new-ticket'=>6002,'mine'=>6003,'watched'=>6004,'closed'=>6005,'uprava_profilu'=>6006];
}

/** Stav tiketu se v historii ukládá jako číselníková hodnota, nikoli opakovaný text. */
function cb_helpdesk_cinnost_stav(string $stav): int
{
    return match ($stav) { 'nový'=>1, 'řeší se'=>2, 'vyřešeno'=>3, default=>throw new InvalidArgumentException('Neznámý stav tiketu.') };
}

/** Důvod sledování má stejná čísla v klientovi, handleru i číselníku pole. */
function cb_helpdesk_cinnost_sledovani(?string $duvod): int
{
    return match ($duvod) { null=>0, 'sleduje'=>1, 'stejny_problem'=>2, 'reagoval'=>3, 'autor'=>4,
        default=>throw new InvalidArgumentException('Neznámý důvod sledování.') };
}

/** Každá potvrzená akce nese autorizovaný tiket a jeho firmu; obsah zpráv se do historie nekopíruje. */
function cb_helpdesk_cinnost_cil(mysqli $db, int $id): array
{
    return ['id_cil_typ'=>8, 'cil_id'=>$id, 'id_firma'=>cb_helpdesk_ticket_company_id($db, $id)];
}

/** Klient určuje jen událost ovládání; server znovu ověří pohled, povolená pole a přístup k tiketu. */
function cb_helpdesk_cinnost_klient(array $event): array
{
    if (array_diff(array_keys($event), ['id_akce','parametry']) !== [] || !is_int($event['id_akce'] ?? null) || !is_array($event['parametry'] ?? null)) throw new InvalidArgumentException('Neplatná událost.');
    $action=$event['id_akce']; $p=$event['parametry'];
    if (isset($p['data']) && !is_array($p['data'])) throw new InvalidArgumentException('Neplatná data činnosti.');
    $allowed=[CbCinnostAkce::MODUL_OTEVREN,CbCinnostAkce::STRANKA_OTEVRENA,CbCinnostAkce::MENU_KLIK,
        CbCinnostAkce::DETAIL_OTEVREN,CbCinnostAkce::DETAIL_ZAVREN,CbCinnostAkce::FILTR_ZMENEN,
        CbCinnostAkce::FORMULAR_ODESLAN,CbHelpdeskCinnost::POLE_UPRAVENO,CbHelpdeskCinnost::VOLBA_ZMENENA,CbHelpdeskCinnost::PRILOHA_KLIK];
    if (!in_array($action,$allowed,true) || array_diff(array_keys($p),['udalost_uuid','id_stranka','cil_id','id_pole','data']) !== []) throw new InvalidArgumentException('Nepovolená činnost.');
    $view=array_search($p['id_stranka'] ?? null,cb_helpdesk_cinnost_stranky(),true);
    if ($view===false || !cb_helpdesk_view_allowed($view)) throw new InvalidArgumentException('Nedostupný pohled Helpdesku.');
    if (!is_string($p['udalost_uuid'] ?? null)) throw new InvalidArgumentException('Chybí ID události.');
    cb_cinnost_parametry_overit($p);
    $ticket=$p['cil_id'] ?? null;
    if ($ticket!==null) {
        if (!cb_helpdesk_can_view(db(),$ticket,cb_helpdesk_current_user_id())) throw new InvalidArgumentException('Nedostupný tiket.');
        $p+=cb_helpdesk_cinnost_cil(db(),$ticket);
    }
    if (in_array($action,[3,17,6005],true) && $ticket===null) throw new InvalidArgumentException('Chybí tiket.');
    if (in_array($action,[3,17,5,6005],true) && in_array($view,['new-ticket','uprava_profilu'],true)) throw new InvalidArgumentException('Ovládání nepatří do pohledu.');
    if ($action===6005) {
        $attachment=$p['data']['id_priloha'] ?? null;
        if (!is_int($attachment) || $attachment<1 || !cb_cinnost_db_jeden(db(),'SELECT id_helpdesk_priloha FROM helpdesk_priloha WHERE id_helpdesk_priloha=? AND id_helpdesk=?',[$attachment,$ticket])) throw new InvalidArgumentException('Nedostupná příloha.');
    }
    $field=$p['id_pole'] ?? null;
    if ($action===5 && !in_array($field,[CbHelpdeskCinnost::UZAVRENE,6011,6012],true)) throw new InvalidArgumentException('Neplatný filtr.');
    if ($action===CbHelpdeskCinnost::VOLBA_ZMENENA && ($view!=='new-ticket' || !in_array($field,[6002,6003,6004],true))) throw new InvalidArgumentException('Neplatná volba.');
    if ($action===CbHelpdeskCinnost::POLE_UPRAVENO && !in_array($field,$view==='new-ticket'?[6007,6008,6010]:[6009],true)) throw new InvalidArgumentException('Neplatné pole.');
    if ($action===6001 && $field===6009 && $ticket===null) throw new InvalidArgumentException('Reakce vyžaduje tiket.');
    if ($action===11 && (($view==='new-ticket')!==($ticket===null) || $view==='uprava_profilu')) throw new InvalidArgumentException('Neplatný formulář.');
    if ($field!==null && !in_array($action,[5,6001,6002],true)) throw new InvalidArgumentException('Neočekávané pole.');
    if ($ticket!==null && !in_array($action,[3,17,11,6001,6005],true)) throw new InvalidArgumentException('Neočekávaný tiket.');
    $p['id_puvod']=CbCinnostPuvod::PROHLIZEC;
    return ['id_akce'=>$action,'parametry'=>$p];
}
