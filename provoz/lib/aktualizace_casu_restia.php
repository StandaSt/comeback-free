<?php
/*
 * Ucel souboru: Vrati cas posledni dokoncene aktualizace dat Restie.
 * Neprovadi zadnou aktualizaci ani nema HTML vystup.
 */
declare(strict_types=1);

if (!function_exists('cb_aktualizace_casu_restia')) {
    /**
     * Vrati posledni potvrzeny cas dokonceni ve formatu H:i:s, jinak prazdny retezec.
     */
    function cb_aktualizace_casu_restia(mysqli $conn): string
    {
        $result = $conn->query('SELECT MAX(konec) AS posledni_konec FROM online_restia WHERE konec IS NOT NULL');
        if (!($result instanceof mysqli_result)) {
            return '';
        }

        $row = $result->fetch_assoc();
        $result->free();
        $finishedAt = trim((string)($row['posledni_konec'] ?? ''));
        if ($finishedAt === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($finishedAt))->format('H:i:s');
        } catch (Throwable $e) {
            return '';
        }
    }
}
