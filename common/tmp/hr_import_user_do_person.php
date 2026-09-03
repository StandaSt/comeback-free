<?php
declare(strict_types=1);

/*
 * Jednoúčelový reset testovacích HR dat a volitelný import uživatelů do HR.
 *
 * Spuštění:
 *   php _www/common/tmp/hr_import_user_do_person.php --db=local --reset --scope=all|vd|nd_employees --import-users=0|1
 *   php _www/common/tmp/hr_import_user_do_person.php --db=server --reset
 *
 * Na serveru je povolen pouze první kompletní běh, když je hr_person prázdná.
 * Číselníky hr_cis_*, hr_mzdy_mesic, hr_sazby a uživatelská data IS zachovává.
 */

$directRun = defined('CB_HR_IMPORT_DIRECT') && CB_HR_IMPORT_DIRECT === true;

if (!$directRun && PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Tento skript lze spustit pouze z příkazové řádky.\n");
    exit(1);
}

$options = $directRun ? [] : getopt('', ['db:', 'reset', 'scope:', 'import-users:']);
$environment = $directRun
    ? (string)($GLOBALS['CB_HR_IMPORT_ENVIRONMENT'] ?? '')
    : (string)($options['db'] ?? '');
$resetScope = $directRun
    ? (string)($GLOBALS['CB_HR_RESET_SCOPE'] ?? 'all')
    : (string)($options['scope'] ?? 'all');
$importUsersRaw = $directRun
    ? (!empty($GLOBALS['CB_HR_IMPORT_USERS']) ? '1' : '0')
    : (string)($options['import-users'] ?? '1');

if ($environment === 'server') {
    $resetScope = 'all';
    $importUsersRaw = '1';
}

if (
    !in_array($environment, ['local', 'server'], true)
    || (!$directRun && !array_key_exists('reset', $options))
    || !in_array($resetScope, ['all', 'vd', 'nd_employees'], true)
    || !in_array($importUsersRaw, ['0', '1'], true)
) {
    fwrite(STDERR, "Použití: php common/tmp/hr_import_user_do_person.php --db=local --reset --scope=all|vd|nd_employees --import-users=0|1\n");
    fwrite(STDERR, "         php common/tmp/hr_import_user_do_person.php --db=server --reset\n");
    exit(1);
}

$importUsers = $importUsersRaw === '1';

$secretsPath = __DIR__ . '/../config/secrets.php';
if (!is_file($secretsPath)) {
    if ($directRun) {
        throw new RuntimeException('Konfigurace databáze nebyla nalezena.');
    }
    fwrite(STDERR, "Konfigurace databáze nebyla nalezena.\n");
    exit(1);
}

require $secretsPath;

if (!isset($SECRETS['db'][$environment]) || !is_array($SECRETS['db'][$environment])) {
    if ($directRun) {
        throw new RuntimeException("Chybí konfigurace databáze pro prostředí {$environment}.");
    }
    fwrite(STDERR, "Chybí konfigurace databáze pro prostředí {$environment}.\n");
    exit(1);
}

/** @var array{host:string,user:string,pass:string,name:string} $config */
$config = $SECRETS['db'][$environment];
$db = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);

if ($db->connect_errno !== 0) {
    if ($directRun) {
        throw new RuntimeException("Nelze se připojit k databázi prostředí {$environment}.");
    }
    fwrite(STDERR, "Nelze se připojit k databázi prostředí {$environment}.\n");
    exit(1);
}

$db->set_charset('utf8mb4');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($environment === 'server') {
    $serverGuard = $db->query('SELECT 1 FROM hr_person LIMIT 1');
    if ($serverGuard->fetch_row() !== null) {
        $message = 'Serverový HR import lze spustit pouze jednou nad prázdnou tabulkou hr_person.';
        if ($directRun) {
            throw new RuntimeException($message);
        }
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    $serverGuard->free();
}

/**
 * Vrátí normalizované telefonní číslo bez změny původní hodnoty.
 */
function hr_import_normalize_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '00420')) {
        $digits = substr($digits, 5);
    } elseif (str_starts_with($digits, '420') && strlen($digits) === 12) {
        $digits = substr($digits, 3);
    }

    return $digits !== '' ? $digits : null;
}

