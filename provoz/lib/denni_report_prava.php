<?php
// lib/denni_report_prava.php * Opravneni denniho reportu
declare(strict_types=1);

const CB_DENNI_REPORT_ZOBRAZIT_PRAVO = 201;
const CB_DENNI_REPORT_EDITOVAT_PRAVO = 203;
const CB_DENNI_REPORT_UZAVRIT_PRAVO = 204;

function cb_denni_report_ma_pravo(int $idPravo): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma($idPravo);
}

