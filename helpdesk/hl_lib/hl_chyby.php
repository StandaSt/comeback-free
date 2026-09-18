<?php
declare(strict_types=1);

/*
 * Jednotná hranice chyb HelpDesku.
 * Očekávaná CbUserVisibleException zůstane konkrétní bez admin push.
 * Každá jiná výjimka dostane bezpečný veřejný text a centrální hlášení adminovi.
 */

/** @param array<string,mixed> $context */
function cb_helpdesk_chyba_context(string $action, array $context = []): array
{
    return array_merge([
        'module' => 'HELPDESK',
        'action' => $action,
    ], $context);
}

/** @param array<string,mixed> $context */
function cb_helpdesk_json_chyba(Throwable $error, string $action, array $context = []): never
{
    $unexpected = cb_chyba_je_neocekavana($error, $context);
    http_response_code($unexpected ? 500 : 422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'err' => cb_chyba_uzivatel($error, cb_helpdesk_chyba_context($action, $context)),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** @param array<string,mixed> $context */
function cb_helpdesk_form_chyba(Throwable $error, string $action, array $context = []): string
{
    return cb_chyba_uzivatel($error, cb_helpdesk_chyba_context($action, $context));
}

