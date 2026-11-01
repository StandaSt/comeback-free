<?php
/* Co-Link: overi velikost prilohy, ocisti puvodni nazev a porovna priponu se skutecnym MIME typem. */
declare(strict_types=1);

/** Vrati overena metadata celeho souboru; neuklada soubory ani neprovadi databazove operace. */
function co_priloha_overit(string $tmp, string $original, int $limit): array
{
    $size = filesize($tmp);
    if ($size === false || $size < 1 || $size > $limit) co_chyba('Soubor překračuje nastavený limit přílohy.', 413);
    // Puvodni nazev slouzi jen zobrazeni a stazeni; zbavime jej cest a ridicich znaku.
    $name = mb_substr(basename(str_replace('\\', '/', $original)), 0, 240);
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'soubor';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    // Zachovany seznam kombinaci; pouha pripona ani MIME dodany prohlizecem nestaci.
    $allowed = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'], 'gif' => ['image/gif'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'], 'heif' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'pdf' => ['application/pdf'], 'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'mp4' => ['video/mp4'], 'mov' => ['video/quicktime'],
    ];
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) co_chyba('Tento typ přílohy není podporovaný.');
    return ['nazev' => $name, 'mime' => $mime, 'velikost' => $size];
}
