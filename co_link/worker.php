<?php
declare(strict_types=1);
/* Co-Link: volitelne CLI zpracovani push fronty; bezne zpravy posila ihned API po jejich ulozeni. */
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/data.php';
require_once __DIR__.'/push_dispatch.php';

$options=getopt('',['base-url:','worker','prostredi:']);
$PROSTREDI=($options['prostredi']??'server')==='local'?'LOCAL':'SERVER';
$base=rtrim((string)($options['base-url']??''),'/').'/';
if(parse_url($base,PHP_URL_SCHEME)!=='https' || !parse_url($base,PHP_URL_HOST)) { fwrite(STDERR,"Pouziti: php worker.php --base-url=https://vas-web/co_link/ [--worker]\n"); exit(2); }
if(!co_pripraveno() || !defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_SUBJECT')) { fwrite(STDERR,"Co-Link nebo Web Push neni pripraven.\n"); exit(2); }
do { co_push_davka($base); if(!array_key_exists('worker',$options)) break; sleep(2); } while(true);
