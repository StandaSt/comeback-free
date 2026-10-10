<?php
/*
 * Ucel souboru: Doplnovaci import schvaleneho planu smlouvy_all.zip bez resetu HR.
 * Pouziva pevne prirazeni, overuje obsah, kopiruje cele originaly a uklada podpisy,
 * rozsahy casti a upozorneni. Samostatne URL ani nacteni souboru import nespousti.
 */
declare(strict_types=1);

// Ocekavane odmitnuti lze zobrazit uzivateli; technicke SQL a souborove chyby nikoli.
class HrSmlouvyImportException extends RuntimeException {}

/** Nacte pevny plan; pri chybejicich podkladech nedovoli improvizovane parovani. */
function hr_smlouvy_manifest(): array
{
    $path = dirname(__DIR__, 3) . '/data/google_data/smlouvy_all_import_manifest.json';
    if (!is_file($path)) {
        throw new HrSmlouvyImportException('Chybí importní plán smlouvy_all_import_manifest.json v data/google_data.');
    }
    $plan = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (($plan['version'] ?? 0) !== 1 || count($plan['documents'] ?? []) !== ($plan['files'] ?? -1)) {
        throw new HrSmlouvyImportException('Importní plán je neúplný. Připravte znovu ověřený plán.');
    }
    $plan['manifest_hash'] = hash_file('sha256', $path);
    return $plan;
}

/** Parametry SQL vzdy predava oddelene od dotazu. */
function hr_smlouvy_query(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** ZIP pouze cte pres Phar; nepotrebuje ZipArchive a nikdy nerozbaluje cizi cesty. */
function hr_smlouvy_archive(array $plan): PharData
{
    $path = dirname(__DIR__, 3) . '/data/google_data/smlouvy_all.zip';
    if (!is_file($path)) {
        throw new HrSmlouvyImportException('Chybí smlouvy_all.zip v data/google_data.');
    }
    if (!hash_equals((string)$plan['zip_sha256'], (string)hash_file('sha256', $path))) {
        throw new HrSmlouvyImportException('ZIP není totožný s kontrolovaným balíkem. Import zastaven.');
    }
    return new PharData($path);
}

/** Nacte pouze jeden predem schvaleny zdroj a overi jeho presny obsah. */
function hr_smlouvy_bytes(PharData $archive, array $row): string
{
    $source = (string)$row['source'];
    if (preg_match('~(?:^|/)\.\.(?:/|$)|[\\\\\x00]~', $source) || !isset($archive[$source])) {
        throw new HrSmlouvyImportException('Chybí schválený zdroj pro osobu ' . $row['id_person'] . '.');
    }
    if ($archive[$source]->getSize() > 64 * 1024 * 1024) {
        throw new HrSmlouvyImportException('Dokument osoby ' . $row['id_person'] . ' překračuje limit 64 MB.');
    }
    $bytes = $archive[$source]->getContent();
    if (!hash_equals((string)$row['sha256'], hash('sha256', $bytes))) {
        throw new HrSmlouvyImportException('Obsah dokumentu osoby ' . $row['id_person'] . ' neodpovídá auditu.');
    }
    return $bytes;
}

/** Najde binarne shodny dokument dane osoby, nikoli dokument jineho zamestnance. */
function hr_smlouvy_existing(mysqli $db, array $row): ?array
{
    $stmt = hr_smlouvy_query($db, 'SELECT ds.relativni_cesta FROM hr_dokument_soubor ds JOIN hr_dokument d ON d.id_dokument=ds.id_dokument AND d.verze=ds.verze WHERE d.id_person=? AND d.id_firma=? AND ds.sha256=? AND d.platny=1 LIMIT 1', [$row['id_person'], $row['id_firma'], $row['sha256']]);
    $existing = $stmt->get_result()->fetch_assoc();
    if (!$existing) {
        return null;
    }
    $base = realpath(dirname(__DIR__, 2) . '/hr/hr_dokumenty');
    $path = realpath(dirname(__DIR__, 2) . '/' . $existing['relativni_cesta']);
    if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)
        || !hash_equals((string)$row['sha256'], (string)hash_file('sha256', $path))) {
        throw new HrSmlouvyImportException('HR eviduje shodný dokument osoby ' . $row['id_person'] . ', ale jeho soubor chybí nebo se liší. Nejdřív opravte tento záznam.');
    }
    return $existing;
}