/* Kompletní reset HR. Pořadí respektuje existující databázové vazby. */
$deleteAllQueries = [
    'DELETE FROM hr_vybaveni_predani',
    'DELETE FROM hr_nd_token',
    'DELETE FROM hr_vd_token',
    'DELETE FROM hr_dokument_podpis',
    'DELETE FROM hr_cinnost',
    'DELETE FROM hr_dokument_soubor',
    'DELETE FROM hr_prohlidka',
    'DELETE FROM hr_skoleni',
    'DELETE FROM hr_benefit',
    'DELETE FROM hr_mzda',
    'DELETE FROM hr_pracovni_preruseni',
    'DELETE FROM hr_pracovni_ukonceni',
    'DELETE FROM hr_pracovni_uvazek',
    'DELETE FROM hr_dovolena_narok',
    'DELETE FROM hr_hodnoceni',
    'DELETE FROM hr_nadrizeny',
    'DELETE FROM hr_nepritomnost',
    'DELETE FROM hr_nouzovy_kontakt',
    'DELETE FROM hr_onboarding_ukol',
    'DELETE FROM hr_poznamka',
    'DELETE FROM hr_adresa',
    'DELETE FROM hr_bankovni_ucet',
    'DELETE FROM hr_email',
    'DELETE FROM hr_telefon',
    'DELETE FROM hr_osobni_udaje',
    'DELETE FROM hr_pracoviste',
    'DELETE FROM hr_zarazeni',
    'DELETE FROM hr_pracovni_vztah',
    'DELETE FROM hr_dokument',
    'DELETE FROM hr_nd',
    'DELETE FROM hr_vd_podminky',
    'DELETE FROM hr_vd_akce',
    'DELETE FROM hr_pozadavek',
    'DELETE FROM hr_person',
    'DELETE FROM hr_vd',
];

/* Reset celého náboru při zachování zaměstnanců a jejich dokumentů. */
$deleteVdQueries = [
    'DELETE c FROM hr_cinnost c LEFT JOIN hr_dokument d ON d.id_dokument = c.id_dokument AND d.verze = c.verze WHERE (c.id_vd IS NOT NULL AND c.id_person IS NULL) OR (d.id_person IS NULL AND (d.id_vd IS NOT NULL OR d.id_nd IS NOT NULL))',
    'DELETE p FROM hr_dokument_podpis p INNER JOIN hr_dokument d ON d.id_dokument = p.id_dokument AND d.verze = p.verze WHERE d.id_person IS NULL AND (d.id_vd IS NOT NULL OR d.id_nd IS NOT NULL)',
    'DELETE s FROM hr_dokument_soubor s INNER JOIN hr_dokument d ON d.id_dokument = s.id_dokument AND d.verze = s.verze WHERE d.id_person IS NULL AND (d.id_vd IS NOT NULL OR d.id_nd IS NOT NULL)',
    'DELETE FROM hr_dokument WHERE id_person IS NULL AND (id_vd IS NOT NULL OR id_nd IS NOT NULL)',
    'UPDATE hr_dokument SET id_vd = NULL, id_nd = NULL WHERE id_person IS NOT NULL AND (id_vd IS NOT NULL OR id_nd IS NOT NULL)',
    'DELETE FROM hr_nd_token',
    'DELETE FROM hr_vd_token',
    'DELETE FROM hr_nd',
    'DELETE FROM hr_vd_podminky',
    'DELETE FROM hr_vd_akce',
    'DELETE FROM hr_pozadavek WHERE id_vd IS NOT NULL',
    'DELETE FROM hr_vd',
];

/*
 * Reset ND a zaměstnanců při zachování uchazečů a výsledku pohovoru.
 * Uchazeči z navazujících fází se vrátí do stavu Domluven nástup (24).
 */
