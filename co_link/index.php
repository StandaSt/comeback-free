<?php
declare(strict_types=1);
/* Co-Link: samostatny vstup PWA s vlastnim zarizenim; overeny IS se pripoji automaticky bez dalsi volby. */
require_once __DIR__.'/data.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$boot=['pripraveno'=>false,'identita'=>null,'is_token'=>'','vapid'=>''];
try {
    $boot['pripraveno']=co_pripraveno();
    if($boot['pripraveno']) {
        $identity=co_identita();
        if($identity) $boot['identita']=['id'=>(int)$identity['id_user'],'jmeno'=>$identity['jmeno'],'csrf'=>$identity['csrf']];
        else {
            require_once __DIR__.'/../common/lib/session_boot.php';
            require_once __DIR__.'/../common/lib/ochrana_crf.php';
            require_once __DIR__.'/../common/lib/prebrani_prav.php';
            if(!empty($_SESSION['login_ok']) && cb_session_validate_after_login() && cb_prebrani_prav_profil()===null
                && co_pravo((int)($_SESSION['cb_user']['id_user']??0),700)) $boot['is_token']=cb_crf_token();
            if(session_status()===PHP_SESSION_ACTIVE) session_write_close();
        }
    }
    $boot['vapid']=defined('CB_VAPID_PUBLIC')?(string)CB_VAPID_PUBLIC:'';
} catch(Throwable $e) { error_log('Co-Link vstup: '.get_class($e)); }
$boot['is_url']=rtrim(dirname(rtrim(co_cesta(),'/')),'/') . '/index.php';
define('CO_LINK_VIEW',true);
require __DIR__.'/view.php';
