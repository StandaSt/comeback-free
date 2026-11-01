<?php
/* Co-Link: pripoji prilohy ke zprave a odmitne prilohu cizi nebo jiz pouzitou. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/../co_db/co_prilohu_ke_zprave_pripojit.php';

/** Neuspech jedine prilohy vyvola chybu a volajici transakce vrati i predchozi zmeny. */
function co_zprava_prilohy_pripojit(int $id, array $files, int $user): void
{
    foreach ($files as $file) {
        $statement = co_prilohu_ke_zprave_pripojit($id, $file, $user);
        if ($statement->affected_rows !== 1) co_chyba('Příloha už není dostupná. Nahrajte ji znovu.', 409);
        $statement->close();
    }
}