/** Kontrola vsech zdroju, vazeb a ciselniku pred prvnim zapisem; vraci presne pocty. */
function hr_smlouvy_preview(mysqli $db, ?callable $progress = null): array
{
    $plan = hr_smlouvy_manifest();
    $progress && $progress('Ověřuji importní plán a kontrolní součet ZIPu.');
    $archive = hr_smlouvy_archive($plan);
    // Jedna transakce musi chranit vsechny cilove tabulky, nikoli jen dokumenty.
    foreach (['hr_dokument', 'hr_dokument_soubor', 'hr_dokument_udaje', 'hr_osobni_udaje', 'hr_mzda', 'hr_poznamka'] as $table) {
        $stmt = hr_smlouvy_query($db, 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
        $engine = $stmt->get_result()->fetch_assoc()['ENGINE'] ?? '';
        if (strcasecmp((string)$engine, 'InnoDB') !== 0) {
            throw new HrSmlouvyImportException('Tabulka ' . $table . ' chybí nebo nepodporuje bezpečnou transakci.');
        }
    }
    $types = [];
    foreach ($db->query('SELECT id_dokument_typ,kod_souboru FROM hr_cis_dokument_typ WHERE aktivni=1')->fetch_all(MYSQLI_ASSOC) as $type) {
        $types[$type['kod_souboru']] = (int)$type['id_dokument_typ'];
    }
    $states = [];
    foreach ($db->query('SELECT id_dokument_stav,nazev FROM hr_cis_dokument_stav WHERE aktivni=1')->fetch_all(MYSQLI_ASSOC) as $state) {
        $states[$state['nazev']] = (int)$state['id_dokument_stav'];
    }
    if (!isset($states['Připraven'], $states['Podepsán'])) {
        throw new HrSmlouvyImportException('Chybí stavy dokumentů Připraven / Podepsán.');
    }
    $new = $existing = 0; $people = $newPeople = [];
    foreach ($plan['documents'] as $index => $row) {
        if (!isset($types[$row['code']])) {
            throw new HrSmlouvyImportException('Chybí aktivní typ dokumentu: ' . $row['code'] . '.');
        }
        $extension = strtolower(trim(pathinfo($row['source'], PATHINFO_EXTENSION)));
        if (!in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], true)) {
            throw new HrSmlouvyImportException('Nepovolený formát dokumentu pro osobu ' . $row['id_person'] . '.');
        }
        $stmt = hr_smlouvy_query($db, 'SELECT p.id_user,p.id_firma,p.testovaci,o.prijmeni FROM hr_person p LEFT JOIN hr_osobni_udaje o ON o.id_osobni_udaje=(SELECT MAX(ou.id_osobni_udaje) FROM hr_osobni_udaje ou WHERE ou.id_person=p.id_person AND ou.platny=1) WHERE p.id_person=?', [$row['id_person']]);
        $person = $stmt->get_result()->fetch_assoc();
        if (!$person || (int)$person['testovaci'] !== 0 || (int)$person['id_user'] !== $row['id_user'] || (int)$person['id_firma'] !== $row['id_firma']) {
            throw new HrSmlouvyImportException('Osoba ' . $row['id_person'] . ' neodpovídá schválenému párování nebo jde o testovací účet.');
        }
        // U admina jsou v lokalni DB historicke testovaci osobni nazvy; jehoID schvalil uzivatel.
        if ($row['id_person'] !== 1 && mb_strtolower(trim((string)$person['prijmeni'])) !== mb_strtolower(trim((string)$row['expected_surname']))) {
            throw new HrSmlouvyImportException('Příjmení osoby ' . $row['id_person'] . ' se od kontroly změnilo. Ověřte párování.');
        }
        hr_smlouvy_bytes($archive, $row);
        $people[$row['id_person']] = true;
        if (hr_smlouvy_existing($db, $row)) {
            $existing++;
        } else {
            $new++; $newPeople[$row['id_person']] = true;
        }
        $progress && $progress(($index + 1) . '/' . $plan['files'] . ': ' . $row['source']);
    }
    $target = dirname(__DIR__, 2) . '/hr/hr_dokumenty';
    if (!is_dir($target) || !is_writable($target)) {
        throw new HrSmlouvyImportException('Adresář www/hr/hr_dokumenty není zapisovatelný.');
    }
    return ['files'=>$plan['files'], 'people'=>count($people), 'new_files'=>$new, 'new_people'=>count($newPeople),
        'existing_files'=>$existing, 'manifest_hash'=>$plan['manifest_hash'], 'types'=>$types, 'states'=>$states];
}

