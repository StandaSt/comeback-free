<?php
/* Co-Link: HTTP vstup chraneneho stazeni prilohy; overi prihlaseni, odesle pripraveny soubor a vrati chyby. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_data.php';
require_once __DIR__ . '/../co_lib/co_priloha_pripravit.php';
// Soukrome prilohy se nesmeji cachovat ani spoustet jako dokument z naseho webu.
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
try {
    $identity=co_identita();
    if(!$identity) co_chyba('Přihlášení vypršelo.',401);
    $download=co_priloha_pripravit((int)$identity['id_user'],(int)($_GET['id']??0));
    $file=$download['priloha'];
    $path=$download['cesta'];
    // Bezpecnostni kontroly jsou dokoncene; HTTP vrstva zachova nazev i presne bajty prilohy.
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="priloha"; filename*=UTF-8\'\''.rawurlencode($file['nazev']));
    header('Content-Length: '.filesize($path));
    readfile($path);
} catch(DomainException $e) { http_response_code($e->getCode()?:400); echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
catch(Throwable $e) { http_response_code(500); echo 'Přílohu se nepodařilo otevřít.'; }
