<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Účel: Spravuje výhradní zámek plánování pobočky. Hlídá aktivitu, bezpečné uvolnění a expiraci; změny vlastnictví audituje bez logování každého heartbeat. */

/** Provede parametrizované SQL; transakci řídí dispatcher celé uživatelské akce. */
function cb_smeny_planovani_sql(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

/** Vrátí pouze aktuální zámek podle času DB, bez jeho prodlužování při GET. */
function cb_smeny_planovani_zamek_nacist(mysqli $db, int $idBranch): ?array
{
    $stmt = cb_smeny_planovani_sql($db, 'SELECT z.id_person,z.token,z.platny_do,TIMESTAMPDIFF(SECOND,NOW(),z.platny_do) zbyva_sekund,COALESCE(NULLIF(' . cb_osoba_jmeno_sql('z.id_person') . ',""),CONCAT("Osoba #",z.id_person)) jmeno FROM smeny_zamek z WHERE z.id_pob=? AND z.platny_do>NOW()', 'i', [$idBranch]);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row;
}

/** Zámek vlastní přihlášená relace a osoba; samotné stejné ID osoby nestačí. */
function cb_smeny_planovani_zamek_vlastni(?array $lock, int $idBranch, int $idPerson): bool
{
    $token = (string)($_SESSION['smeny_zamky'][$idBranch] ?? '');
    return $lock !== null && (int)$lock['id_person'] === $idPerson && $token !== '' && hash_equals((string)$lock['token'], $token);
}

/** Volat až po zamčení rodičovské pobočky, které serializuje i dosud neexistující zámek. */
function cb_smeny_planovani_zamek_ziskat(mysqli $db, int $idBranch, int $idPerson): string
{
    $lock = cb_smeny_planovani_zamek_nacist($db, $idBranch);
    if ($lock !== null && !cb_smeny_planovani_zamek_vlastni($lock, $idBranch, $idPerson)) {
        throw new CbUserVisibleException('Pobočku právě plánuje jiný uživatel. Zkuste to po uvolnění zámku nebo po ' . $lock['platny_do'] . '.');
    }
    $token = $lock === null ? bin2hex(random_bytes(16)) : (string)$lock['token'];
    cb_smeny_planovani_sql($db, 'INSERT INTO smeny_zamek (id_pob,id_person,token,platny_do) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL 20 MINUTE)) ON DUPLICATE KEY UPDATE id_person=VALUES(id_person),token=VALUES(token),platny_do=VALUES(platny_do)', 'iis', [$idBranch,$idPerson,$token])->close();
    return $token;
}

/** Každý zápis vyžaduje dosud platný token z formuláře; propadlý nelze tiše převzít. */
function cb_smeny_planovani_zamek_overit(mysqli $db, int $idBranch, int $idPerson, string $token, int $remaining = 1200, bool $renew = true): void
{
    $lock = cb_smeny_planovani_zamek_nacist($db, $idBranch);
    if (!cb_smeny_planovani_zamek_vlastni($lock, $idBranch, $idPerson) || !hash_equals((string)$lock['token'], $token)) {
        // Interní odmítnutí přesměruje na přehled; nezobrazuje se ani neukládá jako uživatelská hláška.
        throw new CbUserVisibleException('Neplatný zámek plánování.',409);
    }
    // Obnova vychází z poslední skutečné aktivity, nikoliv z běhu časovače prohlížeče.
    if ($renew) cb_smeny_planovani_sql($db, 'UPDATE smeny_zamek SET platny_do=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id_pob=?', 'ii', [max(1,min(1200,$remaining)),$idBranch])->close();
}

/** Aktivita a odchod pracují pouze se zámkem vlastněným relací, nikdy nepřevezmou propadlý zámek. */
function cb_smeny_planovani_zamek_pozadavek(mysqli $db, array $branches): bool
{
    $action = (string)($_POST['action'] ?? '');
    if (!in_array($action,['smeny_plan_zamek_aktivita','smeny_plan_zamek_stav','smeny_plan_zamek_odchod'],true)) return false;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $transaction = false;
    try {
        if ((!cb_smeny_planovani_edituje() && !cb_smeny_planovani_publikuje()) || !cb_crf_platny()) {
            http_response_code(403); echo json_encode(['ok'=>false]); return true;
        }
        $branch = (int)($_POST['id_pob'] ?? 0);
        $person = cb_smeny_sablony_id_person($db);
        $token = (string)($_POST['zamek_token'] ?? '');
        if (!isset($branches[$branch]) || $person <= 0 || $token === '' || !hash_equals((string)($_SESSION['smeny_zamky'][$branch] ?? ''),$token)) {
            http_response_code(409); echo json_encode(['ok'=>false]); return true;
        }
        $db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->begin_transaction(); $transaction = true;
        // Stejný rodičovský zámek jako při převzetí brání souběhu odchodu a nového plánovače.
        cb_smeny_planovani_sql($db,'SELECT id_pob FROM pobocka WHERE id_pob=? FOR UPDATE','i',[$branch])->close();
        if ($action === 'smeny_plan_zamek_odchod') {
            // Opožděný odchod nikdy nesmaže novější token ani zámek jiné osoby.
            $removed=cb_smeny_planovani_sql($db,'DELETE FROM smeny_zamek WHERE id_pob=? AND id_person=? AND token=?','iis',[$branch,$person,$token]);
            $changed=$removed->affected_rows; $removed->close();
            if($changed>0) cb_smeny_audit_zapis($db,$person,'zamek_odchod','pobocka',$branch,null,null);
            unset($_SESSION['smeny_zamky'][$branch]);
        } else {
            $active = cb_smeny_planovani_sql($db,'SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1','i',[$person]);
            $isActive = $active->get_result()->fetch_assoc() !== null; $active->close();
            if (!$isActive) throw new CbUserVisibleException('Plánovač již není aktivní v HR.');
            $idle = max(0,(int)($_POST['necinost_ms'] ?? 0));
            if ($idle >= 1200000) throw new CbUserVisibleException('Plánování bylo ukončeno pro nečinnost.');
            cb_smeny_planovani_zamek_overit($db,$branch,$person,$token,1200-(int)ceil($idle/1000),$action === 'smeny_plan_zamek_aktivita');
        }
        $db->commit(); $transaction = false;
        echo json_encode(['ok'=>true]);
    } catch (Throwable $e) {
        if ($transaction) $db->rollback();
        // Úspěšné periodické kontroly nejsou nové uživatelské úkony; skutečné odmítnutí je dohledatelné.
        if(function_exists('cb_smeny_aktivita_chyba')) cb_smeny_aktivita_chyba($e);
        http_response_code($e instanceof CbUserVisibleException ? 409 : 500);
        if (!$e instanceof CbUserVisibleException) error_log('Směny: obsluha zámku: '.$e->getMessage());
        echo json_encode(['ok'=>false]);
    }
    return true;
}
