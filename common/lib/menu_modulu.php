<?php
declare(strict_types=1);
/* Účel: Spojí datové definice modulů s jediným rendererem menu a předem připraví menu povolených modulů bez jejich obsahu. */
require_once __DIR__.'/moduly.php';
require_once __DIR__.'/../includes/blok_menu.php';

/** Doplní odkazy a označení stránky; definice ani oprávnění nepatří do JavaScriptu. */
function cb_menu_modulu_data(string $module, string $page = '', string $source = ''): array
{
    $definitions=[
        'provoz'=>['provoz/lib/menu_data.php','cb_provoz_menu_data'],
        'hr'=>['hr/hr_lib/hr_menu_data.php','cb_hr_menu_data'],
        'smeny'=>['smeny/sm_lib/sm_menu_data.php','cb_smeny_menu_data'],
        'ukoly'=>['ukoly/uk_lib/uk_menu_data.php','cb_ukoly_menu_data'],
        'helpdesk'=>['helpdesk/hl_lib/hl_menu_data.php','cb_helpdesk_menu_data'],
        'administrace'=>['administrace/admin_lib/admin_menu_data.php','cb_administrace_menu_data'],
    ];
    if(!isset($definitions[$module])) throw new InvalidArgumentException('Neznámý modul menu.');
    [$file,$provider]=$definitions[$module];
    require_once dirname(__DIR__,2).'/'.$file;
    $menu=$provider();
    $menu['module']=$module;
    $menu['key']=$menu['key'] ?? 'page';
    $menu['default_page']=$menu['default_page'] ?? 'prehled';
    if($page==='dashboard') $page='prehled';
    $page=$page!==''?$page:$menu['default_page'];
    $base=['m'=>$module];
    if($module==='helpdesk') $base['src']=in_array($source,['provoz','hr','smeny','ukoly'],true)?$source:'provoz';
    foreach($menu['items'] as &$item) {
        $item['url']=cb_root_url('index.php?'.http_build_query($base+[$menu['key']=>$item['page']], '', '&', PHP_QUERY_RFC3986));
        $item['active_pages']=$item['active_pages'] ?? [$item['page']];
        $item['active']=in_array($page,$item['active_pages'],true);
        foreach($item['items'] ?? [] as $index=>$child) {
            $item['items'][$index]['url']=cb_root_url('index.php?'.http_build_query($base+[$menu['key']=>$child['page']]+($child['params'] ?? []),'','&',PHP_QUERY_RFC3986));
        }
    }
    unset($item);
    return $menu;
}

/** Běžné serverové zobrazení i šablony používají stejná data a stejné HTML. */
function cb_menu_modulu_vykreslit(string $module, string $page = '', string $source = ''): void
{
    cb_render_blok_menu(cb_menu_modulu_data($module,$page,$source));
}

/** Šablony patří jen současnému přihlášení; nepoužívají obsah modulů ani vzdálené aktualizace. */
function cb_menu_modulu_sablony(): void
{
    foreach(cb_moduly_povolene() as $module) {
        if(!cb_modul_ma_pristup($module)) continue;
        echo '<template data-cb-module-menu="'.cb_blok_menu_h($module).'">';
        cb_menu_modulu_vykreslit($module);
        echo '</template>';
    }
}
