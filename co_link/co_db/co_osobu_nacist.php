<?php
/* Co-Link: jednim SELECT nacte aktivni osobu a jmeno ze spolecneho HR zdroje. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Identita i platnost firemniho uctu se overuje pri kazdem pozadavku. */
function co_osoba(int $id): array
{
    return co_rows('SELECT u.id_user,p.id_person,p.id_firma,
        COALESCE(' . cb_osoba_jmeno_sql('p.id_person') . ',u.email) jmeno
        FROM user u JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user AND p.aktivni=1 WHERE u.id_user=?', [$id])[0] ?? [];
}
