<?php
/* Účel: Pojmenované dotazy nad změnami IS; načtení souboru žádný dotaz nespouští. */
declare(strict_types=1);

/** Stránka obsahuje jeden řádek navíc pro rozpoznání pokračování. */
function cb_db_verze_seznam(mysqli $db, int $strana): array
{
    $offset = $strana * 50;
    $stmt = $db->prepare('SELECT id, datum, poradi, typ, obsah FROM admin_verzovani ORDER BY datum DESC, poradi DESC LIMIT 51 OFFSET ?');
    try {
        $stmt->bind_param('i', $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}

/** Opakované odeslání stejného formuláře vrací již uloženou verzi. */
function cb_db_verze_pozadavek(mysqli $db, string $kod): ?array
{
    $stmt = $db->prepare('SELECT datum, poradi FROM admin_verzovani WHERE kod_pozadavku = ?');
    try {
        $stmt->bind_param('s', $kod);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    } finally { $stmt->close(); }
}

/** Každý den se čísluje od jedničky; unikátní index chrání souběh. */
function cb_db_verze_dalsi_poradi(mysqli $db, string $datum): int
{
    $stmt = $db->prepare('SELECT COALESCE(MAX(poradi), 0) + 1 AS dalsi FROM admin_verzovani WHERE datum = ?');
    try {
        $stmt->bind_param('s', $datum);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['dalsi'];
    } finally { $stmt->close(); }
}

/** Uloží jednu ručně napsanou změnu bez přepisování jejího textu. */
function cb_db_verze_vlozit(mysqli $db, string $datum, int $poradi, int $typ, string $obsah, int $autor, string $cas, string $kod): void
{
    $stmt = $db->prepare('INSERT INTO admin_verzovani (datum, poradi, typ, obsah, id_user, vytvoreno, kod_pozadavku) VALUES (?, ?, ?, ?, ?, ?, ?)');
    try {
        $stmt->bind_param('siisiss', $datum, $poradi, $typ, $obsah, $autor, $cas, $kod);
        $stmt->execute();
    } finally { $stmt->close(); }
}
