<?php
// admin_testy/st.php * Verze: V3 * Aktualizace: 15.3.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const CB_ST_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_ST_OUT_FILE = __DIR__ . '/../_kandidati/smeny_tyden.txt';
const CB_ST_DEFAULT_ID_POB = 6;

function cb_st_write(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        $json = "{\n  \"ok\": 0,\n  \"chyba\": \"json_encode selhal\"\n}";
    }

    file_put_contents(CB_ST_OUT_FILE, $json . "\n", LOCK_EX);
}

function cb_st_find_db_branch_by_kod(string $kod): ?array
{
    $kod = trim($kod);
    if ($kod === '') {
        return null;
    }

    $stmt = db()->prepare('SELECT id_pob, kod, nazev FROM pobocka WHERE kod=? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Nepodařilo se připravit dotaz na pobocka.');
    }

    $stmt->bind_param('s', $kod);
    $stmt->execute();
    $stmt->bind_result($idPob, $kodDb, $nazev);

    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        return null;
    }

    return [
        'id_pob' => (int)$idPob,
        'kod' => (string)$kodDb,
        'nazev' => (string)$nazev,
    ];
}

function cb_st_day_offset(string $day): int
{
    if ($day === 'monday') {
        return 0;
    }
    if ($day === 'tuesday') {
        return 1;
    }
    if ($day === 'wednesday') {
        return 2;
    }
    if ($day === 'thursday') {
        return 3;
    }
    if ($day === 'friday') {
        return 4;
    }
    if ($day === 'saturday') {
        return 5;
    }
    if ($day === 'sunday') {
        return 6;
    }

    throw new RuntimeException('Neznámý den ze Směn: ' . $day);
}

function cb_st_datum(string $startDay, string $day): string
{
    $dt = new DateTimeImmutable($startDay);
    $offset = cb_st_day_offset($day);

    if ($offset > 0) {
        $dt = $dt->modify('+' . $offset . ' day');
    }

    return $dt->format('Y-m-d');
}

function cb_st_log(?callable $logger, string $text): void
{
    if ($logger !== null) {
        $logger($text);
    }
}

