<?php
declare(strict_types=1);

if (!function_exists('cb_pravo_vstupni_pravo')) {
    function cb_pravo_vstupni_pravo(int $idPravo): int
    {
        $idVstupnihoPrava = intdiv($idPravo, 100) * 100;

        return in_array($idVstupnihoPrava, [100, 200, 300, 400, 500, 600], true)
            ? $idVstupnihoPrava
            : 0;
    }
}

if (!function_exists('cb_pravo_ma')) {
    function cb_pravo_ma(int $idPravo): bool
    {
        if ($idPravo <= 0) {
            $message = 'Právo musí mít platné kladné ID.';
            error_log($message);
            throw new RuntimeException($message);
        }

        $stavy = $_SESSION['prava_stav'] ?? null;
        if (!is_array($stavy)) {
            $message = 'Číselník práv není načtený.';
            error_log($message);
            throw new RuntimeException($message);
        }
        if (!array_key_exists($idPravo, $stavy)) {
            $message = 'Právo ID ' . $idPravo . ' neexistuje v cis_prava.';
            error_log($message);
            throw new RuntimeException($message);
        }

        $idVstupnihoPrava = cb_pravo_vstupni_pravo($idPravo);
        if ($idVstupnihoPrava !== 0 && $idVstupnihoPrava !== $idPravo && !cb_pravo_ma($idVstupnihoPrava)) {
            return false;
        }

        if ((int)$stavy[$idPravo] === 0) {
            return true;
        }

        $prava = $_SESSION['prava'] ?? [];
        return is_array($prava) && array_key_exists($idPravo, $prava);
    }
}
