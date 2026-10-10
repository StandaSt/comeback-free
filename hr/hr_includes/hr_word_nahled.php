<?php
/* Ucel souboru: Bez LibreOffice na serveru pripravi chraneny PDF nahled Wordu z overeneho prenosneho baliku. */
declare(strict_types=1);

/** PDF spoji s SHA originalu; id z lokalni DB se nikdy nepouziva k serverovemu parovani. */
function hr_word_preview(string $source, string $sourceHash): string
{
    if (!preg_match('/^[a-f0-9]{64}$/', $sourceHash) || !hash_equals($sourceHash, (string)hash_file('sha256', $source))) {
        throw new RuntimeException('Originál dokumentu neodpovídá evidovanému obsahu.');
    }
    $package=dirname(__DIR__,3).'/data/google_data/hr_word_nahledy.zip';
    if (!is_file($package)) { throw new RuntimeException('Chybí balík PDF náhledů hr_word_nahledy.zip v data/google_data.'); }
    $archive=new PharData($package);
    $manifest=json_decode($archive['manifest.json']->getContent(),true,512,JSON_THROW_ON_ERROR);
    $expected=$manifest[$sourceHash]['pdf_sha256'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/',(string)$expected) || !isset($archive[$sourceHash.'.pdf'])) {
        throw new RuntimeException('Pro tento Word dokument zatím není připravený PDF náhled.');
    }
    $target=substr($source,0,strrpos($source,'.')).'.pdf';
    if (is_file($target)) {
        if (!hash_equals($expected,(string)hash_file('sha256',$target))) { throw new RuntimeException('Uložený PDF náhled neodpovídá balíku; je třeba jej zkontrolovat.'); }
        return $target;
    }
    $bytes=$archive[$sourceHash.'.pdf']->getContent();
    if (!str_starts_with($bytes,'%PDF-') || !hash_equals($expected,hash('sha256',$bytes))) { throw new RuntimeException('Kontrola PDF náhledu selhala.'); }
    // Hotovy soubor zverejni atomicky, nikoli po castech; soubezne zadosti ukladaji totozny obsah.
    $temp=tempnam(dirname($source),'hr_preview_');
    if (!$temp) { throw new RuntimeException('Nelze vytvořit PDF náhled v adresáři HR dokumentů.'); }
    try {
        if (file_put_contents($temp,$bytes)!==strlen($bytes)) { throw new RuntimeException('Zápis PDF náhledu selhal.'); }
        if (is_file($target)) {
            if (!hash_equals($expected,(string)hash_file('sha256',$target))) { throw new RuntimeException('Cílový náhled byl změněn jiným procesem.'); }
        } elseif (!rename($temp,$target)) { throw new RuntimeException('Nelze dokončit uložení PDF náhledu.'); }
    } finally { if (is_file($temp)) { unlink($temp); } }
    return $target;
}
