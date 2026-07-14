<?php
declare(strict_types=1);

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Missing file.\n");
    exit(1);
}

$data = file_get_contents($path);
if (!is_string($data)) {
    fwrite(STDERR, "Cannot read file.\n");
    exit(1);
}

$hasBom = strncmp($data, "\xEF\xBB\xBF", 3) === 0;
$isUtf8 = mb_check_encoding($data, 'UTF-8');

echo json_encode([
    'file' => $path,
    'utf8' => $isUtf8,
    'bom' => $hasBom,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

exit(($isUtf8 && !$hasBom) ? 0 : 2);
