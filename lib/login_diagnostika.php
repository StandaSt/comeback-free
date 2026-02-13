<?php
// lib/login_diagnostika.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 63
declare(strict_types=1);

/*
 * Diagnostický log pro přihlašování (textový log do /log/error.log)
 *
 * Účel:
 * - mít čitelný log kroků přihlášení a chyb (vývoj/diagnostika)
 *
 * Bezpečnost:
 * - heslo se nikdy nezapisuje
 */

function cb_login_log_line(string $step, array $ctx = [], ?Throwable $e = null): void
{
    $dir = __DIR__ . '/../log';
    @mkdir($dir, 0775, true);
    $file = $dir . '/error.log';

    // Bezpečnost: heslo se sem nikdy nesmí dostat.
    if (isset($ctx['heslo'])) $ctx['heslo'] = '[HIDDEN]';
    if (isset($ctx['password'])) $ctx['password'] = '[HIDDEN]';

    $ts     = date('Y-m-d H:i:s');
    $uri    = (string)($_SERVER['REQUEST_URI'] ?? '');
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    $ip     = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $sid    = (string)session_id();

    $ctxParts = [];
    foreach ($ctx as $k => $v) {
        if (is_bool($v)) $v = $v ? 'true' : 'false';
        elseif ($v === null) $v = 'null';
        elseif (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
        else $v = (string)$v;

        $v = str_replace(["\r", "\n"], [' ', ' '], $v);
        $ctxParts[] = $k . '=' . $v;
    }
    $ctxTxt = $ctxParts ? (' | ' . implode(' | ', $ctxParts)) : '';

    $exTxt = '';
    if ($e) {
        $exTxt =
            ' | EX=' . get_class($e) .
            ' | MSG=' . str_replace(["\r", "\n"], [' ', ' '], $e->getMessage()) .
            ' | AT=' . basename($e->getFile()) . ':' . $e->getLine();
    }

    $line = $ts . ' | ' . $step .
        ' | ' . $method .
        ' | ' . $ip .
        ' | sid=' . $sid .
        ' | uri=' . $uri .
        $ctxTxt .
        $exTxt .
        PHP_EOL;

    @file_put_contents($file, $line, FILE_APPEND);
}
// lib/login_diagnostika.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 63
// konec souboru