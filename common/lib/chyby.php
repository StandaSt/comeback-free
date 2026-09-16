<?php
// lib/chyby.php * Jednotne zpracovani neocekavanych chyb IS
declare(strict_types=1);

/*
 * Verejny vystup nikdy neprozrazuje technicky detail.
 * Admin dostane bezpecny popis pres stavajici log_chyby a Web Push.
 * Chybove hlaseni nesmi samo shodit puvodni pozadavek ani vytvorit rekurzi.
 */

function cb_chyba_verejna_zprava(): string
{
    return 'Je nám líto, vyskytla se chyba, admin již byl informován.';
}

final class CbUserVisibleException extends RuntimeException
{
}

/**
 * Nový kód používá CbUserVisibleException pro očekávané zprávy. Přechodný
 * allow_runtime_message zachovává starší formuláře, dokud se nepřevedou.
 */
function cb_chyba_je_neocekavana(Throwable $error, array $context = []): bool
{
    if ($error instanceof CbUserVisibleException) {
        return false;
    }
    if (!empty($context['allow_runtime_message']) && get_class($error) === RuntimeException::class) {
        return false;
    }
    return true;
}

/** @param array<string,mixed> $context */
function cb_chyba_uzivatel(Throwable $error, array $context = []): string
{
    if (!cb_chyba_je_neocekavana($error, $context)) {
        return $error->getMessage();
    }

    cb_chyba_oznam($error, $context);
    return cb_chyba_verejna_zprava();
}

/** @param array<string,mixed> $context */
function cb_chyba_iniciator(array $context = []): array
{
    $sessionUser = $_SESSION['cb_user'] ?? null;
    $idUser = is_array($sessionUser) ? (int)($sessionUser['id_user'] ?? 0) : 0;
    $name = '';
    $email = '';

    if (is_array($sessionUser)) {
        $name = trim(
            (string)($sessionUser['jmeno'] ?? $sessionUser['name'] ?? '')
            . ' '
            . (string)($sessionUser['prijmeni'] ?? $sessionUser['surname'] ?? '')
        );
        $email = trim((string)($sessionUser['email'] ?? ''));
    }

    $contextActor = trim((string)($context['actor'] ?? ''));
    if ($contextActor !== '') {
        $email = $contextActor;
    }

    $parts = [];
    if ($idUser > 0) {
        $parts[] = 'ID ' . $idUser;
    }
    if ($name !== '') {
        $parts[] = $name;
    }
    if ($email !== '') {
        $parts[] = $email;
    }

    $label = $parts !== [] ? implode(' / ', $parts) : 'nepřihlášený uživatel';
    $label = preg_replace('/[\r\n\t]+/u', ' ', $label) ?? $label;
    if (mb_strlen($label, 'UTF-8') > 90) {
        $label = mb_substr($label, 0, 87, 'UTF-8') . '...';
    }

    return ['id_user' => $idUser > 0 ? $idUser : null, 'label' => $label];
}

/** @param array<string,mixed> $context */
function cb_chyba_admin_popis(Throwable $error, array $context = []): string
{
    $table = trim((string)($context['table'] ?? ''));
    $message = trim($error->getMessage());

    if ($error instanceof mysqli_sql_exception) {
        if (preg_match("~Unknown column '([^']+)'~i", $message, $match) === 1) {
            $column = (string)$match[1];
            if (str_contains($column, '.')) {
                [, $column] = array_pad(explode('.', $column, 2), 2, $column);
            }
            return $table !== ''
                ? 'Chyba databáze: v tabulce ' . $table . ' chybí sloupec ' . $column . '.'
                : 'Chyba databáze: chybí sloupec ' . $column . '.';
        }
        if (preg_match("~Table '[^']*\.([^']+)' doesn't exist~i", $message, $match) === 1) {
            return 'Chyba databáze: chybí tabulka ' . (string)$match[1] . '.';
        }

        return match ((int)$error->getCode()) {
            1045 => 'Chyba databáze: přístup k databázi byl odmítnut.',
            1049 => 'Chyba databáze: požadovaná databáze neexistuje.',
            1062 => 'Chyba databáze: byla nalezena duplicitní hodnota.',
            1064 => 'Chyba databáze: SQL dotaz má neplatnou syntaxi.',
            1048 => 'Chyba databáze: povinná hodnota chybí.',
            1205 => 'Chyba databáze: vypršel čas čekání na databázový zámek.',
            1213 => 'Chyba databáze: operace byla přerušena kvůli souběžné změně dat.',
            1265, 1366 => 'Chyba databáze: hodnota má neočekávaný formát nebo typ.',
            1451 => 'Chyba databáze: záznam nelze odstranit, protože je používán jinde.',
            1452 => 'Chyba databáze: chybí navázaný záznam.',
            2002, 2003, 2006, 2013 => 'Chyba databáze: spojení s databází není dostupné.',
            default => 'Chyba databáze: ' . ($message !== '' ? $message : 'neznámá databázová chyba.'),
        };
    }

    if ($error instanceof TypeError) {
        return 'Chyba aplikace: neočekávaný datový typ. ' . $message;
    }

    return get_class($error) . ': ' . ($message !== '' ? $message : 'neočekávaná chyba aplikace.');
}

