<?php
declare(strict_types=1);
/* Účel: Ověří a atomicky provede plánování i vyřízení žádosti; po publikaci předá oznámení k odeslání. */

/** Dispatcher je jedinou transakční hranicí pro změny rozpisu a zámku. */
function cb_smeny_planovani_akce(mysqli $db, array $branches, array $weeks): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !str_starts_with((string)($_POST['action'] ?? ''),'smeny_plan_')) return;
    $idBranch = (int)($_POST['id_pob'] ?? 0);
    $weekIndex = (int)($_POST['week'] ?? -1);
    $redirect = cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$idBranch.'&week='.max(0,$weekIndex));
    $inTransaction = false;
    try {
        $action = (string)$_POST['action'];
        // Publikující může převzít zámek a zveřejnit hotový plán i bez práva měnit směny.
        $publishOnly = cb_smeny_planovani_publikuje() && in_array($action,['smeny_plan_zamek','smeny_plan_uvolnit','smeny_plan_obnovit_zamek','smeny_plan_zverejnit'],true);
        if ((!cb_smeny_planovani_edituje() && !$publishOnly) || !cb_crf_platny()) throw new CbUserVisibleException('Nemáte právo provést tuto akci nebo vypršela platnost stránky.');
        if (!in_array($action,['smeny_plan_zamek','smeny_plan_uvolnit','smeny_plan_obnovit_zamek','smeny_plan_zalozit','smeny_plan_sablona_obnovit','smeny_plan_priradit','smeny_plan_odebrat','smeny_plan_pripraven','smeny_plan_upravit','smeny_plan_zverejnit','smeny_plan_zmenit','smeny_plan_zruseni_schvalit','smeny_plan_zruseni_zamitnout'],true)) throw new CbUserVisibleException('Neznámá akce plánování.');
        if (!isset($branches[$idBranch],$weeks[$weekIndex]) || (string)($_POST['tyden_od'] ?? '') !== (string)$weeks[$weekIndex]['start_day']) throw new CbUserVisibleException('Pobočka nebo týden už nejsou aktuální. Obnovte stránku.');
        $week = $weeks[$weekIndex];
        $idPerson = cb_smeny_sablony_id_person($db);
        if ($idPerson <= 0) throw new CbUserVisibleException('Účet není propojený s osobou v HR.');
        $stmt = cb_smeny_planovani_sql($db, 'SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1', 'i',[$idPerson]);
        $activePlanner = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($activePlanner === null) throw new CbUserVisibleException('Plánovač již není aktivní v HR.');
        if ($action !== 'smeny_plan_uvolnit' && new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')) < $week['deadline']) throw new CbUserVisibleException('Plánování se otevře po uzávěrce požadavků '.$week['deadline']->format('j. n. Y \v H:i').'.');
        // Po čekání na zámek osoby musí následující čtení vidět poslední potvrzená data.
        $db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->begin_transaction(); $inTransaction = true;
        $stmt = cb_smeny_planovani_sql($db, 'SELECT id_pob FROM pobocka WHERE id_pob=? AND aktivni=1 FOR UPDATE', 'i',[$idBranch]);
        $activeBranch = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($activeBranch === null) throw new CbUserVisibleException('Pobočka již není aktivní.');
        $newToken = null;
        if ($action === 'smeny_plan_zamek') {
            $newToken = cb_smeny_planovani_zamek_ziskat($db,$idBranch,$idPerson);
        } else {
            cb_smeny_planovani_zamek_overit($db,$idBranch,$idPerson,(string)($_POST['zamek_token'] ?? ''));
            if ($action === 'smeny_plan_uvolnit') {
                cb_smeny_planovani_sql($db, 'DELETE FROM smeny_zamek WHERE id_pob=?', 'i',[$idBranch])->close();
            } elseif ($action !== 'smeny_plan_obnovit_zamek') {
                $plan = cb_smeny_planovani_nacist($db,$idBranch,(string)$week['start_day']);
                if ($action === 'smeny_plan_zalozit') {
                    if ($plan !== null) throw new CbUserVisibleException('Týden už byl založen. Obnovte stránku.');
                    cb_smeny_planovani_zalozit($db,$branches[$idBranch],$week,(int)($_POST['id_smeny_sablona'] ?? 0),$idPerson);
                } else {
                    if ($plan === null || (int)$plan['id_smeny_rozpis'] !== (int)($_POST['id_smeny_rozpis'] ?? 0) || (int)$plan['verze'] !== (int)($_POST['verze'] ?? 0) || $plan['stav'] !== (string)($_POST['stav'] ?? '')) throw new CbUserVisibleException('Stav rozpisu se změnil. Obnovte stránku.');
                    switch ($action) {
                        case 'smeny_plan_zruseni_schvalit':
                        case 'smeny_plan_zruseni_zamitnout':
                            cb_smeny_zruseni_vyridit($db,$plan,$idPerson,$_POST); break;
                        case 'smeny_plan_zverejnit':
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
                        case 'smeny_plan_pripraven':
                            if ($plan['stav'] !== 'rozpracovany') throw new CbUserVisibleException('Týden není rozpracovaný.');
                            cb_smeny_planovani_sql($db, 'UPDATE smeny_rozpis SET pripraveno=1 WHERE id_smeny_rozpis=?', 'i',[(int)$plan['id_smeny_rozpis']])->close();
                            cb_smeny_audit_zapis($db,$idPerson,'pripraveno','rozpis',(int)$plan['id_smeny_rozpis'],null,['verze'=>$plan['verze']]); break;
                    }
                }
            }
        }
        $db->commit(); $inTransaction = false;
        if ($newToken !== null) $_SESSION['smeny_zamky'][$idBranch] = $newToken;
        if ($action === 'smeny_plan_uvolnit') unset($_SESSION['smeny_zamky'][$idBranch]);
        $message = match ($action) {
            'smeny_plan_zruseni_schvalit' => 'Směna byla odebrána z pracovního plánu. Zrušení dokončíte zveřejněním změn.',
            'smeny_plan_zruseni_zamitnout' => 'Žádost byla zamítnuta. Zaměstnanec vidí výsledek v Mé směny.',
            'smeny_plan_zverejnit' => 'Rozpis byl zveřejněn. Zaměstnanci mají oznámení v Mé směny.',
            'smeny_plan_zamek' => 'Pobočka je zamčená pro vaše plánování na 15 minut.',
            'smeny_plan_uvolnit' => 'Pobočka byla uvolněna pro ostatní plánovače.',
            'smeny_plan_obnovit_zamek' => 'Zámek byl prodloužen o 15 minut.',
            'smeny_plan_pripraven' => 'Týden je připravený ke zveřejnění. Zaměstnancům se zatím nic nemění.',
            default => 'Pracovní rozpis byl uložen. Zaměstnancům se zatím nic nemění.',
        };
        // Publikace už je potvrzená v DB. Selhání sítě nesmí vypadat jako neuložený rozpis.
        if ($action === 'smeny_plan_zverejnit') {
            try {
                require_once __DIR__.'/sm_push_publikace.php';
                $failed = cb_smeny_push_publikace($db, (int)$plan['id_smeny_rozpis'], (int)$plan['pracovni_verze']);
                $message .= $failed > 0 ? ' Některé push notifikace se nepodařilo doručit.' : ' Push byly odeslány na dostupná zařízení.';
            } catch (Throwable $e) {
                error_log('Směny: doručení po publikaci #'.$plan['id_smeny_rozpis'].': '.$e->getMessage());
                $message .= ' Odeslání push se nepodařilo dokončit.';
            }
        }
        cb_smeny_sablony_flash_ulozit('success',$message);
    } catch (Throwable $e) {
        if ($inTransaction) $db->rollback();
        cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Plánování směn','table'=>'smeny_rozpis']));
    }
    header('Location: '.$redirect); exit;
}
