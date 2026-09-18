<?php
declare(strict_types=1);

/* Jednotná hranice očekávaných a technických chyb modulu HR. */

/** @param array<string,mixed> $context */
function cb_hr_chyba_context(string $action, array $context = []): array
{
    return array_merge([
        'module' => 'HR',
        'action' => $action,
    ], $context);
}

/** @param array<string,mixed> $context */
function cb_hr_chyba_text(Throwable $error, string $action, array $context = []): string
{
    return cb_chyba_uzivatel($error, cb_hr_chyba_context($action, $context));
}

