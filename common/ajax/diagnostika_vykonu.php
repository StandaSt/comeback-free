<?php
/* Prijme omezenou davku vykonovych metadat; identitu IS nebo Co-Linku overuje nezavisle na klientovi. */
declare(strict_types=1);
require_once __DIR__.'/../lib/diagnostika_vykonu.php';
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') {http_response_code(405);exit;}
// Pri vypnuti neotevirame DB ani session a neprijimame zadne zaznamy.
$config=cb_diag_config();
if (!$config['enabled']) {http_response_code(204);exit;}
try {
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>60000) {http_response_code(413);exit;}
    $raw=file_get_contents('php://input',false,null,0,60001);
    if ($raw===false || strlen($raw)>60000) {http_response_code(413);exit;}
    $data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    $module=$data['module']??'';
    if (!in_array($module,['email','co_link'],true) || !is_array($data['events']??null) || count($data['events'])>20) {http_response_code(400);exit;}
    if ($module==='co_link') {
        require_once __DIR__.'/../../co_link/co_lib/co_lib.php';
        co_puvod(); $identity=co_identita();
        if (!$identity) {http_response_code(401);exit;}
        co_csrf($identity); $user=(int)$identity['id_user']; $login=0;
    } else {
        require_once __DIR__.'/../lib/session_boot.php';
        require_once __DIR__.'/../config/secrets.php';
        require_once __DIR__.'/../lib/app.php';
        require_once __DIR__.'/../lib/ochrana_crf.php';
        cb_session_guard_entry();
        if (empty($_SESSION['login_ok'])) {http_response_code(401);exit;}
        cb_crf_vyzaduj();
        if (empty($_SESSION['cb_pc_session_token']) || !hash_equals((string)$_SESSION['cb_pc_session_token'],(string)($_SERVER['HTTP_X_COMEBACK_PC_SESSION']??''))) {http_response_code(409);exit;}
        $user=(int)($_SESSION['cb_user']['id_user']??0);$login=(int)($_SESSION['cb_id_login']??0);
        session_write_close();
    }
    if (!cb_diag_enabled($config,$user)) {http_response_code(204);exit;}
    foreach ($data['events'] as $event) {
        $row=cb_diag_client_event($event,$module,$user,$login,(string)($_SERVER['HTTP_USER_AGENT']??''));
        if ($row!==null) cb_diag_write($row);
    }
    http_response_code(204);
} catch (Throwable) {http_response_code(400);}
