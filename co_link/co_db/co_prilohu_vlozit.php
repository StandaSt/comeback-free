<?php
/* Co-Link: jediny INSERT pro evidenci ulozene prilohy; vrati ID z posledniho vlozeni bez dalsiho dotazu. */
declare(strict_types=1);

/** Eviduje vlastnika a metadata; soubor musi byt ulozen pred volanim teto operace. */
function co_prilohu_vlozit(int $user, string $name, string $stored, string $mime, int $size): int
{
    co_sql('INSERT INTO co_priloha(vlastnik,nazev,soubor,mime,velikost) VALUES(?,?,?,?,?)', [$user, $name, $stored, $mime, $size])->close();
    return (int)db()->insert_id;
}
