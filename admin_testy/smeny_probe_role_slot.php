<?php
// lib/smeny_probe_role_slot.php * Verze: V4 * Aktualizace: 12.2.2026 * Počet řádků: 295
declare(strict_types=1);

/*
 * JEDNORÁZOVÝ PROBE – Směny: ČÍSELNÍK ROLE + ČÍSELNÍK SLOT + data PŘIHLÁŠENÉHO uživatele
 *
 * Co přesně dělá:
 * - spustí se RUČNĚ (otevřením URL /lib/smeny_probe_role_slot.php) po přihlášení
 * - vezme token ze session (cb_token)
 * - vytáhne:
 *   A) userGetLogged: role uživatele (roles{id,name})
 *   B) userGetLogged: sloty uživatele (shiftRoleTypeNames)
 *   C) číselník rolí: roleFindAll{id,name}
 *   D) číselník slotů: shiftRoleTypeFindAll{id,name}
 * - dopočítá sloty uživatele včetně ID ze Směn (podle číselníku)
 * - výsledek zapíše do pomocne/data_smeny.txt
 *
 * Důležité:
 * - je to diagnostika, NEMĚNÍ DB
 * - nic nemaže, jen přidává řádky do txt
 * - záměrně NELOGUJE GraphQL chyby (ERRORY), aby výpis nezabíral místo
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/smeny_graphql.php';

$OUT_FILE = __DIR__ . '/../pomocne/data_smeny.txt';
$GQL_URL  = 'https://smeny.pizzacomeback.cz/graphql';

/**
 * Připraví řádku do txt: "YYYY-mm-dd HH:ii:ss | LABEL | DATA"
 */
function probe_txt_line(string $label, mixed $data): string
{
    $ts = date('Y-m-d H:i:s');

    if (is_string($data)) {
        $val = $data;
    } else {
        $tmp = json_encode($data, JSON_UNESCAPED_UNICODE);
        $val = ($tmp === false) ? '[json_encode_failed]' : $tmp;
    }

    $val = str_replace(["\r", "\n"], [' ', ' '], $val);

    return $ts . ' | ' . $label . ' | ' . $val . PHP_EOL;
}

/**
 * Append do txt (vytvoří adresář, zamkne soubor).
 */
function probe_file_append(string $file, string $line): void
{
    @mkdir(dirname($file), 0775, true);
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Vrátí token ze session, nebo null (a zapíše jen stručnou chybu do txt).
 */
function probe_get_token(string $outFile): ?string
{
    $token = $_SESSION['cb_token'] ?? '';
    if (!is_string($token) || $token === '') {
        probe_file_append($outFile, probe_txt_line('PROBE:ERROR', 'Chybí token v session (cb_token).'));
        return null;
    }
    return $token;
}

/**
 * Vrátí id_user z session (0 když chybí).
 */
function probe_get_id_user(): int
{
    $cbUser = $_SESSION['cb_user'] ?? null;
    if (is_array($cbUser) && isset($cbUser['id_user'])) {
        return (int)$cbUser['id_user'];
    }
    return 0;
}

/**
 * Spustí dotaz a zapíše výsledek pouze když je OK.
 * (Chyby se nezapisují, aby nezabíraly místo.)
 */
function probe_run_ok(string $outFile, string $label, string $url, string $query, array $vars, string $token): ?array
{
    try {
        $data = cb_smeny_graphql($url, $query, $vars, $token);
        probe_file_append($outFile, probe_txt_line($label, [
            'ok' => 1,
            'data' => $data,
        ]));
        return $data;
    } catch (Throwable $e) {
        return null;
    }
}

$token = probe_get_token($OUT_FILE);
if ($token === null) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Chybí token v session (cb_token).\n";
    exit;
}

$idUser = probe_get_id_user();

/*
 * Start bloku – identifikace běhu
 */
probe_file_append($OUT_FILE, probe_txt_line('PROBE:RUN:START', [
    'mode' => 'role_slot_probe_user_only',
    'id_user' => $idUser,
    'email' => (string)($_SESSION['cb_user']['email'] ?? '---'),
]));

/*
 * 0) Ověření tokenu (minimální dotaz)
 */
probe_run_ok(
    $OUT_FILE,
    'PROBE:ME:MIN',
    $GQL_URL,
    'query{ userGetLogged{ id email } }',
    [],
    $token
);

/*
 * 1) UŽIVATEL: role + sloty (tohle už víme, že funguje)
 */
probe_run_ok(
    $OUT_FILE,
    'PROBE:ME:ROLES_OBJECT',
    $GQL_URL,
    'query{ userGetLogged{ id email roles{ id name } } }',
    [],
    $token
);

