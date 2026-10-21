<?php
declare(strict_types=1);
/* Účel: Ověří výběr a přesný náhled příjemců před zveřejněním admin sdělení.
 * Koncept žije krátce jen v session; do DB se ukládá zpráva a konkrétní příjemci bez JSON filtrů.
 */
require_once __DIR__.'/../../common/db/db_admin_sdeleni.php';
require_once __DIR__.'/../../common/lib/firemni_pristup.php';

/** Stránka i přímé požadavky vyžadují Administraci a právo 110 pro správu sdělení. */
function cb_admin_sdeleni_firmy(mysqli $db, int $user): array
{
    if (!cb_pravo_ma(100)) { throw new CbUserVisibleException('Nemáte přístup do Administrace.',403); }
    if (!cb_pravo_ma(110)) { throw new CbUserVisibleException('Nemáte právo spravovat sdělení od admina.',403); }
    $firmy=cb_firemni_pristup_firmy($db,$user);
    if ($firmy===[]) { throw new CbUserVisibleException('Nemáte povolenou žádnou firmu.'); }
    return $firmy;
}

/** Prázdný či podvržený výběr nesmí nechtěně oslovit všechny uživatele. */
function cb_admin_sdeleni_ids(mixed $raw, array $allowed): array
{
    if (!is_array($raw)) { throw new CbUserVisibleException('Neplatný výběr příjemců.'); }
    $ids=[];
    foreach ($raw as $id) {
        $value=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($value===false || !in_array($value,$allowed,true)) { throw new CbUserVisibleException('Výběr obsahuje nepovolenou položku.'); }
        $ids[$value]=$value;
    }
    sort($ids);
    return array_values($ids);
}

/** Sestaví pouze explicitně vybraný režim a zkontroluje délky i platnost před náhledem. */
function cb_admin_sdeleni_draft(array $post, array $lists): array
{
    foreach (['nadpis','obsah','rezim'] as $key) {
        if (isset($post[$key]) && !is_string($post[$key])) { throw new CbUserVisibleException('Neplatná hodnota formuláře sdělení.'); }
    }
    $title=trim((string)($post['nadpis']??'')); $body=trim((string)($post['obsah']??''));
    $days=filter_var($post['dny']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10]]);
    if ($title==='' || $body==='') { throw new CbUserVisibleException('Vyplňte nadpis a text sdělení.'); }
    if (mb_strlen($title)>120 || mb_strlen($body)>1000) { throw new CbUserVisibleException('Nadpis může mít nejvýše 120 znaků a text 1 000 znaků.'); }
    if ($days===false) { throw new CbUserVisibleException('Platnost musí být 1 až 10 dnů.'); }
    $mode=(string)($post['rezim']??'');
    if (!in_array($mode,['vsichni','skupina','jednotlivec'],true)) { throw new CbUserVisibleException('Vyberte příjemce sdělení.'); }
    $selection=['rezim'=>$mode];
    if ($mode==='skupina') {
        foreach (['role','sloty','pobocky'] as $key) {
            $selection[$key]=cb_admin_sdeleni_ids($post[$key]??[],array_map('intval',array_column($lists[$key],'id')));
        }
        if (($selection['role']===[] && $selection['sloty']===[]) || $selection['pobocky']===[]) {
            throw new CbUserVisibleException('Vyberte alespoň jednu roli nebo slot a alespoň jednu pobočku.');
        }
    } elseif ($mode==='jednotlivec') {
        $selection['id_users']=cb_admin_sdeleni_ids($post['id_users']??[],array_map('intval',array_column($lists['lide'],'id_user')));
        if ($selection['id_users']===[]) { throw new CbUserVisibleException('Vyberte alespoň jednoho uživatele.'); }
    }
    return ['nadpis'=>$title,'obsah'=>$body,'dny'=>$days,'vyber'=>$selection];
}

/** Publikovat lze pouze přesně ten obsah a seznam, který správce viděl v čerstvém náhledu. */
function cb_admin_sdeleni_vstup(string $action, int $user): array
{
    $db=db();$firmy=cb_admin_sdeleni_firmy($db,$user);
    if ($action==='ciselniky' && ($_SERVER['REQUEST_METHOD']??'')==='GET') { return cb_sdeleni_ciselniky($db,$firmy); }
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { throw new CbUserVisibleException('Použijte formulář sdělení.'); }
    cb_crf_vyzaduj();
    if ($action==='nahled') {
        unset($_SESSION['cb_sdeleni_draft']);
        $draft=cb_admin_sdeleni_draft($_POST,cb_sdeleni_ciselniky($db,$firmy));
        $draft['prijemci']=cb_sdeleni_prijemci($db,$firmy,$draft['vyber']);
        if ($draft['prijemci']===[]) { throw new CbUserVisibleException('Výběru neodpovídá žádný aktivní uživatel.'); }
        $draft['token']=bin2hex(random_bytes(24));$draft['do']=time()+600;$draft['autor']=$user;
        $_SESSION['cb_sdeleni_draft']=$draft;
        return ['token'=>$draft['token'],'nadpis'=>$draft['nadpis'],'obsah'=>$draft['obsah'],'dny'=>$draft['dny'],'prijemci'=>$draft['prijemci']];
    }
    $draft=$_SESSION['cb_sdeleni_draft']??null;
    if (!is_array($draft) || $draft['autor']!==$user || $draft['do']<time() || !hash_equals($draft['token'],(string)($_POST['token']??''))) {
        throw new CbUserVisibleException('Náhled vypršel nebo již byl odeslán. Zobrazte nový náhled příjemců.');
    }
    $fresh=cb_sdeleni_prijemci($db,$firmy,$draft['vyber']);
    if ($fresh!==$draft['prijemci']) { unset($_SESSION['cb_sdeleni_draft']);throw new CbUserVisibleException('Seznam příjemců se změnil. Před zveřejněním obnovte náhled.'); }
    $id=cb_sdeleni_zverejni($db,$user,$draft);
    unset($_SESSION['cb_sdeleni_draft']);
    return ['id_sdeleni'=>$id,'pocet'=>count($draft['prijemci'])];
}
