<?php
declare(strict_types=1);

/**
 * Spolecna pravidla pro parovani celeho jmena z externich zdroju s id_user.
 * Uznavaji se jen obe poradi celeho jmena, rozdilna diakritika, velikost
 * pismen a mezery. Pri shode vice ID ma aktivni uzivatel prednost.
 */
function cb_person_name_match_key(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = strtr($name, [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'ë' => 'e', 'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o',
        'ö' => 'o', 'ő' => 'o', 'ř' => 'r', 'ŕ' => 'r', 'š' => 's', 'ť' => 't',
        'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'ž' => 'z',
    ]);

    return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
}

/**
 * @param array<int,array{id_user:int,aktivni:int,jmeno:string,prijmeni:string}> $candidates
 * @return array<string,array<int,array<string,mixed>>>
 */
function cb_person_name_index(array $candidates): array
{
    $index = [];
    foreach ($candidates as $candidate) {
        $idUser = (int)($candidate['id_user'] ?? 0);
        $firstName = trim((string)($candidate['jmeno'] ?? ''));
        $lastName = trim((string)($candidate['prijmeni'] ?? ''));
        if ($idUser <= 0 || $firstName === '' || $lastName === '') {
            continue;
        }
        $candidate['names'] = [$firstName . ' ' . $lastName, $lastName . ' ' . $firstName];
        foreach ($candidate['names'] as $name) {
            $key = cb_person_name_match_key((string)$name);
            if ($key !== '') {
                $index[$key][$idUser] = $candidate;
            }
        }
    }

    return $index;
}

/**
 * @param array<string,array<int,array<string,mixed>>> $index
 * @return array{status:string,user:?array,match_code:string}
 */
function cb_person_name_resolve(string $name, array $index): array
{
    $key = cb_person_name_match_key($name);
    $matches = $key === '' ? [] : array_values($index[$key] ?? []);
    if ($matches === []) {
        return ['status' => 'nenalezen', 'user' => null, 'match_code' => ''];
    }

    $active = array_values(array_filter(
        $matches,
        static fn(array $candidate): bool => (int)($candidate['aktivni'] ?? 0) === 1
    ));
    $pool = $active !== [] ? $active : $matches;
    if (count($pool) !== 1) {
        return ['status' => 'nejednoznacny', 'user' => null, 'match_code' => ''];
    }

    $user = $pool[0];
    $exact = in_array($name, (array)($user['names'] ?? []), true);
    return [
        'status' => 'shoda',
        'user' => $user,
        'match_code' => $exact ? '' : '2',
    ];
}
