<?php
// Samostatný JSON endpoint náhledu a výslovného odeslání archivních kontrol, s kontrolou práv a CRF.
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/moduly.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
require_once __DIR__ . '/../../common/lib/mailer.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/kontrola_trzeb_email.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) cb_session_forget_auth();
    if (empty($_SESSION['login_ok']) || !cb_modul_ma_pristup('provoz') || !cb_kt_ma_pravo() || !cb_kt_ma_pravo(CB_KT_ULOZIT)) {
        http_response_code(403); throw new CbUserVisibleException('Nemáte právo odeslat výsledek kontroly.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { http_response_code(405); throw new CbUserVisibleException('Neplatný požadavek.'); }
    if (!cb_crf_platny()) { http_response_code(403); throw new CbUserVisibleException('Platnost formuláře vypršela. Obnovte stránku.'); }
    $input=json_decode((string)file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new CbUserVisibleException('Neplatná data požadavku.');
    // Náhledy jsou krátkodobé a patří jedné relaci; opakované odeslání stejného tokenu není možné.
    foreach (($_SESSION['kt_email'] ?? []) as $key=>$preview) if (($preview['time'] ?? 0)<time()-1800) unset($_SESSION['kt_email'][$key]);
    if (($input['action'] ?? '')==='preview') {
        $state=cb_kt_email_stav(db(),$input);
        $generated=(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('d. m. Y H:i:s');
        $body=cb_kt_email_obsah($state['data'],$generated);
        $subject='Kontrola tržeb · '.cb_format('d',$state['data']['from']).' – '.cb_format('d',$state['data']['to']);
        $token=bin2hex(random_bytes(24));
        while (count($_SESSION['kt_email'] ?? [])>=5) array_shift($_SESSION['kt_email']);
        $_SESSION['kt_email'][$token]=['time'=>time(),'fingerprint'=>cb_kt_email_otisk($state),'filters'=>$input,'subject'=>$subject,'body'=>$body];
        echo json_encode(['ok'=>true,'token'=>$token,'body'=>$body,'subject'=>$subject,'sender'=>$state['sender'],'recipient'=>$state['recipient']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    } elseif (($input['action'] ?? '')==='send') {
        $token=is_string($input['token'] ?? null) ? $input['token'] : '';
        $preview=$_SESSION['kt_email'][$token] ?? null;
        if (!is_array($preview)) throw new CbUserVisibleException('Náhled vypršel nebo již byl použit. Otevřete nový náhled.');
        $state=cb_kt_email_stav(db(),$preview['filters']);
        if (!hash_equals($preview['fingerprint'],cb_kt_email_otisk($state))) {
            unset($_SESSION['kt_email'][$token]);
            throw new CbUserVisibleException('Kontroly nebo kontakty se od vytvoření náhledu změnily. Otevřete nový náhled.');
        }
        unset($_SESSION['kt_email'][$token]);
        cb_mail_send('hr',$state['recipient']['email'],$preview['subject'],$preview['body'],
            html_entity_decode(strip_tags(str_replace(['</p>','</tr>','<br>'],"\n",$preview['body'])),ENT_QUOTES,'UTF-8'),[], $state['sender']);
        cb_user_akce_zapis(['id_user_akce_typ'=>16,'modul'=>'provoz','objekt'=>'kontrola_trzeb_email','pole'=>'email_prijemce',
            'hodnota_new'=>$state['recipient']['email'],'zdroj'=>'kontrola_trzeb',
            'detail'=>['od'=>$state['data']['from'],'do'=>$state['data']['to'],
                'kontroly'=>implode(',',array_column($state['data']['controls'],'id')),'odpoved_na'=>$state['sender']['email']]]);
        echo json_encode(['ok'=>true,'message'=>'Výsledek kontroly byl odeslán na e-mail '.$state['recipient']['name'].'.'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    } else throw new CbUserVisibleException('Neplatná akce.');
} catch (Throwable $e) {
    if (http_response_code()<400) http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e instanceof CbUserVisibleException ? $e->getMessage() : cb_chyba_uzivatel($e,['module'=>'PROVOZ','action'=>'E-mail kontroly tržeb'])],JSON_UNESCAPED_UNICODE);
}
