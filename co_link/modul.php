<?php
declare(strict_types=1);
/* Co-Link: obsah komunikace patri do PP, navigace a uzivatelske zapati do standardniho menu IS. */
if(!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE!=='co_link' || empty($_SESSION['login_ok'])) {
    http_response_code(404);
    exit;
}
if(!cb_pravo_ma(700)) {
    http_response_code(403);
    return;
}
if(!defined('CB_PP_ONLY') || CB_PP_ONLY!==true) {
    require_once __DIR__.'/../common/lib/menu_modulu.php';
    cb_menu_modulu_vykreslit('co_link',($_GET['page']??'')==='uprava_profilu'?'uprava_profilu':'prehled');
}
if(($_GET['page']??'')==='uprava_profilu') {
    require __DIR__.'/../common/pages/uprava_profilu.php';
    return;
}
?>
<section class="pp co_link_content" data-module="co_link" data-page="prehled" aria-label="Co-Link">
  <iframe class="co_link_frame" src="<?= h(cb_root_url('co_link/?is=1')) ?>" aria-label="Co-Link"></iframe>
</section>
