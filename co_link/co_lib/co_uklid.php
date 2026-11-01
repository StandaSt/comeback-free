<?php
/* Co-Link: uklid souboru a koordinace databazovych operaci; bootstrap, prostredi a spousteni zajistuje CLI vstup. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_uklid_db.php';

/** Odstrani jen nepripojene prilohy, ktere jsou stale nepripojene i po ziskani transakcniho zamku. */
function co_uklid_nepripojene(): int
{
    $count = 0;
    foreach (co_uklid_db_nepripojene() as $candidate) {
        $removed = co_transakce(static function () use ($candidate): bool {
            $row = co_uklid_db_prilohu_zamknout((int)$candidate['id']);
            if (!$row) return false;
            if (preg_match('/^[a-f0-9]{64}$/D', $row['soubor'])) {
                $path = co_uloziste() . '/' . $row['soubor'];
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Uklid souboru selhal.');
            }
            co_uklid_db_prilohu_smazat((int)$row['id']);
            return true;
        });
        if ($removed) $count++;
    }
    return $count;
}

/** Docasne casti uploadu musi byt stare, mit vlastni format nazvu a nesmi je prave drzet jiny prenos. */
function co_uklid_docasne(string $directory): int
{
    $count = 0;
    foreach (is_dir($directory) ? new DirectoryIterator($directory) : [] as $file) {
        if ($file->isDot() || $file->isLink() || !$file->isFile()
            || !preg_match('/^[0-9]+-[a-f0-9]{32}$/D', $file->getFilename())
            || $file->getMTime() > time() - 2 * 86400) continue;
        $handle = fopen($file->getPathname(), 'r+b');
        if ($handle === false) continue;
        try {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                try { if (unlink($file->getPathname())) $count++; }
                finally { flock($handle, LOCK_UN); }
            }
        } finally { fclose($handle); }
    }
    return $count;
}

/** Provede puvodni tri faze; cesta k docasnym datum zustava mimo www i po presunu knihovny. */
function co_uklid_provest(): array
{
    return [
        'nepripojene_prilohy' => co_uklid_nepripojene(),
        'docasne_soubory' => co_uklid_docasne(dirname(__DIR__, 3) . '/data/tmp/co_link'),
        'expirovane_qr' => co_uklid_db_qr_smazat(),
    ];
}
