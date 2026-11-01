<?php
/* Co-Link: poskytne celou sadu komponent CSS klientum s puvodni adresou style.css, bez databaze a HTML. */
declare(strict_types=1);

// Stejne poradi jako v hlavni sablone zachova vztah zakladnich a kontextovych pravidel.
$files = [
    'co_zaklad.css',
    'co_rozlozeni.css',
    'co_navigace.css',
    'co_zpravy.css',
    'co_psani.css',
    'co_uvitani.css',
    'co_dialogy.css',
    'co_adresar.css',
    'co_is.css',
];

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache');
// Pred vystupem overime celou sadu, aby castecnemu nasazeni neodpovidala neuplna CSS odpoved HTTP 200.
foreach ($files as $file) {
    if (!is_readable(__DIR__ . '/' . $file)) {
        http_response_code(503);
        exit;
    }
}
foreach ($files as $file) {
    readfile(__DIR__ . '/' . $file);
    echo "\n";
}
