<?php
declare(strict_types=1);
/* Účel: Atomicky mění rozpis pod zámkem pobočky; dokončení plánování jej rovnou zveřejní a po potvrzení transakce odešle existující oznámení. */

/** Dispatcher je jedinou transakční hranicí pro změny rozpisu a zámku. */
function cb_smeny_planovani_akce(mysqli $db, array $branches, array $weeks): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !str_starts_with((string)($_POST['action'] ?? ''),'smeny_plan_')) return;
    // Životní cyklus stránky vrací JSON a neprovádí žádné změny rozpisu.
    if (cb_smeny_planovani_zamek_pozadavek($db,$branches)) exit;
    $idBranch = (int)($_POST['id_pob'] ?? 0);
    $weekIndex = (int)($_POST['week'] ?? -1);
    $redirect = cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$idBranch.'&week='.max(0,$weekIndex));
    $inTransaction = false;
    try {
        $action = (string)$_POST['action'];
        if (!cb_smeny_planovani_edituje() || !cb_crf_platny()) throw new CbUserVisibleException('Nemáte právo provést tuto akci nebo vypršela platnost stránky.');
        if (!in_array($action,['smeny_plan_nastaveni','smeny_plan_slot_pridat','smeny_plan_slot_odebrat','smeny_plan_zamek','smeny_plan_uvolnit','smeny_plan_obnovit_zamek','smeny_plan_zalozit','smeny_plan_sablona_obnovit','smeny_plan_priradit','smeny_plan_odebrat','smeny_plan_pripraven','smeny_plan_upravit','smeny_plan_zverejnit','smeny_plan_zmenit'],true)) throw new CbUserVisibleException('Neznámá akce plánování.');
        if (!isset($branches[$idBranch],$weeks[$weekIndex]) || (string)($_POST['tyden_od'] ?? '') !== (string)$weeks[$weekIndex]['start_day']) throw new CbUserVisibleException('Pobočka nebo týden už nejsou aktuální. Obnovte stránku.');
        $week = $weeks[$weekIndex];
        $selectedDay=array_search((string)($_POST['datum'] ?? ''),array_column($week['days'],'date'),true);
        $redirect.='&den_index='.($selectedDay===false ? max(0,min(6,(int)($_POST['den_index'] ?? 0))) : $selectedDay);
        $idPerson = cb_smeny_sablony_id_person($db);
        if ($idPerson <= 0) throw new CbUserVisibleException('Účet není propojený s osobou v HR.');
        $stmt = cb_smeny_planovani_sql($db, 'SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1', 'i',[$idPerson]);
        $activePlanner = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($activePlanner === null) throw new CbUserVisibleException('Plánovač již není aktivní v HR.');
        if(!cb_smeny_planovani_schema($db)) throw new CbUserVisibleException('Plánování vyžaduje připravenou aktualizaci databáze pro nastavení a sloupce rozpisu.');
        // Po čekání na zámek osoby musí následující čtení vidět poslední potvrzená data.
        $db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->begin_transaction(); $inTransaction = true;
        $stmt = cb_smeny_planovani_sql($db, 'SELECT id_pob FROM pobocka WHERE id_pob=? AND aktivni=1 FOR UPDATE', 'i',[$idBranch]);
        $activeBranch = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($activeBranch === null) throw new CbUserVisibleException('Pobočka již není aktivní.');
        $plan = cb_smeny_planovani_nacist($db,$idBranch,(string)$week['start_day']);
        if (!in_array($action,['smeny_plan_nastaveni','smeny_plan_uvolnit'],true) && empty($plan['planovat_kdykoliv']) && new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')) < $week['deadline']) throw new CbUserVisibleException('Před uzávěrkou zapněte volbu Plánovat kdykoliv.');
        $newToken = null;
        if ($action === 'smeny_plan_nastaveni') {
            // Nastavení lze zapnout i před prvním převzetím; aktivní cizí zámek ale chrání také nastavení.
            $lock=cb_smeny_planovani_zamek_nacist($db,$idBranch);
            if($lock!==null) cb_smeny_planovani_zamek_overit($db,$idBranch,$idPerson,(string)($_POST['zamek_token'] ?? ''));
            if($plan===null) {
                cb_smeny_planovani_zalozit($db,$branches[$idBranch],$week,0,$idPerson);
                $plan=cb_smeny_planovani_nacist($db,$idBranch,(string)$week['start_day']);
            } elseif((int)$plan['id_smeny_rozpis']!==(int)($_POST['id_smeny_rozpis'] ?? 0) || (int)$plan['verze']!==(int)($_POST['verze'] ?? 0) || $plan['stav']!==(string)($_POST['stav'] ?? '')) throw new CbUserVisibleException('Rozpis se mezitím změnil. Obnovte stránku.');
            if(!hash_equals(hash('sha256',(int)!empty($plan['planovat_kdykoliv']).':'.(int)!empty($plan['bez_ohledu_na_pobocku'])),(string)($_POST['nastaveni_otisk'] ?? ''))) throw new CbUserVisibleException('Nastavení rozpisu se mezitím změnilo. Obnovte stránku.');
            $values=['planovat_kdykoliv'=>(int)!empty($_POST['planovat_kdykoliv']),'bez_ohledu_na_pobocku'=>(int)!empty($_POST['bez_ohledu_na_pobocku'])];
            cb_smeny_planovani_sql($db,'UPDATE smeny_rozpis SET planovat_kdykoliv=?,bez_ohledu_na_pobocku=? WHERE id_smeny_rozpis=?','iii',[$values['planovat_kdykoliv'],$values['bez_ohledu_na_pobocku'],(int)$plan['id_smeny_rozpis']])->close();
            cb_smeny_audit_zapis($db,$idPerson,'nastaveni_planovani','rozpis',(int)$plan['id_smeny_rozpis'],array_intersect_key($plan,$values),$values);
        } elseif ($action === 'smeny_plan_zamek') {
            $newToken = cb_smeny_planovani_zamek_ziskat($db,$idBranch,$idPerson);
            cb_smeny_audit_zapis($db,$idPerson,'zamek_ziskan','pobocka',$idBranch,null,['tyden_od'=>$week['start_day']]);
        } else {
            cb_smeny_planovani_zamek_overit($db,$idBranch,$idPerson,(string)($_POST['zamek_token'] ?? ''));
            if ($action === 'smeny_plan_uvolnit') {
                cb_smeny_planovani_sql($db, 'DELETE FROM smeny_zamek WHERE id_pob=?', 'i',[$idBranch])->close();
                cb_smeny_audit_zapis($db,$idPerson,'zamek_uvolnen','pobocka',$idBranch,null,null);
            } elseif ($action !== 'smeny_plan_obnovit_zamek') {
                $plan = cb_smeny_planovani_nacist($db,$idBranch,(string)$week['start_day']);
                if ($action === 'smeny_plan_zalozit') {
                    if ($plan !== null) throw new CbUserVisibleException('Týden už byl založen. Obnovte stránku.');
                    cb_smeny_planovani_zalozit($db,$branches[$idBranch],$week,(int)($_POST['id_smeny_sablona'] ?? 0),$idPerson);
                } else {
                    if ($plan === null || (int)$plan['id_smeny_rozpis'] !== (int)($_POST['id_smeny_rozpis'] ?? 0) || (int)$plan['verze'] !== (int)($_POST['verze'] ?? 0) || $plan['stav'] !== (string)($_POST['stav'] ?? '')) throw new CbUserVisibleException('Stav rozpisu se změnil. Obnovte stránku.');
                    cb_smeny_planovani_sloty_pripravit($db,$plan);
                    switch ($action) {
                        case 'smeny_plan_slot_pridat':
                        case 'smeny_plan_slot_odebrat':
                            cb_smeny_planovani_slot_zmenit($db,$plan,$branches[$idBranch],$week,$_POST,$idPerson); break;
                        case 'smeny_plan_zverejnit':
                        case 'smeny_plan_pripraven':
                            cb_smeny_publikace_zverejnit($db,$plan,$idPerson); break;
                        case 'smeny_plan_sablona_obnovit':
                            cb_smeny_planovani_sablonu_obnovit($db,(int)$plan['id_smeny_rozpis'],$branches[$idBranch],$week,(int)($_POST['id_smeny_sablona'] ?? 0),$idPerson); break;
                        case 'smeny_plan_zmenit':
                        case 'smeny_plan_priradit':
                            cb_smeny_planovani_priradit($db,$plan,$branches[$idBranch],$week,$_POST,$idPerson); break;
                        case 'smeny_plan_odebrat':
                            cb_smeny_planovani_odebrat($db,$plan,(int)($_POST['id_smeny_smena'] ?? 0),$idPerson); break;
                        case 'smeny_plan_upravit':
                            cb_smeny_planovani_upravit($db,$plan,$idPerson); break;
                    }
                }
            }
        }
        $db->commit(); $inTransaction = false;
        if ($newToken !== null) $_SESSION['smeny_zamky'][$idBranch] = $newToken;
        if ($action === 'smeny_plan_uvolnit') unset($_SESSION['smeny_zamky'][$idBranch]);
        $message = match ($action) {
            'smeny_plan_zverejnit' => 'Rozpis byl zveřejněn. Zaměstnanci mají oznámení v Mé směny.',
            'smeny_plan_zamek' => 'Pobočka je zamčená pro vaše plánování na 20 minut.',
            'smeny_plan_uvolnit' => 'Pobočka byla uvolněna pro ostatní plánovače.',
            'smeny_plan_obnovit_zamek' => 'Zámek byl prodloužen o 20 minut.',
            'smeny_plan_pripraven' => 'Rozpis byl zveřejněn. Zaměstnanci mají oznámení v Mé směny.',
            'smeny_plan_sablona_obnovit' => 'Barevný podklad byl změněn. Naplánované směny zůstaly zachovány.',
            default => null,
        };
        // Publikace už je potvrzená v DB. Selhání sítě nesmí vypadat jako neuložený rozpis.
        if (in_array($action,['smeny_plan_zverejnit','smeny_plan_pripraven'],true)) {
            try {
                require_once __DIR__.'/sm_push_publikace.php';
                $failed = cb_smeny_push_publikace($db, (int)$plan['id_smeny_rozpis'], (int)$plan['pracovni_verze']);
                $message .= $failed > 0 ? ' Některé push notifikace se nepodařilo doručit.' : ' Push byly odeslány na dostupná zařízení.';
            } catch (Throwable $e) {
                error_log('Směny: doručení po publikaci #'.$plan['id_smeny_rozpis'].': '.$e->getMessage());
                $message .= ' Odeslání push se nepodařilo dokončit.';
            }
        }
        // Běžné uložení nepotřebuje hlášku; chyby i významné stavové změny zůstávají viditelné.
        if($message!==null) cb_smeny_sablony_flash_ulozit('success',$message);
    } catch (Throwable $e) {
        if ($inTransaction) $db->rollback();
        cb_smeny_aktivita_chyba($e);
        // Ztráta zámku ukončí editaci bez hlášky, která by zůstala čekat na další návštěvu.
        if ($e instanceof CbUserVisibleException && $e->getCode() === 409) {
            $redirect = cb_root_url('index.php?m=smeny&page=prehled');
            unset($_SESSION['cb_smeny_sablony_flash']);
        } else {
            cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Plánování směn','table'=>'smeny_rozpis']));
        }
    }
    header('Location: '.$redirect); exit;
}
