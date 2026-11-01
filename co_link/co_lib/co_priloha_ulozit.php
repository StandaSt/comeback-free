<?php
/* Co-Link: ulozi overenou prilohu do neverejneho uloziste a zajisti evidenci; pri chybe DB odstrani prave ulozenou kopii. */
declare(strict_types=1);
require_once __DIR__ . '/co_priloha_overit.php';
require_once __DIR__ . '/../co_db/co_prilohu_vlozit.php';

/** Dokonci bezny i slozeny upload se stejnou API odpovedi; bezna cesta pouziva move_uploaded_file. */
function co_dokoncit_prilohu(int $user, string $tmp, string $original, int $limit, bool $httpUpload): array
{
    $metadata = co_priloha_overit($tmp, $original, $limit);
    $dir = co_uloziste();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Uloziste neni dostupne.');
    // Nahodny interni nazev nikdy nezavisi na puvodnim nazvu souboru od uzivatele.
    $stored = bin2hex(random_bytes(32));
    $moved = $httpUpload ? move_uploaded_file($tmp, $dir . '/' . $stored) : copy($tmp, $dir . '/' . $stored);
    if (!$moved) throw new RuntimeException('Ulozeni prilohy selhalo.');
    try {
        $id = co_prilohu_vlozit($user, $metadata['nazev'], $stored, $metadata['mime'], $metadata['velikost']);
        return ['priloha' => ['id' => $id, 'nazev' => $metadata['nazev'], 'velikost' => $metadata['velikost']]];
    } catch (Throwable $error) {
        // Soubor bez uspesne evidence nesmi zustat v trvalem ulozisti jako osiřela kopie.
        unlink($dir . '/' . $stored);
        throw $error;
    }
}
