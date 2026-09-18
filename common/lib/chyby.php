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
function cb_chyba_http_status(Throwable $error, array $context = []): int
{
    if (cb_chyba_je_neocekavana($error, $context)) {
        return 500;
    }

    $current = http_response_code();
    if (in_array($current, [400, 401, 403, 404, 409, 422], true)) {
        return $current;
    }

    return 422;
}

/** @param array<string,mixed> $context */
function cb_chyba_json_odesli(Throwable $error, array $context = []): never
{
    http_response_code(cb_chyba_http_status($error, $context));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'err' => cb_chyba_uzivatel($error, $context),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cb_chyba_globalni_format(): string
{
    foreach (headers_list() as $header) {
        $lower = strtolower($header);
        if (str_starts_with($lower, 'content-type: application/json')) {
            return 'json';
        }
        if (
            str_starts_with($lower, 'content-type: text/plain')
            || str_starts_with($lower, 'content-disposition: attachment')
        ) {
            return 'text';
        }
    }

    $jsonHeaders = [
        'HTTP_X_COMEBACK_SET_PERIOD',
        'HTTP_X_COMEBACK_SET_BRANCH',
        'HTTP_X_COMEBACK_SET_BRANCHES',
        'HTTP_X_COMEBACK_SET_PRODLEVA',
        'HTTP_X_COMEBACK_ACTIVE_MODULE',
        'HTTP_X_COMEBACK_THEME',
        'HTTP_X_COMEBACK_HELPDESK',
        'HTTP_X_COMEBACK_ADMIN_EDITACE_PRAV',
        'HTTP_X_COMEBACK_ADMIN_USER_ACTIVATE',
        'HTTP_X_COMEBACK_REPORT_PROMENNE',
        'HTTP_X_COMEBACK_SMENY_PLAN_STATE',
    ];
    foreach ($jsonHeaders as $header) {
        if (isset($_SERVER[$header])) {
            return 'json';
        }
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $uri = strtolower((string)($_SERVER['REQUEST_URI'] ?? ''));
    if (
        str_contains($accept, 'application/json')
        || isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        || str_contains($uri, '/ajax/')
        || str_contains($uri, 'helpdesk_action=')
    ) {
        return 'json';
    }

    return 'html';
}

/** @param array<string,mixed> $context */
function cb_chyba_globalni_vystup(Throwable $error, array $context = []): void
{
    if (!empty($GLOBALS['CB_CHYBA_GLOBALNE_VYRIZENA'])) {
        return;
    }
    $GLOBALS['CB_CHYBA_GLOBALNE_VYRIZENA'] = true;

    try {
        $context += [
            'module' => (string)($GLOBALS['CURRENT_MODULE'] ?? 'SYSTEM'),
            'action' => 'Nezachycená chyba webového požadavku',
        ];
        $message = cb_chyba_uzivatel($error, $context);
        $status = cb_chyba_http_status($error, $context);
        $format = cb_chyba_globalni_format();

        if (!headers_sent()) {
            http_response_code($status);
            header('Cache-Control: no-store');
            if ($format === 'json') {
                header('Content-Type: application/json; charset=utf-8');
            } elseif ($format === 'text') {
                header('Content-Type: text/plain; charset=utf-8');
            } else {
                header('Content-Type: text/html; charset=utf-8');
            }
        }

        if ($format === 'json') {
            echo json_encode(['ok' => false, 'err' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        if ($format === 'text') {
            echo $message;
            return;
        }

        echo '<section role="alert" style="margin:16px;padding:14px;border:1px solid #dc2626;border-radius:8px;color:#991b1b;background:#fff1f2;font:600 14px/1.45 sans-serif">'
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . '</section>';
    } catch (Throwable $handlerError) {
        error_log('[cb_global_error_handler_failed] ' . get_class($handlerError) . ': ' . $handlerError->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo cb_chyba_verejna_zprava();
    }
}

function cb_chyba_globalni_exception(Throwable $error): void
{
    cb_chyba_globalni_vystup($error);
}

function cb_chyba_globalni_shutdown(): void
{
    if (!empty($GLOBALS['CB_CHYBA_GLOBALNE_VYRIZENA'])) {
        return;
    }
    $last = error_get_last();
    if (!is_array($last) || !in_array((int)($last['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    $error = new ErrorException(
        (string)($last['message'] ?? 'Fatální chyba aplikace.'),
        0,
        (int)($last['type'] ?? E_ERROR),
        (string)($last['file'] ?? ''),
        (int)($last['line'] ?? 0)
    );
    cb_chyba_globalni_vystup($error, ['action' => 'Fatální chyba webového požadavku']);
}

function cb_chyba_web_bootstrap(): void
{
    if (PHP_SAPI === 'cli' || !empty($GLOBALS['CB_CHYBA_WEB_BOOTSTRAP'])) {
        return;
    }
    $GLOBALS['CB_CHYBA_WEB_BOOTSTRAP'] = true;
    $GLOBALS['CB_CHYBA_GLOBALNE_VYRIZENA'] = false;
    ini_set('display_errors', '0');
    set_exception_handler('cb_chyba_globalni_exception');
    register_shutdown_function('cb_chyba_globalni_shutdown');
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

/** Vrátí pouze bezpečnou část URL; tokeny ani libovolný query string se nelogují. */
function cb_chyba_bezpecna_url(string $requestUri): string
{
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return '';
    }

    $query = parse_url($requestUri, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return $path;
    }

    parse_str($query, $params);
    $allowedKeys = ['m', 'page', 'hd', 'helpdesk_action', 'cb_helpdesk_module'];
    $safe = [];
    foreach ($allowedKeys as $key) {
        $value = $params[$key] ?? null;
        if (!is_scalar($value)) {
            continue;
        }
        $value = trim((string)$value);
        if ($value !== '') {
            $safe[$key] = mb_substr($value, 0, 80, 'UTF-8');
        }
    }

    return $safe === []
        ? $path
        : $path . '?' . http_build_query($safe, '', '&', PHP_QUERY_RFC3986);
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
        $url = cb_chyba_bezpecna_url((string)($_SERVER['REQUEST_URI'] ?? ''));
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

            $pushDelivered = db_zapis_log_chyby(
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
            if ($sendPush && !$pushDelivered) {
                error_log('[cb_error_push_not_delivered] ' . $adminMessage);
            }
        } catch (Throwable $logError) {
            error_log('[cb_error_report_failed] ' . get_class($logError) . ': ' . $logError->getMessage());
        }

        if (!$dbLogCompleted && !$pushSuppressedAsDuplicate) {
            // Když selže zápis do log_chyby, zkusí se push samostatně. Také tato
            // cesta může při úplném výpadku DB selhat, nesmí však vyvolat rekurzi.
            try {
                require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
                $pushDelivered = cb_push_send_error_admin($adminMessage, $error->getFile(), $error->getLine(), 1);
                if (!$pushDelivered) {
                    error_log('[cb_error_push_not_delivered] ' . $adminMessage);
                }
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
