<?php
// Spolecny zamek pobocky a overeni platnych kontrol chrani ulozeni i obnovu pred soubehem.
declare(strict_types=1);

// Konflikt nese konkretni verze, aby uzivatel nepotvrdil nahrazeni mezitim zmeneneho archivu.
class CbKtKonflikt extends RuntimeException
{
    public function __construct(public readonly array $ids) { parent::__construct('V archivu již existuje platná kontrola této pobočky a období.'); }
}

// Existujici pobocka poskytuje stabilni zamek i tehdy, kdy jeste nema zadnou kontrolu.
function cb_kt_archiv_zamkni(mysqli $db, int $branch): void
{
    $stmt = $db->prepare('SELECT id_pob FROM pobocka WHERE id_pob=? FOR UPDATE');
    $stmt->bind_param('i', $branch); $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null; $stmt->close();
    if (!$exists) { throw new CbUserVisibleException('Pobočka nebyla nalezena.'); }
}

// Zamykaci varianta uvnitr transakce cte posledni potvrzeny stav, nikoli starsi snimek.
function cb_kt_archiv_platne_ids(mysqli $db, int $branch, string $from, string $to, int $exclude = 0, bool $lock = false): array
{
    $valid = cb_kt_platnost_pripravena($db) ? ' AND platny=1' : '';
    $stmt = $db->prepare('SELECT id_kontrola FROM provoz_kontrola_trzeb WHERE id_pob=? AND datum_od=? AND datum_do=? AND id_kontrola<>?' . $valid . ' ORDER BY id_kontrola' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->bind_param('issi', $branch, $from, $to, $exclude); $stmt->execute();
    $ids = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_kontrola'));
    $stmt->close(); return $ids;
}

// Souhlas musi patrit prave existujicim verzim. Novou soubezne ulozenou verzi neprepisujeme.
function cb_kt_archiv_over_souhlas(array $actual, ?array $confirmed): void
{
    if ($actual !== [] && $actual !== $confirmed) { throw new CbKtKonflikt($actual); }
}
