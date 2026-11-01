<?php
/* Co-Link: prevezme HTTP upload, overi jeho prijeti a zvoli bezne nebo blokove zpracovani prilohy. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_priloha_limit_nacist.php';
require_once __DIR__ . '/co_priloha_nahrat_blok.php';
require_once __DIR__ . '/co_priloha_ulozit.php';

/** Zachovava vstup API nahrat i podobu odpovedi; velke prilohy mohou prichazet po blocich. */
function co_nahrat(int $user): array
{
    $file = $_FILES['soubor'] ?? [];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) co_chyba('Soubor se nepodařilo nahrát. Zkontrolujte velikost a připojení.', 413);
    $tmp = (string)($file['tmp_name'] ?? '');
    // Prijimame pouze soubor skutecne prijaty PHP uploadem, nikoli libovolnou cestu dodanou klientem.
    if (!is_uploaded_file($tmp)) co_chyba('Chybí nahraný soubor.');
    $limit = co_priloha_limit_nacist() * 1048576;
    if (isset($_POST['upload_id'])) return co_nahrat_blok($user, $tmp, $limit);
    return co_dokoncit_prilohu($user, $tmp, (string)($file['name'] ?? 'soubor'), $limit, true);
}