function cb_st_import_week(array $params = [], ?callable $logger = null): array
{
    $report = [
        'ok' => 0,
        'cas' => date('Y-m-d H:i:s'),
        'soubor' => 'admin_testy/st.php',
        'vystup' => '_kandidati/smeny_tyden.txt',
        'uzivatel' => [
            'id_user' => (int)($_SESSION['cb_user']['id_user'] ?? 0),
            'email' => (string)($_SESSION['cb_user']['email'] ?? ''),
        ],
        'prubeh' => [],
    ];

    try {
        cb_st_log($logger, 'START importu týdenních směn');
        $report['prubeh'][] = 'START importu týdenních směn';

        $token = (string)($_SESSION['cb_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Chybí session token cb_token. Přihlas se a spusť test znovu.');
        }

        $idPob = 0;
        $zdroj = '';
        $kod = '';

        if (isset($params['id_pob']) && trim((string)$params['id_pob']) !== '') {
            $idPob = (int)$params['id_pob'];
            $zdroj = 'param id_pob';
        }

        if ($idPob <= 0 && isset($params['kod']) && trim((string)$params['kod']) !== '') {
            $kod = trim((string)$params['kod']);

            if ($kod === 'vsechny') {
                throw new RuntimeException('Pro tento test vyber konkrétní pobočku, ne Všechny.');
            }

            $dbBranch = cb_st_find_db_branch_by_kod($kod);

            if ($dbBranch === null) {
                throw new RuntimeException('Pobočka pro zadaný kod v DB neexistuje.');
            }

            $idPob = (int)$dbBranch['id_pob'];
            $zdroj = 'param kod -> DB';
            $report['pobocka_db'] = $dbBranch;
        }

        if ($idPob <= 0) {
            $idPob = CB_ST_DEFAULT_ID_POB;
            $zdroj = 'default id_pob=6';
        }

        $report['pobocka_hledani'] = [
            'zdroj' => $zdroj,
            'id_pob' => $idPob,
            'kod' => $kod,
        ];

        cb_st_log($logger, 'Pobočka: id_pob=' . (string)$idPob . ' (' . $zdroj . ')');
        $report['prubeh'][] = 'Pobočka: id_pob=' . (string)$idPob . ' (' . $zdroj . ')';

        $query = <<<'GQL'
query($skipWeeks:Int!, $branchId:Int!){
  branchGetShiftWeek(skipWeeks:$skipWeeks, branchId:$branchId){
    id
    startDay
    shiftDays{
      id
      day
      shiftRoles{
        id
        halfHour
        firstHour
        type{
          id
        }
        shiftHours{
          id
          startHour
          confirmed
          isFirst
          employee{
            id
          }
        }
      }
    }
  }
}
GQL;

        $vars = [
            'skipWeeks' => 0,
            'branchId' => $idPob,
        ];

        cb_st_log($logger, 'Stahuji týden ze Směn...');
        $report['prubeh'][] = 'Stahuji týden ze Směn';

        $weekData = cb_smeny_graphql(
            CB_ST_GQL_URL,
            $query,
            $vars,
            $token,
            60
        );

        $week = $weekData['branchGetShiftWeek'] ?? null;
        if (!is_array($week)) {
            throw new RuntimeException('branchGetShiftWeek nevrátil data.');
        }

        $startDay = (string)($week['startDay'] ?? '');
        if ($startDay === '') {
            throw new RuntimeException('Ve výsledku chybí startDay.');
        }

        $smenyWeekId = (int)($week['id'] ?? 0);
        cb_st_log($logger, 'Týden ID: ' . (string)$smenyWeekId . ' | startDay: ' . $startDay);
        $report['prubeh'][] = 'Týden ID: ' . (string)$smenyWeekId . ' | startDay: ' . $startDay;

        $report['graphql'] = [
            'variables' => $vars,
        ];
        $report['tyden'] = [
            'smeny_week_id' => $smenyWeekId,
            'startDay' => $startDay,
        ];

        $sql = 'INSERT INTO smeny_akceptovane (
            smeny_week_id,
            smeny_day_id,
            smeny_role_id,
            shift_hour_id,
            datum,
            den,
            id_pob,
            id_slot,
            start_hour,
            confirmed,
            is_first,
            id_user
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = db()->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nepodařilo se připravit INSERT do smeny_akceptovane.');
        }

        db()->begin_transaction();

        $dny = 0;
        $roleBloky = 0;
        $hodinyCelkem = 0;
        $hodinyObsazene = 0;
        $hodinyNeobsazene = 0;

        foreach (($week['shiftDays'] ?? []) as $shiftDay) {
            if (!is_array($shiftDay)) {
                continue;
            }

            $smenyDayId = (int)($shiftDay['id'] ?? 0);
            $den = (string)($shiftDay['day'] ?? '');
            $datum = cb_st_datum($startDay, $den);
            $dny++;

            cb_st_log($logger, 'Den: ' . $datum . ' (' . $den . ')');
            $report['prubeh'][] = 'Den: ' . $datum . ' (' . $den . ')';

            foreach (($shiftDay['shiftRoles'] ?? []) as $shiftRole) {
                if (!is_array($shiftRole)) {
                    continue;
                }

                $roleBloky++;
                $smenyRoleId = (int)($shiftRole['id'] ?? 0);
                $idSlot = (int)($shiftRole['type']['id'] ?? 0);

                foreach (($shiftRole['shiftHours'] ?? []) as $shiftHour) {
                    if (!is_array($shiftHour)) {
                        continue;
                    }

                    $shiftHourId = (int)($shiftHour['id'] ?? 0);
                    $startHour = (int)($shiftHour['startHour'] ?? 0);
                    $confirmed = !empty($shiftHour['confirmed']) ? 1 : 0;
                    $isFirst = !empty($shiftHour['isFirst']) ? 1 : 0;
                    $idUser = isset($shiftHour['employee']['id']) ? (int)$shiftHour['employee']['id'] : null;

                    if ($idUser === null) {
                        $hodinyNeobsazene++;
                    } else {
                        $hodinyObsazene++;
                    }

                    $stmt->bind_param(
                        'iiiissiiiiii',
                        $smenyWeekId,
                        $smenyDayId,
                        $smenyRoleId,
                        $shiftHourId,
                        $datum,
                        $den,
                        $idPob,
                        $idSlot,
                        $startHour,
                        $confirmed,
                        $isFirst,
                        $idUser
                    );

                    if (!$stmt->execute()) {
                        throw new RuntimeException('INSERT selhal: ' . $stmt->error);
                    }

                    $hodinyCelkem++;
                }
            }
        }

        db()->commit();
        $stmt->close();

        cb_st_log($logger, 'Uloženo hodin: ' . (string)$hodinyCelkem);
        cb_st_log($logger, 'Obsazené hodiny: ' . (string)$hodinyObsazene);
        cb_st_log($logger, 'Neobsazené hodiny: ' . (string)$hodinyNeobsazene);
        cb_st_log($logger, 'KONEC importu');

        $report['ok'] = 1;
        $report['souhrn'] = [
            'dny' => $dny,
            'role_bloky' => $roleBloky,
            'hodiny_celkem' => $hodinyCelkem,
            'hodiny_obsazene' => $hodinyObsazene,
            'hodiny_neobsazene' => $hodinyNeobsazene,
        ];

        cb_st_write($report);
        return $report;

    } catch (Throwable $e) {
        if (db()->errno === 0) {
            try {
                db()->rollback();
            } catch (Throwable $rollbackError) {
            }
        } else {
            try {
                db()->rollback();
            } catch (Throwable $rollbackError) {
            }
        }

        $report['ok'] = 0;
        $report['chyba'] = $e->getMessage();
        $report['typ_chyby'] = get_class($e);

        cb_st_log($logger, '');
        cb_st_log($logger, 'CHYBA');
        cb_st_log($logger, $e->getMessage());

        cb_st_write($report);
        return $report;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    header('Content-Type: text/plain; charset=utf-8');

    $report = cb_st_import_week($_GET, static function (string $line): void {
        echo $line . "\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    });

    if (!empty($report['ok'])) {
        echo "\nOK\n";
        echo 'Detail zapsán do _kandidati/smeny_tyden.txt' . "\n";
    } else {
        echo "\nCHYBA\n";
        echo 'Detail zapsán do _kandidati/smeny_tyden.txt' . "\n";
    }
}
