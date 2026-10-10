<?php
/* Ucel: Ovladac druheho doplnovaciho HR importu s nahledem, potvrzenim a viditelnym prubehem. */
declare(strict_types=1);
require_once __DIR__.'/../../common/scripts/hr_import_udaje.php';

/** Srozumitelne oznaceni tabulek v kontrolnim prehledu. */
function cb_admin_hr_udaje_labels(): array
{
    return ['hr_osobni_udaje'=>'Osobní údaje','hr_pracovni_vztah'=>'Pracovní vztahy','hr_mzda'=>'Smluvní mzdy','hr_pracovni_uvazek'=>'Úvazky','hr_adresa'=>'Adresy','hr_email'=>'E-maily','hr_telefon'=>'Telefony','hr_bankovni_ucet'=>'Bankovní účty','hr_zdravotni_pojisteni'=>'Zdravotní pojištění','hr_slot'=>'Sloty','hr_funkce'=>'Oprava historického přiřazení funkce','hr_pracoviste'=>'Pracoviště','hr_pracoviste_main'=>'Hlavní pracoviště','hr_mzdy_mesic'=>'Historické mzdové měsíce','hr_poznamka'=>'Poznámky k dořešení','hr_dokument'=>'Oprava přiřazení dokumentů'];
}

/** Zobrazi vysledek nahledu vcetne konkretnich zmen a upozorneni, bez tajnych udaju. */
function cb_admin_hr_udaje_result(array $result): void
{
    $labels=cb_admin_hr_udaje_labels();
    echo '<p>Změny se týkají <strong>'.h((string)$result['people']).' zaměstnanců</strong>. Počet níže je počet operací, nikoli počet lidí.</p><table><tr><th>Oblast</th><th>Operací</th></tr>';
    foreach ($result['counts'] as $table=>$count) echo '<tr><td>'.h($labels[$table] ?? $table).'</td><td>'.h((string)$count).'</td></tr>';
    echo '</table><details><summary>Konkrétní navržené změny</summary><table><tr><th>Person</th><th>Oblast</th><th>Původní → nové hodnoty</th></tr>';
    foreach ($result['changes'] as $change) {
        $before=json_encode($change['before'] ?? [],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $after=json_encode($change['after'] ?? [],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        echo '<tr><td>'.h((string)$change['id_person']).'</td><td>'.h($labels[$change['table']] ?? $change['table']).'<br>'.h($change['action']).'</td><td>'.h($before.' → '.$after).'</td></tr>';
    }
    echo '</table></details><details><summary>Upozornění pro personalistu ('.count($result['warnings']).')</summary><ul>';
    foreach ($result['warnings'] as $note) echo '<li>Person '.h((string)$note['id_person']).': '.nl2br(h($note['text'])).'</li>';
    echo '</ul></details><details><summary>Údaje ukládané zatím do poznámky</summary><p>Nemají samostatné strukturované mapování v tomto importu. Neztratí se; zůstanou u zaměstnance k dořešení.</p><ul>';
    foreach ($result['unsupported_fields'] as $field=>$count) echo '<li>'.h($field).' — '.h((string)$count).' osob</li>';
    echo '</ul></details>';
}

/** Import vyzaduje pravo100, CSRF, souhlas a nahled svazany s planem i stavem DB. */
function cb_admin_hr_udaje_handle(): void
{
    $action=(string)($_POST['cb_action'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST' || !in_array($action,['admin_hr_udaje_preview','admin_hr_udaje_import'],true)) return;
    if (!cb_pravo_ma(100)) { http_response_code(403); exit('K importu je potřeba přístup do administrace.'); }
    cb_crf_vyzaduj();
    $user=(int)($_SESSION['cb_user']['id_user'] ?? 0);
    if ($user<=0) { http_response_code(403); exit('Přihlaste se znovu.'); }
    $csrf=cb_crf_token(); $write=$action==='admin_hr_udaje_import';
    $proof=$_SESSION['cb_hr_udaje_preview'] ?? [];
    if ($write && ((string)($_POST['confirm'] ?? '')!=='1' || empty($proof['token']) || !hash_equals((string)$proof['token'],(string)($_POST['preview_token'] ?? '')) || (int)($proof['expires'] ?? 0)<time())) {
        http_response_code(409); exit('Nejdřív zkontrolujte údaje a potvrďte jejich import.');
    }
    unset($_SESSION['cb_hr_udaje_preview']);
    if ($write) session_write_close();
    set_time_limit(0); ignore_user_abort(true); ini_set('zlib.output_compression','0');
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store'); header('X-Accel-Buffering: no');
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Doplnění údajů HR</title><style>body{font-family:Arial,sans-serif;margin:24px}pre{max-height:38vh;overflow:auto;white-space:pre-wrap;border:1px solid #ccc;padding:12px}table{border-collapse:collapse;max-width:100%}td,th{border:1px solid #ccc;padding:6px;vertical-align:top;overflow-wrap:anywhere}details{margin:18px 0}button{padding:12px}</style></head><body><h1>'.($write ? 'Import údajů do HR' : 'Kontrola údajů pro HR').'</h1><pre><!--'.str_repeat(' ',4096).'-->';
    $progress=static function(string $message): void { echo h($message)."\n"; flush(); };
    $success=false;
    try {
        if ($write) {
            // Potvrzeni nesmi pouzit drivejsi nahled po mezitim provedene uprave zdroju nebo DB.
            $check=hru_run(db(),$user,false,$progress);
            if (!hash_equals((string)($proof['hash'] ?? ''),$check['hash']) || !hash_equals((string)($proof['fingerprint'] ?? ''),$check['fingerprint'])) throw new HrUdajeImportException('Podklady nebo HR data se od náhledu změnily. Proveďte znovu kontrolu.');
            $result=hru_run(db(),$user,true,$progress,(string)$proof['fingerprint'],(string)$proof['hash']);
            echo '</pre><h2>Import údajů dokončen</h2>';
        } else {
            $result=hru_run(db(),$user,false,$progress);
            // Token ulozime az po uspesne kontrole. Ostatni soubezne session zmeny zachovame.
            $token=bin2hex(random_bytes(24));
            $_SESSION['cb_hr_udaje_preview']=['token'=>$token,'hash'=>$result['hash'],'fingerprint'=>$result['fingerprint'],'expires'=>time()+3600];
            session_write_close();
            echo '</pre><h2>Údaje připraveny k importu</h2>';
        }
        cb_admin_hr_udaje_result($result);
        if (!$write) echo '<form method="post" action="'.h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')).'"><input type="hidden" name="cb_action" value="admin_hr_udaje_import"><input type="hidden" name="cb_crf" value="'.h($csrf).'"><input type="hidden" name="preview_token" value="'.h($token).'"><label><input type="checkbox" name="confirm" value="1" required> Potvrzuji doplnění a opravy údajů HR v uvedeném rozsahu, bez resetu.</label><p><button type="submit">Doplnit údaje zaměstnanců do HR</button></p></form>';
        $success=true;
    } catch (Throwable $e) {
        $message=$e instanceof HrUdajeImportException || $e instanceof HrSmlouvyImportException ? $e->getMessage() : cb_admin_chyba_text($e,'Doplnění údajů HR');
        echo '</pre><h2 role="alert">Import zastaven</h2><p>'.h($message).'</p><p>Při ztrátě spojení během potvrzení transakce nejdřív zkontrolujte výsledek v HR.</p>';
    }
    if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
    if ($write) cb_admin_chyba_audit(static function() use($success): void { cb_user_akce_zapis(['id_user_akce_typ'=>14,'modul'=>'administrace','objekt'=>'hr_import_udaje','pole'=>'spusteni','vysledek'=>$success ? 1 : 0,'zdroj'=>'administrace']); });
    echo '<p><a href="'.h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')).'">Zpět do administrace</a></p></body></html>';
    exit;
}
