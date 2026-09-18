<?php
declare(strict_types=1);

/* Jednotná hranice očekávaných a technických chyb modulu Administrace. */

/** @param array<string,mixed> $context */
function cb_admin_chyba_context(string $action, array $context = []): array
{
    return array_merge([
        'module' => 'ADMINISTRACE',
        'action' => $action,
    ], $context);
}

/** @param array<string,mixed> $context */
function cb_admin_chyba_text(Throwable $error, string $action, array $context = []): string
{
    return cb_chyba_uzivatel($error, cb_admin_chyba_context($action, $context));
}

/** @param array<string,mixed> $context */
function cb_admin_chyba_status(Throwable $error, array $context = []): int
{
    $status = http_response_code();
    if ($status === 401 || $status === 403) {
        return $status;
    }
    return cb_chyba_je_neocekavana($error, $context) ? 500 : 422;
}

function cb_admin_chyba_audit(callable $audit): void
{
    try {
        $audit();
    } catch (Throwable $auditError) {
        error_log('[cb_admin_error_audit_failed] ' . get_class($auditError) . ': ' . $auditError->getMessage());
    }
}

/** @param array<string,mixed> $context */
function cb_admin_json_chyba(Throwable $error, string $action, array $context = []): never
{
    http_response_code(cb_admin_chyba_status($error, $context));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'err' => cb_admin_chyba_text($error, $action, $context),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
