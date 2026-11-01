<?php
/* Co-Link: vraci stabilni koren Co-Linku take pro vnorene vstupy. */
declare(strict_types=1);

/** Cookie i QR odkazy zustavaji v koreni Co-Linku, take pri volani vstupu z vnorenych adresaru. */
function co_cesta(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/co_link/index.php'));
    // Adresar konkretniho endpointu nesmi zuzit platnost cookie napriklad na co_ajax/.
    $module = strrpos($script, '/co_link/');
    if ($module !== false) return substr($script, 0, $module + strlen('/co_link/'));
    // Pro volani mimo modul ponechame dosavadni odvozeni z adresare spousteneho skriptu.
    return rtrim(dirname($script), '/') . '/';
}
