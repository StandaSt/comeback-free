<?php
declare(strict_types=1);

/*
 * Datova vrstva kategorie AI analytik v Administraci.
 * Cte pouze neuspesne nebo odmitnute audity a nevraci prompt, SQL ani tokeny.
 */

/** Nacte posledni neuspesne AI audity v bezpecnem administracnim rozsahu. */
function cb_admin_ai_chyby_nacti(mysqli $db, int $limit): array
{
    $limit = in_array($limit, [20, 50, 100, 250], true) ? $limit : 50;
    $result = $db->query('
        SELECT a.id_ai_analytik_audit, a.created_at, a.completed_at,
               a.id_user, a.model, a.duration_ms, a.status,
               a.error_type, a.error_code, a.error_message,
               u.jmeno, u.prijmeni, u.email
        FROM ai_analytik_audit a
        LEFT JOIN user u ON u.id_user = a.id_user
        WHERE a.status <> \'cancelled\'
          AND (
            a.error_message IS NOT NULL
            OR a.status IN (
                \'error\', \'fatal_error\', \'security_block\',
                \'security_block_error\', \'model_permission_denied\',
                \'rejected_request\', \'limit_exceeded\', \'connection_lost\'
            )
          )
        ORDER BY a.created_at DESC, a.id_ai_analytik_audit DESC
        LIMIT ' . $limit
    );
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst přehled chyb AI analytika.');
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

/** Prelozi technicky stav AI auditu do kratkeho ceskeho popisu. */
function cb_admin_ai_chyba_stav(string $status): string
{
    return match ($status) {
        'fatal_error' => 'Fatální chyba PHP',
        'security_block' => 'Bezpečnostní blokace',
        'security_block_error' => 'Selhání bezpečnostní blokace',
        'model_permission_denied' => 'Nepovolený model',
        'rejected_request' => 'Odmítnutý požadavek',
        'limit_exceeded' => 'Překročený limit analýzy',
        'connection_lost' => 'Přerušené spojení',
        default => 'Technická chyba',
    };
}
