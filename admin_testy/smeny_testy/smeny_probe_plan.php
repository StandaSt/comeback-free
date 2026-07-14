<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/smeny_graphql.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
$OUT_FILE = __DIR__ . '/../_kandidati/pomocne/smeny_probe_plan.txt';

function cb_probe_plan_out(string $file, string $label, mixed $data): void
{
    @mkdir(dirname($file), 0775, true);
    $ts = date('Y-m-d H:i:s');

    if (is_string($data)) {
        $payload = $data;
    } else {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $payload = ($json === false) ? '[json_encode_failed]' : $json;
    }

    $line = "=== {$ts} | {$label} ===\n{$payload}\n\n";
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

function cb_probe_plan_type_ref(array $t): array
{
    $kind = (string)($t['kind'] ?? '');
    $name = $t['name'] ?? null;
    $of = $t['ofType'] ?? null;
    $nonNull = ($kind === 'NON_NULL');

    while (is_array($of)) {
        $k = (string)($of['kind'] ?? '');
        if ($k === 'NON_NULL') {
            $nonNull = true;
        }
        $kind = ($k !== '') ? $k : $kind;
        if (!empty($of['name'])) {
            $name = $of['name'];
        }
        $of = $of['ofType'] ?? null;
    }

    return [
        'kind' => $kind,
        'name' => is_string($name) ? $name : '',
        'non_null' => $nonNull,
    ];
}

function cb_probe_plan_is_supported_arg(array $arg): bool
{
    $t = cb_probe_plan_type_ref((array)($arg['type'] ?? []));
    return in_array($t['kind'], ['SCALAR', 'ENUM'], true);
}

function cb_probe_plan_guess_value(string $argName, string $kind, string $typeName, int $idUser, int $branchId): mixed
{
    $n = strtolower($argName);

    if ($kind === 'SCALAR' && in_array($typeName, ['Int', 'Float'], true)) {
        if (str_contains($n, 'branch')) {
            return ($branchId > 0) ? $branchId : 1;
        }
        if (str_contains($n, 'user')) {
            return $idUser;
        }
        if (str_contains($n, 'week')) {
            return (int)date('W');
        }
        if (str_contains($n, 'year')) {
            return (int)date('Y');
        }
        if (str_contains($n, 'limit')) {
            return 5;
        }
        if (str_contains($n, 'offset')) {
            return 0;
        }
        if ($n === 'id') {
            return $idUser;
        }
        return 1;
    }

    if ($kind === 'SCALAR' && $typeName === 'Boolean') {
        return true;
    }

    if ($kind === 'SCALAR' && in_array($typeName, ['String', 'ID'], true)) {
        if (str_contains($n, 'from') || str_contains($n, 'od') || str_contains($n, 'start')) {
            return date('Y-m-d');
        }
        if (str_contains($n, 'to') || str_contains($n, 'do') || str_contains($n, 'end')) {
            return date('Y-m-d', strtotime('+7 day'));
        }
        if ($n === 'id') {
            return (string)$idUser;
        }
        return '1';
    }

    return null;
}

function cb_probe_plan_build_call(array $field, int $idUser, int $branchId): ?array
{
    $fname = (string)($field['name'] ?? '');
    if ($fname === '') {
        return null;
    }

    $args = $field['args'] ?? [];
    if (!is_array($args)) {
        $args = [];
    }

    $varDefs = [];
    $callArgs = [];
    $vars = [];

    foreach ($args as $arg) {
        if (!is_array($arg) || !isset($arg['name'])) {
            continue;
        }

        if (!cb_probe_plan_is_supported_arg($arg)) {
            return null;
        }

        $argName = (string)$arg['name'];
        $t = cb_probe_plan_type_ref((array)($arg['type'] ?? []));
        $kind = (string)$t['kind'];
        $typeName = (string)$t['name'];
        $isNonNull = !empty($t['non_null']);
        if ($typeName === '') {
            return null;
        }

        $guess = cb_probe_plan_guess_value($argName, $kind, $typeName, $idUser, $branchId);
        if ($guess === null) {
            return null;
        }

        $varDefs[] = '$' . $argName . ':' . $typeName . ($isNonNull ? '!' : '');
        $callArgs[] = $argName . ':$' . $argName;
        $vars[$argName] = $guess;
    }

    $ret = cb_probe_plan_type_ref((array)($field['type'] ?? []));
    $retKind = (string)$ret['kind'];
    $selection = '';
    if (!in_array($retKind, ['SCALAR', 'ENUM'], true)) {
        $selection = '{ __typename }';
    }

    $defs = '';
    if (!empty($varDefs)) {
        $defs = '(' . implode(',', $varDefs) . ')';
    }

    $argsTxt = '';
    if (!empty($callArgs)) {
        $argsTxt = '(' . implode(',', $callArgs) . ')';
    }

    $query = 'query' . $defs . '{ ' . $fname . $argsTxt . ' ' . $selection . ' }';

    return [
        'field' => $fname,
        'query' => $query,
        'vars' => $vars,
    ];
}

$token = (string)($_SESSION['cb_token'] ?? '');
$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

if ($token === '' || $idUser <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Chybi session token nebo id_user. Prihlas se a spust znovu.\n";
    exit;
}

cb_probe_plan_out($OUT_FILE, 'RUN_START', [
    'id_user' => $idUser,
    'email' => (string)($cbUser['email'] ?? ''),
]);

$branchId = 0;
try {
    $branchData = cb_smeny_graphql($GQL_URL, 'query{ branchFindAll{ id name } }', [], $token);
    $rows = $branchData['branchFindAll'] ?? [];
    if (is_array($rows) && isset($rows[0]['id'])) {
        $branchId = (int)$rows[0]['id'];
    }
    cb_probe_plan_out($OUT_FILE, 'branchFindAll', $branchData);
} catch (Throwable $e) {
    cb_probe_plan_out($OUT_FILE, 'branchFindAll_ERR', $e->getMessage());
}

try {
    $typeNames = ['ShiftWeek', 'ShiftDay', 'ShiftRole', 'WorkingWeek', 'PreferredWeek', 'ShiftWeekTemplate', 'Branch'];
    foreach ($typeNames as $tn) {
        try {
            $typeDump = cb_smeny_graphql(
                $GQL_URL,
                'query($name:String!){
                    __type(name:$name){
                        name
                        fields{
                            name
                            type{
                                kind
                                name
                                ofType{
                                    kind
                                    name
                                    ofType{
                                        kind
                                        name
                                    }
                                }
                            }
                        }
                    }
                }',
                ['name' => $tn],
                $token
            );
            cb_probe_plan_out($OUT_FILE, 'TYPE_' . $tn, $typeDump);
        } catch (Throwable $e) {
            cb_probe_plan_out($OUT_FILE, 'TYPE_ERR_' . $tn, $e->getMessage());
        }
    }

    $schema = cb_smeny_graphql(
        $GQL_URL,
        'query{
            __schema{
                queryType{
                    fields{
                        name
                        description
                        args{
                            name
                            description
                            type{
                                kind
                                name
                                ofType{
                                    kind
                                    name
                                    ofType{
                                        kind
                                        name
                                        ofType{
                                            kind
                                            name
                                        }
                                    }
                                }
                            }
                        }
                        type{
                            kind
                            name
                            ofType{
                                kind
                                name
                                ofType{
                                    kind
                                    name
                                    ofType{
                                        kind
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }',
        [],
        $token
    );
    cb_probe_plan_out($OUT_FILE, 'INTROSPECTION_QUERY_FIELDS', $schema);

    $fields = $schema['__schema']['queryType']['fields'] ?? [];
    if (!is_array($fields)) {
        $fields = [];
    }

    $candidates = [];
    foreach ($fields as $f) {
        if (!is_array($f)) {
            continue;
        }
        $name = strtolower((string)($f['name'] ?? ''));
        if (
            str_contains($name, 'shift')
            || str_contains($name, 'week')
            || str_contains($name, 'preferred')
        ) {
            $candidates[] = $f;
        }
    }
    cb_probe_plan_out($OUT_FILE, 'CANDIDATES', $candidates);

    foreach ($candidates as $f) {
        $call = cb_probe_plan_build_call($f, $idUser, $branchId);
        $fname = (string)($f['name'] ?? 'unknown');

        if ($call === null) {
            cb_probe_plan_out($OUT_FILE, 'CALL_SKIP_' . $fname, 'unsupported args or type');
            continue;
        }

        cb_probe_plan_out($OUT_FILE, 'CALL_TRY_' . $fname, [
            'query' => $call['query'],
            'vars' => $call['vars'],
        ]);

        try {
            $result = cb_smeny_graphql($GQL_URL, $call['query'], $call['vars'], $token);
            cb_probe_plan_out($OUT_FILE, 'CALL_OK_' . $fname, $result);
        } catch (Throwable $e) {
            cb_probe_plan_out($OUT_FILE, 'CALL_ERR_' . $fname, $e->getMessage());
        }
    }
} catch (Throwable $e) {
    cb_probe_plan_out($OUT_FILE, 'INTROSPECTION_ERR', $e->getMessage());
}

cb_probe_plan_out($OUT_FILE, 'RUN_END', ['ok' => 1]);

header('Content-Type: text/plain; charset=utf-8');
echo "OK - probe zapsan do _kandidati/pomocne/smeny_probe_plan.txt\n";
