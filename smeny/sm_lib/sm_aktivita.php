<?php
declare(strict_types=1);
/* Účel: Připraví bezpečný kontext směnové akce a propojí potvrzené změny či odmítnutí se společnou historií činnosti. */
require_once __DIR__.'/../../common/lib/user_aktivita.php';

/** Vstupní formulář se nikdy neloguje celý; ukládají se jen identifikátory a provozní časy. */
function cb_smeny_aktivita_kontext(array $data=[]): array
{
    $input=array_merge($_GET,$_POST,$data); $context=[];
    foreach(['id_pob','week','id_person','id_slot','id_smeny_rozpis','id_smeny_sablona','id_smeny_smena','id_smeny_oznameni'] as $key) {
        if(isset($input[$key]) && is_scalar($input[$key]) && filter_var($input[$key],FILTER_VALIDATE_INT)!==false && (int)$input[$key]>=0) $context[$key]=(int)$input[$key];
    }
    foreach(['datum','tyden_od'] as $key) if(is_string($input[$key] ?? null) && preg_match('~^\d{4}-\d{2}-\d{2}$~D',$input[$key])) $context[$key]=$input[$key];
    foreach(['cas_od','cas_do'] as $key) if(is_string($input[$key] ?? null) && preg_match('~^\d{2}:\d{2}$~D',$input[$key])) $context[$key]=$input[$key];
    if(is_string($input['klic_slotu'] ?? null) && preg_match('~^[a-f0-9]{32}$~D',$input['klic_slotu'])) $context['klic_slotu']=$input['klic_slotu'];
    if(is_string($input['page'] ?? null) && preg_match('~^[a-z_]{1,50}$~D',$input['page'])) $context['stranka']=$input['page'];
    if(is_string($input['cb_aktivita_id'] ?? null) && preg_match('~^[a-f0-9-]{36}$~D',$input['cb_aktivita_id'])) $context['klientske_odeslani']=$input['cb_aktivita_id'];
    return $context;
}

/** Neúspěch záznamu nesmí změnit původní důvod odmítnutí uživatelské operace. */
function cb_smeny_aktivita_chyba(Throwable $error): void
{
    $code=is_string($_POST['action'] ?? null)?substr($_POST['action'],0,80):'neznamy_pozadavek';
    cb_aktivita_server_zapis('smeny',$code,cb_smeny_aktivita_kontext()+[
        'duvod'=>$error instanceof CbUserVisibleException?$error->getMessage():'technicka_chyba','kod'=>$error->getCode(),
    ],20,'akce',0,false);
}
