<?php
declare(strict_types=1);
/* Účel: Autorizovaný společný vstup osobních sdělení, jejich zobrazení a přečtení.
 * Správu příjemců předává samostatné knihovně Administrace, nepoužívá push oznámení.
 */
require_once __DIR__.'/../db/db_admin_sdeleni.php';

/** Identita příjemce pochází výhradně z platného přihlášení. */
function cb_sdeleni_user(): int
{
    $id=(int)($_SESSION['cb_user']['id_user']??0);
    if (empty($_SESSION['login_ok']) || $id<=0) { throw new CbUserVisibleException('Přihlaste se do IS.',401); }
    return $id;
}

/** Převod datumů používá společný formát stejně jako ostatní části IS. */
function cb_sdeleni_cas(?string $value): string
{
    return $value ? cb_format('dc',$value) : '—';
}

/** Datum v živé kartě formátuje stejná centrální knihovna jako oba archivy. */
function cb_sdeleni_osobni_odpoved(mysqli $db, int $user): array
{
    $rows=cb_sdeleni_osobni($db,$user);
    foreach ($rows as &$row) { $row['odeslano_text']=cb_sdeleni_cas($row['odeslano']); }
    unset($row);
    return ['zpravy'=>$rows,'server_ts'=>time()];
}

/** JSON vstup je nezávislý na aktuálním modulu a chráněný společnou session a CRF. */
function cb_sdeleni_vstup(): void
{
    $action=(string)($_GET['cb_sdeleni']??'');
    if ($action==='') { return; }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        $user=cb_sdeleni_user();
        if (in_array($action,['ciselniky','nahled','zverejnit'],true)) {
            require_once __DIR__.'/../../administrace/admin_lib/admin_sdeleni.php';
            $data=cb_admin_sdeleni_vstup($action,$user);
        } elseif ($action==='seznam' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET') {
            $data=cb_sdeleni_osobni_odpoved(db(),$user);
        } elseif (in_array($action,['zobrazeno','precteno'],true) && ($_SERVER['REQUEST_METHOD']??'')==='POST') {
            cb_crf_vyzaduj();
            $id=filter_var($_POST['id_sdeleni']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false) { throw new CbUserVisibleException('Neplatné sdělení.'); }
            cb_sdeleni_zaznamenej(db(),$user,$id,$action==='precteno');
            $data=cb_sdeleni_osobni_odpoved(db(),$user);
        } else { throw new CbUserVisibleException('Neplatný požadavek na sdělení.'); }
        echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        http_response_code($error instanceof CbUserVisibleException ? (in_array($error->getCode(),[401,403],true)?$error->getCode():400) : 500);
        echo json_encode(['ok'=>false,'err'=>cb_chyba_uzivatel($error,['module'=>'SYSTEM','action'=>'Info od admina'])],JSON_UNESCAPED_UNICODE);
    }
    exit;
}