$deleteNdEmployeesQueries = [
    'DELETE a FROM hr_vd_akce a INNER JOIN hr_cis_vd_akce_vysledek v ON v.id_vd_akce_vysledek = a.id_vd_akce_vysledek WHERE v.id_vd_akce_typ IN (12, 15, 18, 21, 24, 27) AND a.id_vd IN (SELECT id_vd FROM hr_nd UNION SELECT id_vd FROM hr_vd WHERE id_person IS NOT NULL OR id_vd_stav IN (15, 27, 30, 33, 51, 54, 57, 60, 63, 66))',
    'UPDATE hr_vd vd SET vd.id_vd_stav = 24, vd.id_person = NULL, vd.upraveno = NOW() WHERE vd.id_person IS NOT NULL OR vd.id_vd_stav IN (15, 27, 30, 33, 51, 54, 57, 60, 63, 66) OR EXISTS (SELECT 1 FROM hr_nd nd WHERE nd.id_vd = vd.id_vd)',
    'DELETE FROM hr_nd_token',
    'DELETE FROM hr_nd',
    "DELETE c FROM hr_cinnost c LEFT JOIN hr_dokument d ON d.id_dokument = c.id_dokument AND d.verze = c.verze WHERE c.id_person IS NOT NULL OR d.id_person IS NOT NULL OR d.id_nd IS NOT NULL OR (d.id_vd IS NOT NULL AND d.id_dokument_typ IN (SELECT id_dokument_typ FROM hr_cis_dokument_typ WHERE kod_souboru IN ('dotaznik', 'smlouva')))",
    "DELETE p FROM hr_dokument_podpis p INNER JOIN hr_dokument d ON d.id_dokument = p.id_dokument AND d.verze = p.verze WHERE d.id_person IS NOT NULL OR d.id_nd IS NOT NULL OR (d.id_vd IS NOT NULL AND d.id_dokument_typ IN (SELECT id_dokument_typ FROM hr_cis_dokument_typ WHERE kod_souboru IN ('dotaznik', 'smlouva')))",
    "DELETE s FROM hr_dokument_soubor s INNER JOIN hr_dokument d ON d.id_dokument = s.id_dokument AND d.verze = s.verze WHERE d.id_person IS NOT NULL OR d.id_nd IS NOT NULL OR (d.id_vd IS NOT NULL AND d.id_dokument_typ IN (SELECT id_dokument_typ FROM hr_cis_dokument_typ WHERE kod_souboru IN ('dotaznik', 'smlouva')))",
    'DELETE FROM hr_vybaveni_predani',
    'DELETE FROM hr_prohlidka',
    'DELETE FROM hr_skoleni',
    'DELETE FROM hr_benefit',
    'DELETE FROM hr_mzda',
    'DELETE FROM hr_pracovni_preruseni',
    'DELETE FROM hr_pracovni_ukonceni',
    'DELETE FROM hr_pracovni_uvazek',
    'DELETE FROM hr_dovolena_narok',
    'DELETE FROM hr_hodnoceni',
    'DELETE FROM hr_nadrizeny',
    'DELETE FROM hr_nepritomnost',
    'DELETE FROM hr_nouzovy_kontakt',
    'DELETE FROM hr_onboarding_ukol',
    'DELETE FROM hr_poznamka',
    'DELETE FROM hr_adresa',
    'DELETE FROM hr_bankovni_ucet',
    'DELETE FROM hr_email',
    'DELETE FROM hr_telefon',
    'DELETE FROM hr_osobni_udaje',
    'DELETE FROM hr_pracoviste',
    'DELETE FROM hr_zarazeni',
    'DELETE FROM hr_pracovni_vztah',
    "DELETE FROM hr_dokument WHERE id_person IS NOT NULL OR id_nd IS NOT NULL OR (id_vd IS NOT NULL AND id_dokument_typ IN (SELECT id_dokument_typ FROM hr_cis_dokument_typ WHERE kod_souboru IN ('dotaznik', 'smlouva')))",
    'DELETE FROM hr_person',
];

