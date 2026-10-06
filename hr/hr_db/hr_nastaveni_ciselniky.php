<?php
declare(strict_types=1);

/* Spravuje ciselniky pod pravem 319, jednotlive i davkove v jedne transakci, zachovava historii a role. */

// Admina overujeme podle aktualni DB role, nikoli podle individualniho opravneni.
function hr_ciselnik_je_admin(mysqli $db): bool
{
    $idUser = hr_current_user_id();
    $stmt = $db->prepare('SELECT 1 FROM hr_person p JOIN hr_pristupovy_profil r ON r.id_person=p.id_person WHERE p.id_user=? AND p.aktivni=1 AND r.id_role=1 LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $admin;
}

// Spolecna kontrola plati i pro primy POST a pridelovani funkce zamestnanci.
function hr_funkce_roli_vyzaduj(mysqli $db, int $idRole): void
{
    if ($idRole === 1 && !hr_ciselnik_je_admin($db)) {
        throw new CbUserVisibleException('Funkci s rolí Admin může přidělovat nebo upravovat pouze Admin.');
    }
}

// Ciselnik roli obsahuje pouze aktivni role; Admin je vyhrazen existujicimu Adminovi.
function hr_ciselnik_role(mysqli $db): array
{
    $admin = hr_ciselnik_je_admin($db);
    $rows = [];
    $result = $db->query('SELECT id_role, role FROM cis_role WHERE aktivni=1 ORDER BY id_role');
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['id_role'] !== 1 || $admin) $rows[] = $row;
    }
    return $rows;
}

// Systemove a migracni polozky nejsou bezne editovatelne ciselniky.
function hr_ciselnik_polozky(mysqli $db, string $typ): array
{
    if ($typ === 'slot') {
        return $db->query('SELECT id_slot AS id, slot AS nazev, aktivni FROM cis_slot WHERE id_slot NOT IN (0,9) ORDER BY aktivni DESC, slot')->fetch_all(MYSQLI_ASSOC);
    }
    $table = cb_hr_schema_table($db, 'cis_funkce');
    $pk = cb_hr_cis_funkce_pk($db);
    return $db->query("SELECT f.{$pk} AS id, f.nazev, f.aktivni, f.id_role, r.role FROM {$table} f JOIN cis_role r ON r.id_role=f.id_role WHERE f.{$pk} NOT IN (1,5,6) ORDER BY f.aktivni DESC, f.nazev")->fetch_all(MYSQLI_ASSOC);
}