/** Neshodu uchova v historii i v poznamce viditelne na karte zamestnance. */
function hr_smlouvy_note(mysqli $db, int $person, string $message, int $idUser): void
{
    hr_smlouvy_query($db, 'INSERT INTO hr_poznamka (id_person,text_poznamky,id_user_zadal) VALUES (?,?,?)', [$person,$message,$idUser]);
    hr_smlouvy_query($db, 'UPDATE hr_osobni_udaje SET poznamka=CONCAT_WS(CHAR(10),NULLIF(poznamka,\'\'),?) WHERE id_person=? AND platny=1 ORDER BY id_osobni_udaje DESC LIMIT 1', [$message,$person]);
}

/** Ulozi schvalene opravy osobnich udaju; neshody s pozdejsi editaci neprepisuje. */
function hr_smlouvy_personal(mysqli $db, array $plan, int $idUser): void
{
    foreach ($plan['personal_updates'] as $update) {
        $field = $update['field'];
        if (!in_array($field, ['jmeno','rodne_cislo'], true)) {
            throw new HrSmlouvyImportException('Neplatné osobní pole importu.');
        }
        $stmt = hr_smlouvy_query($db, 'SELECT id_osobni_udaje,'.$field.' AS value FROM hr_osobni_udaje WHERE id_person=? AND platny=1 ORDER BY id_osobni_udaje DESC LIMIT 1 FOR UPDATE', [$update['id_person']]);
        $current = $stmt->get_result()->fetch_assoc();
        if (!$current) {
            throw new HrSmlouvyImportException('Chybí osobní údaje osoby '.$update['id_person'].'.');
        }
        $old = (string)($current['value'] ?? '');
        if (!in_array($old, $update['allowed_old'], true)) {
            hr_smlouvy_note($db, $update['id_person'], 'Nutné dořešit: import navrhuje '.$field.'='.$update['value'].', ale současný údaj byl mezitím změněn.', $idUser);
            continue;
        }
        hr_smlouvy_query($db, 'UPDATE hr_osobni_udaje SET '.$field.'=? WHERE id_osobni_udaje=?', [$update['value'],$current['id_osobni_udaje']]);
    }
    // Smluvni fix je hr_mzda; hr_sazby slouzi i provoznimuCOL a nesmi se prepsat smluvni castkou.
    foreach ($plan['approved_monthly_rates'] as $rate) {
        $stmt = hr_smlouvy_query($db, 'SELECT pv.id_pracovni_vztah FROM hr_pracovni_vztah pv JOIN hr_cis_pracovni_vztah_typ t ON t.id_pracovni_vztah_typ=pv.id_pracovni_vztah_typ WHERE pv.id_person=? AND pv.platny=1 AND t.nazev=\'HPP\' AND pv.datum_nastupu<=? AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni>=?)', [$rate['id_person'],$rate['from'],$rate['from']]);
        $relations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $message = 'Smluvní fix '.$rate['amount'].' Kč/měsíc od '.$rate['from'].'. '.$rate['warning'];
        if (count($relations) !== 1) {
            hr_smlouvy_note($db, $rate['id_person'], 'Nutné dořešit: '.$message.' Nelze jednoznačně připojit historický HPP.', $idUser);
            continue;
        }
        $idRelation = (int)$relations[0]['id_pracovni_vztah'];
        $stmt = hr_smlouvy_query($db, 'SELECT id_mzda,castka FROM hr_mzda WHERE id_pracovni_vztah=? AND platnost_od=? AND platny=1', [$idRelation,$rate['from']]);
        $existing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($existing !== []) {
            if (count($existing) !== 1 || (int)$existing[0]['castka'] !== $rate['amount']) {
                hr_smlouvy_note($db, $rate['id_person'], 'Nutné dořešit: '.$message.' Existující mzdový záznam se liší; ponechán.', $idUser);
            }
            continue;
        }
        $stmt = hr_smlouvy_query($db, 'SELECT id_mzda_typ FROM cis_mzda_typ WHERE kod=\'fix\'');
        $types = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($types) !== 1) {
            hr_smlouvy_note($db, $rate['id_person'], 'Nutné dořešit: '.$message.' Nejednoznačný typ měsíční mzdy.', $idUser);
            continue;
        }
        // Otevrenou starsi mzdu neukoncuje ani neprekryva; odlisnost predava personalistovi.
        $stmt = hr_smlouvy_query($db, 'SELECT id_mzda FROM hr_mzda WHERE id_pracovni_vztah=? AND platny=1 AND (platnost_od IS NULL OR platnost_od<?) AND (platnost_do IS NULL OR platnost_do>=?)', [$idRelation,$rate['from'],$rate['from']]);
        if ($stmt->get_result()->num_rows > 0) {
            hr_smlouvy_note($db, $rate['id_person'], 'Nutné dořešit: '.$message.' Starší mzda překrývá nové období; původní data ponechána.', $idUser);
            continue;
        }
        // Nezasahuje do existujicich obdobi; platnost konci pred dalsi znamou mzdou.
        $stmt = hr_smlouvy_query($db, 'SELECT MIN(platnost_od) AS next_date FROM hr_mzda WHERE id_pracovni_vztah=? AND platny=1 AND platnost_od>?', [$idRelation,$rate['from']]);
        $next = $stmt->get_result()->fetch_assoc()['next_date'] ?? null;
        $end = $next ? date('Y-m-d', strtotime($next.' -1 day')) : null;
        hr_smlouvy_query($db, 'INSERT INTO hr_mzda (id_pracovni_vztah,id_mzda_typ,castka,platnost_od,platnost_do,id_user_zadal) VALUES (?,?,?,?,?,?)', [$idRelation,$types[0]['id_mzda_typ'],$rate['amount'],$rate['from'],$end,$idUser]);
    }
}

