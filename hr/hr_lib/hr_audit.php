<?php
declare(strict_types=1);

/* Účel: Eviduje skutečné změny HR do společného logu IS. Audit a formulář se potvrzují společně;
 * vnitřní transakce handlerů se při auditované akci připojí k transakci celého formuláře.
 * Hesla, tokeny, obsah souborů ani neověřený obsah POST se do auditu nekopírují.
 */
require_once __DIR__ . '/../hr_db/hr_audit_snimky.php';

/** Připojí existující transakční blok k auditované akci, mimo ni zachová původní chování. */
function hr_audit_transaction_begin(mysqli $db): bool
{
    if (hr_audit_aktivni($db)) {
        $GLOBALS['hr_audit_context']['depth']++;
        return true;
    }
    return $db->begin_transaction();
}

/** Vnořený handler nesmí potvrdit data dřív, než je připraven jejich audit. */
function hr_audit_transaction_commit(mysqli $db): bool
{
    if (hr_audit_aktivni($db)) {
        if ($GLOBALS['hr_audit_context']['depth'] < 2) throw new LogicException('Chybí začátek vnitřní transakce HR.');
        $GLOBALS['hr_audit_context']['depth']--;
        return true;
    }
    return $db->commit();
}

/** Chyba libovolného dílčího bloku ruší celý formulář, nikoli jen poslední změnu. */
function hr_audit_transaction_rollback(mysqli $db): bool
{
    if (hr_audit_aktivni($db)) {
        $GLOBALS['hr_audit_context']['aborted'] = true;
        $GLOBALS['hr_audit_context']['depth'] = 1;
    }
    return $db->rollback();
}

/** Kontext je vždy omezen na konkrétní spojení a jeden HTTP požadavek. */
function hr_audit_aktivni(mysqli $db): bool
{
    return isset($GLOBALS['hr_audit_context']) && $GLOBALS['hr_audit_context']['db'] === $db;
}

/** Zahájí audit pouze rozpoznané zapisovací akce; neznámá akce se nikdy neodhaduje. */
function hr_audit_zahaj(mysqli $db, string $action, array $input): void
{
    $plan = hr_audit_plan($action, $input);
    if ($plan === null) return;
    $db->begin_transaction();
    $GLOBALS['hr_audit_context'] = $plan + ['db'=>$db, 'depth'=>1, 'aborted'=>false, 'action'=>$action];
    // I neočekávaný konec handleru musí vrátit data a zanechat neúspěšný pokus, ne tichou mezeru.
    register_shutdown_function(static function () use ($db): void {
        if (!hr_audit_aktivni($db)) return;
        try { hr_audit_dokoncit($db,false,'Požadavek HR nebyl dokončen.'); }
        catch (Throwable $e) { error_log('Nedokonceny audit HR: '.$e->getMessage()); }
    });
    try {
        // Zámek osoby oddělí souběžné editace ještě před načtením původních hodnot.
        if ($plan['person'] > 0) {
            $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE id_person=? FOR UPDATE');
            $stmt->bind_param('i', $plan['person']); $stmt->execute(); $stmt->close();
        }
        $GLOBALS['hr_audit_context']['before'] = hr_audit_snimky($db, $plan);
    } catch (Throwable $e) {
        $db->rollback(); unset($GLOBALS['hr_audit_context']);
        throw $e;
    }
}

/** Nová osoba dosud nemá ID v POST; přiřadí se skutečné ID vrácené zápisem. */
function hr_audit_nova_osoba(mysqli $db, int $idPerson): void
{
    if (hr_audit_aktivni($db) && $GLOBALS['hr_audit_context']['action'] === 'hr_zamestnanec_ulozit') {
        $GLOBALS['hr_audit_context']['person'] = $idPerson;
    }
}

/** Porovná konkrétní uložené řádky. Technické časové značky samy nejsou změnou údajů. */
function hr_audit_rozdily(array $before, array $after): array
{
    $events = [];
    $metadata = ['vytvoreno','zadano','zadani','zmena','upraveno','id_user_zadal','id_person_zadal','id_user_zrusil','id_user_uzavrel','zadal'];
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
        $oldRows = $before[$table] ?? []; $newRows = $after[$table] ?? [];
        // Některé starší editory vytvoří stejnou verzi znovu; takové Uložit není změna údajů.
        if (hr_audit_verze_bez_zmeny($table, $oldRows, $newRows, $metadata)) continue;
        foreach (array_unique(array_merge(array_keys($oldRows), array_keys($newRows))) as $id) {
            $old = $oldRows[$id] ?? []; $new = $newRows[$id] ?? [];
            $oldDiff = []; $newDiff = [];
            foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
                if (in_array($field, $metadata, true)) continue;
                $a = $old[$field] ?? null; $b = $new[$field] ?? null;
                if ($a !== $b) { $oldDiff[$field] = $a; $newDiff[$field] = $b; }
            }
            if ($oldDiff !== []) $events[] = ['table'=>$table, 'id'=>(int)$id, 'old'=>$oldDiff, 'new'=>$newDiff,
                'row'=>$new ?: $old, 'kind'=>$old === [] ? 'pridani' : ($new === [] ? 'odebrani' : 'zmena')];
        }
    }
    return $events;
}

