<?php
declare(strict_types=1);

if (!function_exists('cb_pravo_ma')) {
    function cb_pravo_ma(int $idPravo): bool
    {
        if ($idPravo <= 0) {
            return false;
        }

        $prava = $_SESSION['prava'] ?? [];
        return is_array($prava) && array_key_exists($idPravo, $prava);
    }
}