/** Doplnovaci zapis v jedne transakci; cizi soubory ani existujici dokumenty neprepisuje. */
function hr_smlouvy_import(mysqli $db, int $idUser, callable $progress): array
{
    $lockPath = dirname(__DIR__) . '/tmp/hr_import_smlouvy.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new HrSmlouvyImportException('Jiný import smluv už běží. Počkejte na jeho dokončení.');
    }
    $created = []; $committing = false; $started = false;
    try {
        $preview = hr_smlouvy_preview($db, $progress);
        $plan = hr_smlouvy_manifest();
        if (!hash_equals($preview['manifest_hash'], $plan['manifest_hash'])) {
            throw new HrSmlouvyImportException('Importní plán se během kontroly změnil. Zkontrolujte podklady znovu.');
        }
        $archive = hr_smlouvy_archive($plan);
        $db->begin_transaction(); $started = true;
        $new = $skipped = 0; $people = [];
        foreach ($plan['documents'] as $index => $row) {
            if (hr_smlouvy_existing($db, $row)) {
                $skipped++; continue;
            }
            $bytes = hr_smlouvy_bytes($archive, $row);
            $signed = in_array($row['signature'], ['Podepsán','Elektronicky podepsán'], true);
            hr_smlouvy_query($db, 'INSERT INTO hr_dokument (verze,id_firma,id_person,id_dokument_typ,id_dokument_stav,nazev,id_user_zadal) VALUES (1,?,?,?,?,?,?)', [$row['id_firma'],$row['id_person'],$preview['types'][$row['code']],$preview['states'][$signed ? 'Podepsán' : 'Připraven'],mb_substr($row['title'],0,200),$idUser]);
            $id = (int)$db->insert_id;
            $name = $id.'_1.'.strtolower(trim(pathinfo($row['source'], PATHINFO_EXTENSION)));
            $relative = 'hr/hr_dokumenty/'.$row['id_firma'].'/'.$row['id_person'].'/'.$name;
            $path = dirname(__DIR__, 2).'/'.$relative;
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new HrSmlouvyImportException('Nelze vytvořit adresář dokumentů osoby '.$row['id_person'].'.');
            }
            $root = realpath(dirname(__DIR__, 2).'/hr/hr_dokumenty');
            $realDir = realpath($directory);
            if (!$root || !$realDir || !str_starts_with($realDir, $root.DIRECTORY_SEPARATOR)) {
                throw new HrSmlouvyImportException('Cílový adresář není uvnitř HR dokumentů.');
            }
            $file = fopen($path, 'xb');
            if (!$file) {
                throw new HrSmlouvyImportException('Cílový soubor již existuje nebo jej nelze vytvořit.');
            }
            $created[] = $path;
            try {
                $offset = 0;
                while ($offset < strlen($bytes)) {
                    $written = fwrite($file, substr($bytes, $offset));
                    if ($written === false || $written === 0) {
                        throw new HrSmlouvyImportException('Zápis souboru selhal; zkontrolujte volné místo.');
                    }
                    $offset += $written;
                }
            } finally {
                fclose($file);
            }
            if (!hash_equals($row['sha256'], (string)hash_file('sha256', $path))) {
                throw new HrSmlouvyImportException('Kontrola uloženého souboru selhala.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            hr_smlouvy_query($db, 'INSERT INTO hr_dokument_soubor (id_dokument,verze,puvodni_nazev,ulozeny_nazev,relativni_cesta,mime_typ,velikost,sha256,poradi,id_user_zadal) VALUES (?,1,?,?,?,?,?,?,1,?)', [$id,$name,$name,$relative,$mime,strlen($bytes),$row['sha256'],$idUser]);
            // Osobni doklady nejsou smlouvy: absence smluvniho podpisu neni zavada jejich kopie.
            $noSignature = in_array($row['code'], ['op','pas','pobyt','zp','rodny_list_ditete','duchod'], true);
            $warnings = $noSignature ? array_values(array_filter($row['warnings'], static fn(string $warning): bool => !in_array($warning, ['Nutné dořešit: Neověřeno.','Nutné dořešit: Nepodepsán.'], true))) : $row['warnings'];
            $meta = json_encode(['hr_import_smlouvy'=>1,'stav_podpisu'=>$noSignature ? null : $row['signature'],'upozorneni'=>$warnings,'casti'=>$row['parts']], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $details = json_encode($row['details'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            hr_smlouvy_query($db, 'INSERT INTO hr_dokument_udaje (id_dokument,verze,poznamka,ocr_text,overeno_clovekem,id_user_zadal) VALUES (?,1,?,?,0,?)', [$id,$meta,$details,$idUser]);
            $new++; $people[$row['id_person']] = true;
            $progress(($index+1).'/'.$plan['files'].': uložen '.$name.' — osoba '.$row['id_person']);
        }
        // Pri opetovnem spusteni nad hotovymi soubory osobni data znovu nemeni.
        if ($new > 0) {
            hr_smlouvy_personal($db, $plan, $idUser);
        }
        $progress('Potvrzuji transakci.');
        $committing = true;
        $db->commit(); $started = false;
        return ['new_files'=>$new,'new_people'=>count($people),'existing_files'=>$skipped];
    } catch (Throwable $error) {
        if ($started) {
            try { $db->rollback(); } catch (Throwable) { /* Spojeni mohlo byt preruseno; dalsi zapis se neprovadi. */ }
        }
        // Po nejistem vysledku COMMIT soubory zachova; jinak maze pouze soubory vytvorene touto transakci.
        if (!$committing) {
            foreach ($created as $path) {
                if (is_file($path)) { unlink($path); }
            }
        }
        throw $error;
    } finally {
        flock($lock, LOCK_UN); fclose($lock);
    }
}
