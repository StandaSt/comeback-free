<?php
/* Co-Link: databazove dotazy pro uklid nepripojenych priloh a davno expirovanych QR; spojeni pripravuje volajici. */
declare(strict_types=1);

/** Kandidati maji stejnou dvoudenni rezervu jako puvodni uklid; pripojene prilohy se nevybiraji. */
function co_uklid_db_nepripojene(): array
{
    return co_rows('SELECT id,soubor FROM co_priloha WHERE zprava IS NULL AND vytvoreno<DATE_SUB(NOW(),INTERVAL 2 DAY)');
}

/** Kontrola pod zamkem brani smazani prilohy, kterou mezitim nekdo pripojil ke zprave. */
function co_uklid_db_prilohu_zamknout(int $id): array
{
    return co_rows('SELECT id,soubor FROM co_priloha WHERE id=? AND zprava IS NULL FOR UPDATE', [$id])[0] ?? [];
}

/** Zaznam se odstranuje az po kontrole pod zamkem a pripadnem odstraneni souboru. */
function co_uklid_db_prilohu_smazat(int $id): void
{
    co_sql('DELETE FROM co_priloha WHERE id=?', [$id])->close();
}

/** QR zaznamy se odstrani az dva dny po konci platnosti; pocet slouzi souhrnu cronu. */
function co_uklid_db_qr_smazat(): int
{
    $statement = co_sql('DELETE FROM co_qr WHERE platnost_do<DATE_SUB(NOW(),INTERVAL 2 DAY)');
    $count = $statement->affected_rows;
    $statement->close();
    return $count;
}
