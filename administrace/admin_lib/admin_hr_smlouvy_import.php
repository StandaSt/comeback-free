<?php
/*
 * Ucel souboru: Ovlada potvrzeny doplnovaci import HR z administrace.
 * Kontrola i import maji vlastni prubehovou stranku; zapis vyzaduje nahled,
 * bezpecnostni token a souhlas prihlaseneho administratora.
 */
declare(strict_types=1);
require_once __DIR__.'/../../common/scripts/hr_import_smlouvy.php';

/** Spusti jen vybranou administracni akci; samotne nacteni import neprovadi. */
function cb_admin_hr_smlouvy_handle(): void
{
    $action = (string)($_POST['cb_action'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        || !in_array($action, ['admin_hr_smlouvy_preview','admin_hr_smlouvy_import'], true)) {
        return;
    }
    if (!cb_pravo_ma(100)) {
        http_response_code(403); exit('K importu je potřeba přístup do administrace.');
    }
    cb_crf_vyzaduj();
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    if ($idUser <= 0) {
        http_response_code(403); exit('Přihlaste se znovu.');
    }
    $csrf = cb_crf_token();
    $plan = hr_smlouvy_manifest();
    $import = $action === 'admin_hr_smlouvy_import';
    $proof = $_SESSION['cb_hr_smlouvy_preview'] ?? [];
    if ($import && ((string)($_POST['confirm'] ?? '') !== '1'
        || !hash_equals((string)($proof['token'] ?? ''), (string)($_POST['preview_token'] ?? ''))
        || empty($proof['token']) || ($proof['hash'] ?? '') !== $plan['manifest_hash']
        || (int)($proof['expires'] ?? 0) < time())) {
        http_response_code(409); exit('Nejdřív znovu zkontrolujte podklady a potvrďte import.');
    }
    $token = bin2hex(random_bytes(24));
    if (!$import) {
        $_SESSION['cb_hr_smlouvy_preview'] = ['token'=>$token,'hash'=>$plan['manifest_hash'],'expires'=>time()+3600];
    } else {
        unset($_SESSION['cb_hr_smlouvy_preview']);
    }
    session_write_close();
    set_time_limit(0);
    ignore_user_abort(true);
    ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Accel-Buffering: no');
    echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Import smluv do HR</title><body><h1>'.($import ? 'Import smluv do HR' : 'Kontrola podkladů pro HR').'</h1>';
    // Omezeny panel zachova viditelny vysledek i po kontrole stovek souboru; vypln zustava v HTML komentari.
    echo '<style>body{font-family:Arial,sans-serif;margin:24px}pre{max-height:55vh;overflow:auto;white-space:pre-wrap;border:1px solid #ccc;padding:12px}button{padding:10px}</style>';
    echo '<p>Průběh zpracování:</p><pre><!--'.str_repeat(' ',4096).'-->';
    $progress = static function (string $message): void { echo h($message)."\n"; flush(); };
    $success = false;
    try {
        if ($import) {
            $result = hr_smlouvy_import(db(), $idUser, $progress);
            echo '</pre><h2>Import dokončen</h2><p>Nově uloženo '.h((string)$result['new_files']).' souborů pro '.h((string)$result['new_people']).' zaměstnanců. Již uložených '.h((string)$result['existing_files']).' souborů přeskočeno.</p>';
            $success = true;
        } else {
            $result = hr_smlouvy_preview(db(), $progress);
            echo '</pre><h2>Podklady připraveny</h2><p>Výběr obsahuje '.h((string)$result['files']).' souborů pro '.h((string)$result['people']).' zaměstnanců.</p><p>Nově se uloží <strong>'.h((string)$result['new_files']).' souborů pro '.h((string)$result['new_people']).' zaměstnanců</strong>; shodných již uložených souborů: '.h((string)$result['existing_files']).'.</p>';
            echo '<form method="post" action="'.h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')).'"><input type="hidden" name="cb_action" value="admin_hr_smlouvy_import"><input type="hidden" name="cb_crf" value="'.h($csrf).'"><input type="hidden" name="preview_token" value="'.h($token).'"><label><input type="checkbox" name="confirm" value="1" required> Potvrzuji doplnění dokumentů a schválených údajů do HR bez resetu.</label><p><button type="submit">Doplnit smlouvy a doklady do HR</button></p></form>';
            $success = true;
        }
    } catch (Throwable $e) {
        // Technicke chyby neukazuji SQL, prihlasovaci udaje ani absolutni serverove cesty.
        $message = $e instanceof HrSmlouvyImportException ? $e->getMessage() : cb_admin_chyba_text($e, 'Import smluv do HR');
        echo '</pre><h2 role="alert">Zpracování zastaveno</h2><p>'.h($message).'</p><p>Při chybě během potvrzení transakce může být výsledek nejistý; před dalším pokusem zkontrolujte HR.</p>';
    }
    if ($import) {
        cb_admin_chyba_audit(static function () use ($success): void {
            cb_user_akce_zapis(['id_user_akce_typ'=>14,'modul'=>'administrace','objekt'=>'hr_import_smlouvy','pole'=>'spusteni','vysledek'=>$success ? 1 : 0,'zdroj'=>'administrace']);
        });
    }
    echo '<p><a href="'.h(cb_root_url('index.php?m=administrace&page=spousteni_scriptu')).'">Zpět do administrace</a></p></body></html>';
    exit;
}
