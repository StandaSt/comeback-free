<?php
/* Co-Link: pod souborovym zamkem sklada casti uploadu a dokoncenou prilohu predava k overeni a ulozeni. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_prilohy_rozpracovane_spocitat.php';
require_once __DIR__ . '/co_priloha_ulozit.php';

/** Kazdy prenos patri vlastnikovi a nahodnemu ID; nespravne poradi nesmi prepsat jiz prijate bajty. */
function co_nahrat_blok(int $user, string $tmp, int $limit): array
{
    $id = (string)($_POST['upload_id'] ?? '');
    $total = filter_var($_POST['celkem'] ?? null, FILTER_VALIDATE_INT);
    $offset = filter_var($_POST['pozice'] ?? null, FILTER_VALIDATE_INT);
    $chunk = filesize($tmp);
    if (!preg_match('/^[a-f0-9]{32}$/D', $id) || $total === false || $offset === false || $total < 1 || $total > $limit || $offset < 0 || $chunk === false || $chunk < 1 || $chunk > 1048576 || $offset + $chunk > $total) co_chyba('Neplatná velikost nebo pořadí části přílohy.', 413);
    if (co_prilohy_rozpracovane_spocitat($user) >= 100) co_chyba('Máte příliš mnoho rozpracovaných příloh. Zkuste to po jejich automatickém úklidu.');
    // Po presunu do co_lib zustava docasne uloziste na stejnem miste mimo verejny www adresar.
    $dir = dirname(__DIR__, 3) . '/data/tmp/co_link';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Docasne uloziste neni dostupne.');
    $path = $dir . '/' . $user . '-' . $id;
    $handle = fopen($path, 'c+b');
    if ($handle === false) throw new RuntimeException('Nelze otevrit cast prilohy.');
    $completed = false;
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Nelze zamknout prenos.');
        $actual = fstat($handle)['size'];
        if ($actual !== $offset) co_chyba('Přenos přílohy byl přerušen. Vyberte soubor znovu.', 409);
        fseek($handle, 0, SEEK_END);
        $input = fopen($tmp, 'rb');
        if ($input === false) throw new RuntimeException('Nelze precist cast prilohy.');
        try { $written = stream_copy_to_stream($input, $handle); }
        finally { fclose($input); }
        if ($written !== $chunk) throw new RuntimeException('Neuplny zapis prilohy.');
        fflush($handle);
        $completed = $offset + $chunk === $total;
        if ($completed) {
            // Typ souboru se kontroluje az z celeho obsahu; prvni blok sam o sobe nestaci.
            $result = co_dokoncit_prilohu($user, $path, (string)($_POST['nazev'] ?? 'soubor'), $limit, false);
            return $result + ['dokonceno' => true];
        }
        return ['dokonceno' => false, 'pozice' => $offset + $chunk];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
        // Dokonceny docasny prenos se uklidi i pri odmitnuti typu nebo chybe nasledne evidence.
        if ($completed && is_file($path)) unlink($path);
    }
}