// Zamykani ciselniku zabrani soubeznemu prideleni stejneho rucne vedeneho ID.
function hr_ciselnik_ulozit(mysqli $db, string $typ, int $id, string $nazev, bool $aktivni, int $idRole, bool $ownTransaction = true): array
{
    if (!cb_hr_nastaveni_ma_pravo()) throw new CbUserVisibleException('Nemáte právo 319 pro správu slotů a funkcí.');
    if (!in_array($typ, ['slot','funkce'], true)) throw new CbUserVisibleException('Neplatný číselník.');
    $nazev = trim($nazev);
    // Nova polozka je vzdy aktivni, bez ohledu na pripadny podvrzeny checkbox.
    if ($id === 0) $aktivni = true;
    if ($nazev === '' || mb_strlen($nazev, 'UTF-8') > ($typ === 'slot' ? 100 : 80)) throw new CbUserVisibleException('Zadejte platný název položky.');
    $table = $typ === 'slot' ? 'cis_slot' : cb_hr_schema_table($db, 'cis_funkce');
    $pk = $typ === 'slot' ? 'id_slot' : cb_hr_cis_funkce_pk($db);
    $nameColumn = $typ === 'slot' ? 'slot' : 'nazev';
    $reserved = $typ === 'slot' ? [0,9] : [1,5,6];
    if ($id < 0 || ($id > 0 && in_array($id, $reserved, true))) throw new CbUserVisibleException('Historickou nebo systémovou položku nelze upravovat.');
    if ($ownTransaction) {
        $lock = $db->query("SELECT GET_LOCK('hr_ciselniky_sprava',5)")->fetch_row();
        if ((int)($lock[0] ?? 0) !== 1) throw new CbUserVisibleException('Číselník právě upravuje jiný uživatel. Opakujte uložení.');
    }
    try {
        if ($ownTransaction) $db->begin_transaction();
        $old = null;
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$pk}=? FOR UPDATE");
            $stmt->bind_param('i', $id); $stmt->execute(); $old = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$old) throw new CbUserVisibleException('Položka nebyla nalezena.');
        }
        if ($typ === 'funkce') {
            hr_funkce_roli_vyzaduj($db, $idRole);
            if ($old) hr_funkce_roli_vyzaduj($db, (int)$old['id_role']);
            $stmt = $db->prepare('SELECT 1 FROM cis_role WHERE id_role=? AND aktivni=1');
            $stmt->bind_param('i', $idRole); $stmt->execute(); $exists = $stmt->get_result()->fetch_row(); $stmt->close();
            if (!$exists) throw new CbUserVisibleException('Vyberte aktivní přístupovou roli.');
        }
        // Opakovane ulozeni nezmenene polozky nevytvari zapis ani auditni sum.
        if ($old && $nazev === (string)$old[$nameColumn] && (int)$aktivni === (int)$old['aktivni']
            && ($typ === 'slot' || $idRole === (int)$old['id_role'])) {
            if ($ownTransaction) $db->commit();
            return ['id'=>$id,'nazev'=>$nazev,'aktivni'=>(int)$aktivni,'id_role'=>$idRole];
        }
        $stmt = $db->prepare("SELECT 1 FROM {$table} WHERE {$nameColumn}=? AND {$pk}<>? LIMIT 1");
        $stmt->bind_param('si', $nazev, $id); $stmt->execute(); $duplicate = $stmt->get_result()->fetch_row(); $stmt->close();
        if ($duplicate) throw new CbUserVisibleException('Položka s tímto názvem již existuje.');
        $stav = (int)$aktivni;
        if ($old) {
            $sql = "UPDATE {$table} SET {$nameColumn}=?, aktivni=?" . ($typ === 'funkce' ? ', id_role=?' : '') . " WHERE {$pk}=?";
            $stmt = $db->prepare($sql);
            if ($typ === 'funkce') $stmt->bind_param('siii', $nazev, $stav, $idRole, $id);
            else $stmt->bind_param('sii', $nazev, $stav, $id);
        } else {
            $used = array_column($db->query("SELECT {$pk} FROM {$table}")->fetch_all(MYSQLI_ASSOC), $pk);
            // Sloty jsou pouzivany i v bitove masce VD, proto zustava rozsah 1 az 62.
            $id = $typ === 'slot' ? 1 : max(12, ...array_map('intval', $used)) + 1;
            if ($typ === 'slot') {
                while (in_array($id, $used) || in_array($id, $reserved, true)) $id++;
                if ($id > 62) throw new CbUserVisibleException('Limit 62 pracovních slotů je vyčerpán.');
                $stmt = $db->prepare('INSERT INTO cis_slot (id_slot,slot,aktivni) VALUES (?,?,?)');
                $stmt->bind_param('isi', $id, $nazev, $stav);
            } else {
                $kod = 'funkce_' . $id;
                $stmt = $db->prepare("INSERT INTO {$table} ({$pk},kod,nazev,id_role,aktivni) VALUES (?,?,?,?,?)");
                $stmt->bind_param('issii', $id, $kod, $nazev, $idRole, $stav);
            }
        }
        $stmt->execute(); $stmt->close();
        // Zmena role pouzite funkce musi zmenit i dnesni odvozene profily v teze transakci.
        if ($typ === 'funkce' && $old && (int)$old['id_role'] !== $idRole) {
            $functionTable = cb_hr_schema_table($db, 'funkce');
            $fk = cb_hr_funkce_fk($db);
            $stmt = $db->prepare("SELECT DISTINCT id_person FROM {$functionTable} WHERE {$fk}=? AND platny=1 AND (platnost_od IS NULL OR platnost_od<=CURDATE()) AND (platnost_do IS NULL OR platnost_do>=CURDATE())");
            $stmt->bind_param('i', $id); $stmt->execute(); $people = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $actor = hr_current_person_id($db);
            foreach ($people as $person) hr_pristupovy_profil_synchronizovat($db, (int)$person['id_person'], $actor);
        }
        // Zaznam autora a puvodnich/novych hodnot patri do stejne transakce jako ciselnik.
        $actorUser = hr_current_user_id();
        $oldJson = $old ? json_encode($old, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null;
        $newJson = json_encode(['nazev'=>$nazev,'aktivni'=>$stav,'id_role'=>$typ === 'funkce' ? $idRole : null], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $db->prepare("INSERT INTO user_akce_new (id_user,id_modul,id_user_akce_typ,objekt,id_objektu,hodnota_old,hodnota_new,detail_json) VALUES (?,3,14,?,?,?,?, '{\"zdroj\":\"hr_ciselniky\"}')");
        $stmt->bind_param('isiss', $actorUser, $table, $id, $oldJson, $newJson);
        $stmt->execute(); $stmt->close();
        if ($ownTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $db->rollback();
        throw $e;
    } finally {
        if ($ownTransaction) $db->query("SELECT RELEASE_LOCK('hr_ciselniky_sprava')");
    }
    return ['id'=>$id,'nazev'=>$nazev,'aktivni'=>$stav,'id_role'=>$idRole];
}

// Cela sada zmen jednoho bloku se potvrdi najednou; chyba vrati zpet i drivejsi radky.
function hr_ciselnik_davka_ulozit(mysqli $db, string $typ, array $rows): array
{
    if (!cb_hr_nastaveni_ma_pravo()) throw new CbUserVisibleException('Nemáte právo 319 pro správu slotů a funkcí.');
    if (!in_array($typ, ['slot','funkce'], true) || count($rows) > 1000) throw new CbUserVisibleException('Neplatná sada změn číselníku.');
    $lock = $db->query("SELECT GET_LOCK('hr_ciselniky_sprava',5)")->fetch_row();
    if ((int)($lock[0] ?? 0) !== 1) throw new CbUserVisibleException('Číselník právě upravuje jiný uživatel. Opakujte uložení.');
    try {
        $db->begin_transaction();
        $saved = []; $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row) || (int)($row['id'] ?? 0) <= 0 || isset($seen[(int)$row['id']])) throw new CbUserVisibleException('Neplatná nebo opakovaná položka.');
            $id = (int)$row['id']; $seen[$id] = true;
            $saved[] = hr_ciselnik_ulozit($db, $typ, $id, (string)($row['nazev'] ?? ''), (int)($row['aktivni'] ?? 0) === 1, (int)($row['id_role'] ?? 0), false);
        }
        $db->commit();
        return $saved;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    } finally {
        $db->query("SELECT RELEASE_LOCK('hr_ciselniky_sprava')");
    }
}