/** @param array<string,mixed> $context */
function cb_chyba_oznam(Throwable $error, array $context = []): void
{
    static $reporting = false;
    if ($reporting) {
        return;
    }
    $reporting = true;

    try {
        $actor = cb_chyba_iniciator($context);
        $module = strtoupper(trim((string)($context['module'] ?? $GLOBALS['CURRENT_MODULE'] ?? 'SYSTEM')));
        if ($module === '') {
            $module = 'SYSTEM';
        }
        $action = trim((string)($context['action'] ?? 'Neznámá operace'));
        if (mb_strlen($action, 'UTF-8') > 60) {
            $action = mb_substr($action, 0, 57, 'UTF-8') . '...';
        }
        $adminDescription = cb_chyba_admin_popis($error, $context);
        $adminMessage = $action . ' | uživatel: ' . (string)$actor['label'] . ' | ' . $adminDescription;
        if (mb_strlen($adminMessage, 'UTF-8') > 255) {
            $adminMessage = mb_substr($adminMessage, 0, 252, 'UTF-8') . '...';
        }
        $url = (string)($_SERVER['REQUEST_URI'] ?? '');
        $code = 'UNEXPECTED_' . strtoupper((new ReflectionClass($error))->getShortName());
        if ($error instanceof mysqli_sql_exception && $error->getCode() !== 0) {
            $code .= '_' . (string)$error->getCode();
        }
        $detail = get_class($error)
            . ': ' . $error->getMessage()
            . ' in ' . $error->getFile()
            . ':' . (string)$error->getLine();

        error_log('[cb_error] ' . $adminMessage . ' | ' . $detail . ' | URL: ' . $url);

        $dbLogCompleted = false;
        $pushSuppressedAsDuplicate = false;
        try {
            require_once __DIR__ . '/../db/zapis_log_chyby.php';
            $conn = db();
            $sendPush = true;

            try {
                $stmt = $conn->prepare(
                    'SELECT 1 FROM log_chyby
                     WHERE kod=? AND soubor=? AND radek=? AND kdy >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                     LIMIT 1'
                );
                if ($stmt !== false) {
                    $file = $error->getFile();
                    $line = $error->getLine();
                    $stmt->bind_param('ssi', $code, $file, $line);
                    $stmt->execute();
                    $sendPush = $stmt->get_result()->fetch_row() === null;
                    $stmt->close();
                }
            } catch (Throwable $dedupeError) {
                $sendPush = true;
            }

            $safeContext = [
                'actor' => (string)$actor['label'],
                'table' => trim((string)($context['table'] ?? '')),
                'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            ];
            $dataJson = json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            db_zapis_log_chyby(
                $conn,
                $actor['id_user'],
                $module,
                $action,
                $code,
                $adminMessage,
                $detail,
                $error->getFile(),
                $error->getLine(),
                $url !== '' ? $url : null,
                is_string($dataJson) ? $dataJson : null,
                0,
                null,
                $sendPush
            );
            $dbLogCompleted = true;
            $pushSuppressedAsDuplicate = !$sendPush;
        } catch (Throwable $logError) {
            error_log('[cb_error_report_failed] ' . get_class($logError) . ': ' . $logError->getMessage());
        }

        if (!$dbLogCompleted && !$pushSuppressedAsDuplicate) {
            // Když selže zápis do log_chyby, zkusí se push samostatně. Také tato
            // cesta může při úplném výpadku DB selhat, nesmí však vyvolat rekurzi.
            try {
                require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
                cb_push_send_error_admin($adminMessage, $error->getFile(), $error->getLine(), 1);
            } catch (Throwable $pushError) {
                error_log('[cb_error_push_failed] ' . get_class($pushError) . ': ' . $pushError->getMessage());
            }
        }
    } catch (Throwable $reportError) {
        error_log('[cb_error_fallback] ' . get_class($reportError) . ': ' . $reportError->getMessage());
    } finally {
        $reporting = false;
    }
}
