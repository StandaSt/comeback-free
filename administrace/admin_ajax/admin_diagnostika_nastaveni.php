<?php
/* Ulozi casove omezeny vypinac diagnostiky a vrati uzivatele do hlavniho IS; vyzaduje prava Administrace/Export DB a CRF. */
declare(strict_types=1);
require_once __DIR__.'/../../common/lib/session_boot.php';
require_once __DIR__.'/../../common/config/secrets.php';
require_once __DIR__.'/../../common/lib/app.php';
require_once __DIR__.'/../../common/lib/ochrana_crf.php';
require_once __DIR__.'/../../common/db/db_prava.php';
require_once __DIR__.'/../../common/lib/diagnostika_vykonu.php';
cb_session_guard_entry();
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);exit;}
if (empty($_SESSION['login_ok'])) {http_response_code(401);exit;}
cb_db_prava_nacti_do_session(db(),(int)($_SESSION['cb_user']['id_user']??0));
if (!cb_diag_admin_allowed()) {http_response_code(403);exit;}
cb_crf_vyzaduj();
try {
    cb_diag_save(($_POST['enabled']??'')==='1',(int)($_POST['minutes']??30),(string)($_POST['users']??''));
    $_SESSION['cb_diag_notice']='Nastavení diagnostiky bylo uloženo.';
} catch (Throwable $error) {
    $_SESSION['cb_diag_notice']='Nastavení se nepodařilo uložit. Ověřte ID uživatelů, dobu měření a dostupnost adresáře diagnostiky.';
}
// Handler lezi dve URL urovne pod korenem IS; relativni navrat zachova i lokalni alias /comeback/.
header('Location: ../../index.php?m=administrace&page=diagnostika_vykonu',true,303);