$meSloty = probe_run_ok(
    $OUT_FILE,
    'PROBE:ME:SHIFTROLETYPE_NAMES',
    $GQL_URL,
    'query{ userGetLogged{ id email shiftRoleTypeNames } }',
    [],
    $token
);

/*
 * 2) ČÍSELNÍKY: roleFindAll + shiftRoleTypeFindAll (jen jako kompaktní mapy name->id)
 */
$roleCatalog = null;
$roleFindAll = probe_run_ok(
    $OUT_FILE,
    'PROBE:ROLE:FINDALL',
    $GQL_URL,
    'query{ roleFindAll{ id name } }',
    [],
    $token
);
if (is_array($roleFindAll) && isset($roleFindAll['roleFindAll']) && is_array($roleFindAll['roleFindAll'])) {
    $map = [];
    foreach ($roleFindAll['roleFindAll'] as $r) {
        if (!is_array($r)) {
            continue;
        }
        $name = trim((string)($r['name'] ?? ''));
        $id = (int)($r['id'] ?? 0);
        if ($name !== '' && $id > 0) {
            $map[$name] = $id;
        }
    }
    $roleCatalog = $map;

    probe_file_append($OUT_FILE, probe_txt_line('PROBE:ROLE:FINDALL:CATALOG', [
        'count' => count($map),
        'data' => $map,
    ]));
}

$slotCatalog = null;
$slotFindAll = probe_run_ok(
    $OUT_FILE,
    'PROBE:SHIFTROLETYPE:FINDALL',
    $GQL_URL,
    'query{ shiftRoleTypeFindAll{ id name } }',
    [],
    $token
);
if (is_array($slotFindAll) && isset($slotFindAll['shiftRoleTypeFindAll']) && is_array($slotFindAll['shiftRoleTypeFindAll'])) {
    $map = [];
    foreach ($slotFindAll['shiftRoleTypeFindAll'] as $s) {
        if (!is_array($s)) {
            continue;
        }
        $name = trim((string)($s['name'] ?? ''));
        $id = (int)($s['id'] ?? 0);
        if ($name !== '' && $id > 0) {
            $map[$name] = $id;
        }
    }
    $slotCatalog = $map;

    probe_file_append($OUT_FILE, probe_txt_line('PROBE:SHIFTROLETYPE:FINDALL:CATALOG', [
        'count' => count($map),
        'data' => $map,
    ]));
}

/*
 * 3) UŽIVATEL: sloty s ID ze Směn (podle číselníku)
 */
if (is_array($meSloty) && isset($meSloty['userGetLogged']['shiftRoleTypeNames']) && is_array($meSloty['userGetLogged']['shiftRoleTypeNames']) && is_array($slotCatalog)) {

    $names = $meSloty['userGetLogged']['shiftRoleTypeNames'];
    $out = [];
    $missing = [];

    foreach ($names as $n) {
        $name = trim((string)$n);
        if ($name === '') {
            continue;
        }
        $id = (int)($slotCatalog[$name] ?? 0);
        if ($id > 0) {
            $out[] = ['id' => $id, 'name' => $name];
        } else {
            $missing[] = $name;
        }
    }

    probe_file_append($OUT_FILE, probe_txt_line('USER:SLOTY:SMENY_ID', [
        'count' => count($out),
        'sloty' => $out,
        'missing_names' => $missing,
    ]));
}

/*
 * 4) Bonus: stejné role i přes jiné resolvery (jen pokud fungují, bez errorů)
 */
if ($idUser > 0) {

    probe_run_ok(
        $OUT_FILE,
        'PROBE:AH:USER_ROLES',
        $GQL_URL,
        'query($id:Int!){
            actionHistoryFindById(id:$id){
                user{
                    id
                    email
                    roles{ id name }
                }
            }
        }',
        ['id' => $idUser],
        $token
    );

    probe_run_ok(
        $OUT_FILE,
        'PROBE:USER:FINDBYID_ROLES',
        $GQL_URL,
        'query($id:Int!){
            userFindById(id:$id){
                id
                email
                roles{ id name }
            }
        }',
        ['id' => $idUser],
        $token
    );
}

/*
 * Konec bloku
 */
probe_file_append($OUT_FILE, probe_txt_line('PROBE:RUN:END', [
    'mode' => 'role_slot_probe_user_only',
]));

/*
 * Výstup pro prohlížeč (aby bylo jasné, že skript doběhl).
 */
header('Content-Type: text/plain; charset=utf-8');
echo "OK – zapsáno do pomocne/data_smeny.txt\n";

// lib/smeny_probe_role_slot.php * Verze: V4 * Aktualizace: 12.2.2026 * Počet řádků: 295
// Konec souboru