$deleteQueries = match ($resetScope) {
    'vd' => $deleteVdQueries,
    'nd_employees' => $deleteNdEmployeesQueries,
    default => $deleteAllQueries,
};

/**
 * Importuje pouze uživatele, kteří ještě nemají vazbu v hr_person.
 *
 * @return array<string,int>
 */
function hr_import_users(mysqli $db): array
{
    $source = $db->prepare('
        SELECT u.id_user, u.jmeno, u.prijmeni, u.email, u.telefon, u.aktivni,
               DATE(u.vytvoren_smeny) AS datum_nastupu
        FROM `user` u
        LEFT JOIN hr_person p ON p.id_user = u.id_user
        WHERE p.id_person IS NULL
        ORDER BY u.id_user
    ');
    $source->execute();
    $users = $source->get_result();

    $counts = [
        'osoby' => 0,
        'pracovni_vztahy' => 0,
        'osobni_udaje' => 0,
        'emaily' => 0,
        'telefony' => 0,
        'pracoviste' => 0,
        'zarazeni' => 0,
    ];

    if ($users->num_rows === 0) {
        $source->close();
        return $counts;
    }

    $migrationRelationType = $db->query("
        SELECT id_pracovni_vztah_typ
        FROM hr_cis_pracovni_vztah_typ
        WHERE nazev = 'Doplnit (migrace)'
        LIMIT 1
    ")->fetch_assoc();
    $migrationRelationTypeId = (int)($migrationRelationType['id_pracovni_vztah_typ'] ?? 0);
    if ($migrationRelationTypeId <= 0) {
        throw new RuntimeException('V číselníku chybí typ pracovního vztahu Doplnit (migrace).');
    }

    $insertPerson = $db->prepare('
        INSERT INTO hr_person (id_user, zdroj, id_user_zadal, vytvoreno, aktivni, overen, kompletni)
        VALUES (?, \'migrace_smeny\', NULL, NOW(), ?, 0, 0)
    ');
    $insertWorkRelation = $db->prepare('
        INSERT INTO hr_pracovni_vztah
            (id_person, id_pracovni_vztah_typ, datum_nastupu, id_user_zadal, vytvoreno, platny)
        VALUES (?, ?, ?, NULL, NOW(), 1)
    ');
    $insertPersonal = $db->prepare('
        INSERT INTO hr_osobni_udaje (id_person, jmeno, prijmeni, id_user_zadal, vytvoreno, platny)
        VALUES (?, ?, ?, NULL, NOW(), 1)
    ');
    $insertEmail = $db->prepare('
        INSERT INTO hr_email (id_person, id_email_typ, email, hlavni, id_user_zadal, vytvoreno, platny)
        VALUES (?, 1, ?, 1, NULL, NOW(), 1)
    ');
    $insertPhone = $db->prepare('
        INSERT INTO hr_telefon (id_person, id_telefon_typ, telefon, telefon_normalizovany, hlavni, id_user_zadal, vytvoreno, platny)
        VALUES (?, 1, ?, ?, 1, NULL, NOW(), 1)
    ');
    $sourceWorkplaces = $db->prepare('
        SELECT id_pob, COALESCE(`main`, 0) AS hlavni
        FROM user_pobocka
        WHERE id_user = ?
        ORDER BY id_pob
    ');
    $insertWorkplace = $db->prepare('
        INSERT INTO hr_pracoviste (id_person, id_pob, hlavni, platnost_od, id_user_zadal, vytvoreno, platny)
        VALUES (?, ?, ?, ?, NULL, NOW(), 1)
    ');
    $sourceSlots = $db->prepare('
        SELECT id_slot
        FROM user_slot
        WHERE id_user = ?
        ORDER BY id_slot
    ');
    $insertAssignment = $db->prepare('
        INSERT INTO hr_zarazeni (id_person, id_slot, hlavni, platnost_od, id_user_zadal, vytvoreno, platny)
        VALUES (?, ?, 0, ?, NULL, NOW(), 1)
    ');

    while ($user = $users->fetch_assoc()) {
        $idUser = (int)$user['id_user'];
        $active = (int)$user['aktivni'];
        $name = trim((string)$user['jmeno']);
        $surname = trim((string)$user['prijmeni']);
        $email = trim((string)$user['email']);
        $phone = trim((string)($user['telefon'] ?? ''));
        $startDate = (string)($user['datum_nastupu'] ?? '');

        if ($name === '' || $surname === '' || $startDate === '') {
            throw new RuntimeException("Uživatel #{$idUser} nemá údaje potřebné pro import.");
        }

        $insertPerson->bind_param('ii', $idUser, $active);
        $insertPerson->execute();
        $idPerson = (int)$db->insert_id;
        $counts['osoby']++;

        $insertWorkRelation->bind_param('iis', $idPerson, $migrationRelationTypeId, $startDate);
        $insertWorkRelation->execute();
        $counts['pracovni_vztahy']++;

        $insertPersonal->bind_param('iss', $idPerson, $name, $surname);
        $insertPersonal->execute();
        $counts['osobni_udaje']++;

        if ($email !== '') {
            $insertEmail->bind_param('is', $idPerson, $email);
            $insertEmail->execute();
            $counts['emaily']++;
        }

        if ($phone !== '') {
            $normalizedPhone = hr_import_normalize_phone($phone);
            $insertPhone->bind_param('iss', $idPerson, $phone, $normalizedPhone);
            $insertPhone->execute();
            $counts['telefony']++;
        }

        $sourceWorkplaces->bind_param('i', $idUser);
        $sourceWorkplaces->execute();
        $workplaces = $sourceWorkplaces->get_result();
        while ($workplace = $workplaces->fetch_assoc()) {
            $idPob = (int)$workplace['id_pob'];
            $main = (int)$workplace['hlavni'];
            $insertWorkplace->bind_param('iiis', $idPerson, $idPob, $main, $startDate);
            $insertWorkplace->execute();
            $counts['pracoviste']++;
        }

        $sourceSlots->bind_param('i', $idUser);
        $sourceSlots->execute();
        $slots = $sourceSlots->get_result();
        while ($slot = $slots->fetch_assoc()) {
            $idSlot = (int)$slot['id_slot'];
            $insertAssignment->bind_param('iis', $idPerson, $idSlot, $startDate);
            $insertAssignment->execute();
            $counts['zarazeni']++;
        }
    }

    $source->close();
    $insertPerson->close();
    $insertWorkRelation->close();
    $insertPersonal->close();
    $insertEmail->close();
    $insertPhone->close();
    $sourceWorkplaces->close();
    $insertWorkplace->close();
    $sourceSlots->close();
    $insertAssignment->close();

    return $counts;
}

$transactionStarted = false;

try {
    $db->begin_transaction();
    $transactionStarted = true;

    foreach ($deleteQueries as $query) {
        $db->query($query);
    }

    $counts = $importUsers ? hr_import_users($db) : [];

    $db->commit();
    $transactionStarted = false;

    $scopeLabels = [
        'all' => 'kompletní HR',
        'vd' => 'VD a uchazeči',
        'nd_employees' => 'ND a zaměstnanci',
    ];
    $output = 'Reset dokončen: ' . $scopeLabels[$resetScope] . ".\n";
    if ($importUsers) {
        $output .= "Import chybějících uživatelů dokončen.\n";
        foreach ($counts as $name => $count) {
            $output .= $name . ': ' . $count . PHP_EOL;
        }
    } else {
        $output .= "Import uživatelů nebyl proveden.\n";
    }

    if ($directRun) {
        $GLOBALS['CB_HR_IMPORT_OUTPUT'] = trim($output);
        return;
    }

    echo $output;
} catch (Throwable $exception) {
    if ($transactionStarted) {
        $db->rollback();
    }
    if ($directRun) {
        throw $exception;
    }
    fwrite(STDERR, 'Import selhal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