/** Srovná obsah platných verzí bez jejich technického ID, autora a času vytvoření. */
function hr_audit_verze_bez_zmeny(string $table, array $before, array $after, array $metadata): bool
{
    $keys = ['hr_osobni_udaje'=>'id_osobni_udaje','hr_telefon'=>'id_telefon','hr_email'=>'id_email',
        'hr_adresa'=>'id_adresa','hr_nouzovy_kontakt'=>'id_nouzovy_kontakt','hr_bankovni_ucet'=>'id_bankovni_ucet'];
    if (!isset($keys[$table])) return false;
    $normalize = static function (array $rows) use ($metadata,$keys,$table): array {
        $values = [];
        foreach ($rows as $row) {
            if ((int)($row['platny'] ?? 1) !== 1) continue;
            foreach (array_merge($metadata,[$keys[$table],'platny','zruseno']) as $key) unset($row[$key]);
            ksort($row); $values[] = json_encode($row,JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        sort($values); return $values;
    };
    return $normalize($before) === $normalize($after);
}

/** Zapíše autora z přihlášené relace na témže spojení jako měněná data. */
function hr_audit_zapis(mysqli $db, array $context, ?array $event, bool $success, string $message): void
{
    $actor = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    if ($actor <= 0) throw new RuntimeException('Audit HR vyžaduje přihlášeného autora.');
    $person = (int)($context['person'] ?? 0);
    if ($person === 0 && ($event['table'] ?? '') === 'hr_pristupovy_profil') $person = (int)($event['row']['id_person'] ?? 0);
    $object = $person > 0 ? 'hr_person' : ($event['table'] ?? $context['object']);
    $id = $person > 0 ? $person : (int)($event['id'] ?? $context['id']);
    $field = $event['table'] ?? $context['action'];
    $old = $event === null ? null : json_encode($event['old'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $new = $event === null ? null : json_encode($event['new'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $description = $context['label'];
    if ($event !== null) $description = hr_audit_popis_udalosti($db, $event, $description);
    $detail = ['zdroj'=>'hr_audit', 'akce'=>$context['action'], 'popis'=>$description, 'id_person'=>$person ?: null,
        'puvod'=>!empty($context['automatic']) ? 'automaticky' : 'uzivatel',
        'tabulka'=>$event['table'] ?? null, 'id_zaznamu'=>$event['id'] ?? null, 'udalost'=>$event['kind'] ?? null,
        'platnost_od'=>$event['row']['platnost_od'] ?? $event['row']['datum_nastupu'] ?? $context['od'],
        'platnost_do'=>$event['row']['platnost_do'] ?? $event['row']['datum_ukonceni'] ?? null];
    if (function_exists('cb_prebrani_prav_profil') && ($profile = cb_prebrani_prav_profil($actor)) !== null) {
        $detail['prebrani_prav_id_user'] = (int)$profile['id_user_zdroj'];
    }
    $json = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $result = $success ? 1 : 0; $error = $success ? null : mb_substr($message,0,255,'UTF-8');
    $login = (int)($_SESSION['cb_id_login'] ?? 0) ?: null;
    $stmt = $db->prepare('INSERT INTO user_akce_new (id_user,id_login,id_modul,id_user_akce_typ,objekt,id_objektu,pole,hodnota_old,hodnota_new,detail_json,vysledek,err_msg) VALUES (?,?,3,14,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('iisissssis', $actor, $login, $object, $id, $field, $old, $new, $json, $result, $error);
    $stmt->execute(); $stmt->close();
}

/** Potvrdí změny až po úspěšném auditu; při selhání auditu vrátí zpět i data. */
function hr_audit_dokoncit(mysqli $db, bool $success, string $message): void
{
    if (!hr_audit_aktivni($db)) return;
    $context = $GLOBALS['hr_audit_context'];
    try {
        if (!$success || $context['aborted']) {
            $db->rollback();
            hr_audit_zapis($db, $context, null, false, $message);
            if ($success) throw new RuntimeException('Transakce HR byla zrušena.');
            return;
        }
        if ($context['depth'] !== 1) throw new LogicException('Nedokončená transakce HR.');
        $after = hr_audit_snimky($db, $context);
        $events=$context['action']==='hr_zamestnanec_foto_ulozit'
            ? hr_audit_foto_udalosti($context['before'],$after) : hr_audit_rozdily($context['before'], $after);
        foreach ($events as $event) {
            hr_audit_zapis($db, $context, $event, true, $message);
        }
        if (!$db->commit()) throw new RuntimeException('Potvrzení transakce HR selhalo.');
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        unset($GLOBALS['hr_audit_context']);
    }
}

/** Jediný odchod formulářů HR; původní společná odpověď a návratová URL zůstávají zachovány. */
function hr_audit_form_finish(string $fallback, bool $success, string $message, array $input = [], array $errors = []): never
{
    $db = $GLOBALS['hr_audit_context']['db'] ?? null;
    if ($db instanceof mysqli) {
        try { hr_audit_dokoncit($db, $success, $message); }
        catch (Throwable $e) {
            error_log('HR audit: ' . $e->getMessage());
            $success = false;
            $message = 'Změny nebyly uloženy: nepodařilo se dokončit evidenci změn HR.';
        }
    }
    cb_form_finish($fallback, $success, $message, $input, $errors);
}
