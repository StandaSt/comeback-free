<?php
declare(strict_types=1);
/* Co-Link: chranene stazeni souboru, bez verejnych odkazu do uloziste a bez spousteni HTML. */
require_once __DIR__.'/data.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: sandbox; default-src 'none'");
try {
    $identity=co_identita();
    if(!$identity) co_chyba('Přihlášení vypršelo.',401);
    $file=co_rows('SELECT p.*,z.konverzace,z.smazano FROM co_priloha p JOIN co_zprava z ON z.id=p.zprava WHERE p.id=?',[(int)($_GET['id']??0)])[0]??[];
    if(!$file || $file['smazano']!==null) co_chyba('Příloha není dostupná.',404);
    co_konverzace((int)$identity['id_user'],(int)$file['konverzace']);
    if(!preg_match('/^[a-f0-9]{64}$/D',(string)$file['soubor'])) co_chyba('Příloha není dostupná.',404);
    $path=co_uloziste().'/'.$file['soubor'];
    if(!is_file($path)) co_chyba('Příloha není dostupná.',404);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="priloha"; filename*=UTF-8\'\''.rawurlencode($file['nazev']));
    header('Content-Length: '.filesize($path));
    readfile($path);
} catch(DomainException $e) { http_response_code($e->getCode()?:400); echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
catch(Throwable $e) { http_response_code(500); echo 'Přílohu se nepodařilo otevřít.'; }
