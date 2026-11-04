<?php
/* E-mail: pouze cteni konkretni docasne prilohy pres nahodny desetiminutovy odkaz pro lokalni Office; zadna session ani heslo schranky se nepredava. */
declare(strict_types=1);
require_once __DIR__.'/prilohy.php';
header('Cache-Control: no-store, private'); header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
try {
    if (!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)) {http_response_code(405);exit;}
    $row=em_priloha_docasna((string)($_GET['token']??''));
    header('Content-Type: '.$row['type']);
    header("Content-Disposition: inline; filename=\"priloha.".pathinfo($row['name'],PATHINFO_EXTENSION)."\"; filename*=UTF-8''".rawurlencode($row['name']));
    header('Content-Length: '.strlen($row['content']));
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD') echo $row['content'];
} catch(Throwable) {http_response_code(410);header('Content-Type: text/plain; charset=utf-8');echo 'Odkaz na přílohu není dostupný. Otevřete ji znovu v IS.';}
