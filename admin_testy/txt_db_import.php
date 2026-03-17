<?php
// admin_testy/txt_db_import.php * Verze: V2 * Aktualizace: 16.3.2026
declare(strict_types=1);

const CB_TXT_DB_IMPORT_FILE = __DIR__ . '/../_kandidati/smeny_import.txt';

function cb_txt_db_import(?callable $logger = null): array
{
    $report = [
        'ok' => 0,
        'vlozeno' => 0,
        'preskoceno' => 0,
        'duplicitni' => 0,
        'chyba' => '',
    ];

    try {
        if (!file_exists(CB_TXT_DB_IMPORT_FILE)) {
            throw new RuntimeException('Soubor _kandidati/smeny_import.txt neexistuje.');
        }

        $rows = file(CB_TXT_DB_IMPORT_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($rows === false) {
            throw new RuntimeException('Soubor _kandidati/smeny_import.txt nelze načíst.');
        }

        if ($logger !== null) {
            $logger('START importu TXT -> DB');
            $logger('Soubor: _kandidati/smeny_import.txt');
        }

        foreach ($rows as $line) {
            if (strpos($line, 'start_day=') === false) {
                $report['preskoceno']++;
                continue;
            }

            $parts = explode('|', $line);
            $startDay = '';
            $idPob = 0;

            foreach ($parts as $part) {
                $part = trim($part);

                if (strpos($part, 'start_day=') === 0) {
                    $startDay = trim(substr($part, 10));
                }

                if (strpos($part, 'id_pob=') === 0) {
                    $idPob = (int)trim(substr($part, 7));
                }
            }

            if ($startDay === '' || $idPob <= 0) {
                $report['preskoceno']++;
                continue;
            }

            $sql = "INSERT IGNORE INTO smeny_import (id_pob, start_day, import)
                    VALUES ($idPob, '" . addslashes($startDay) . "', 0)";

            $ok = db()->query($sql);
            if ($ok !== true) {
                throw new RuntimeException('INSERT do smeny_import selhal: ' . db()->error);
            }

            if (db()->affected_rows > 0) {
                $report['vlozeno']++;
                if ($logger !== null) {
                    $logger('Vloženo: id_pob=' . (string)$idPob . ' | start_day=' . $startDay);
                }
            } else {
                $report['duplicitni']++;
                if ($logger !== null) {
                    $logger('Duplicitní, přeskočeno: id_pob=' . (string)$idPob . ' | start_day=' . $startDay);
                }
            }
        }

        $report['ok'] = 1;

        if ($logger !== null) {
            $logger('HOTOVO');
            $logger('Vloženo: ' . (string)$report['vlozeno']);
            $logger('Duplicitní: ' . (string)$report['duplicitni']);
            $logger('Přeskočeno: ' . (string)$report['preskoceno']);
        }

        return $report;

    } catch (Throwable $e) {
        $report['chyba'] = $e->getMessage();

        if ($logger !== null) {
            $logger('CHYBA: ' . $e->getMessage());
        }

        return $report;
    }
}
