<?php
declare(strict_types=1);
/* Účel: Přijímá pouze povolené činnosti ovládání Helpdesku; identitu, původ a výsledek určuje server. */
if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) { http_response_code(404); exit; }
require_once __DIR__.'/../hl_lib/hl_prava.php';
require_once __DIR__.'/../hl_lib/hl_cinnost.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['login_ok'])) { http_response_code(401); echo '{"ok":false}'; return; }
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { http_response_code(405); echo '{"ok":false}'; return; }
cb_crf_vyzaduj();
try {
    $raw=file_get_contents('php://input',false,null,0,48001);
    if (!is_string($raw) || strlen($raw)>48000) throw new InvalidArgumentException('Příliš velká dávka.');
    $body=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
    if (!is_array($body) || array_diff(array_keys($body),['owner','events'])!==[]) throw new InvalidArgumentException('Neplatná obálka událostí.');
    $owner=(int)($_SESSION['cb_id_login'] ?? 0).':'.cb_helpdesk_current_user_id();
    if (!is_array($body) || ($body['owner'] ?? null)!==$owner) { http_response_code(403); echo '{"ok":false}'; return; }
    $events=$body['events'] ?? null;
    if (!is_array($events) || !array_is_list($events) || count($events)<1 || count($events)>20) throw new InvalidArgumentException('Neplatná dávka.');
    $items=array_map('cb_helpdesk_cinnost_klient',$events);
    // Limit chrání session i úložiště; opakování po výpadku používá stále stejné UUID.
    $minute=intdiv(time(),60);
    if (($_SESSION['hl_cinnost_limit']['minuta'] ?? null)!==$minute || ($_SESSION['hl_cinnost_limit']['owner'] ?? null)!==$owner) $_SESSION['hl_cinnost_limit']=['minuta'=>$minute,'pocet'=>0,'owner'=>$owner];
    if ($_SESSION['hl_cinnost_limit']['pocet']+count($items)>300) { http_response_code(429); echo '{"ok":false}'; return; }
    $db=db(); $db->begin_transaction(); $accepted=[];
    try {
        foreach ($items as $item) { $result=cb_cinnost_zapsat($item['id_akce'],$item['parametry']); $accepted[]=$result['udalost_uuid']; }
        $db->commit();
    } catch (Throwable $error) { $db->rollback(); throw $error; }
    $_SESSION['hl_cinnost_limit']['pocet']+=count($items);
    echo json_encode(['ok'=>true,'accepted'=>$accepted],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException|JsonException $error) {
    http_response_code(400); echo '{"ok":false}';
} catch (Throwable $error) {
    error_log('Helpdesk: zápis činnosti selhal, kód '.(int)$error->getCode());
    http_response_code(503); echo '{"ok":false}';
}
