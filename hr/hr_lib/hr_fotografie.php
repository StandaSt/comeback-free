<?php
declare(strict_types=1);
/* Účel: Ověří výřez a uloží jediný aktuální portrét osoby jako hr_img/zamestnanci/ID.jpg. */

/** Oba formuláře přijímají pouze čitelný čtvercový výřez do velikosti 1200 px. */
function hr_foto_over_vyrez(array $upload): void
{
    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new CbUserVisibleException('Vyberte fotografii a její čtvercový výřez.');
    $temporary = (string)($upload['tmp_name'] ?? '');
    $size = $temporary !== '' && is_file($temporary) ? @getimagesize($temporary) : false;
    if ($size === false || $size[0] < 1 || $size[0] !== $size[1] || $size[0] > 1200) {
        throw new CbUserVisibleException('Vyberte čtvercový výřez fotografie o velikosti nejvýše 1200 × 1200 px.');
    }
}

/** Soubor se skutečně převede do JPEG; úplný nový obrázek teprve potom nahradí původní. */
function hr_store_employee_photo(mixed $upload, int $idPerson): ?string
{
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($idPerson <= 0) throw new LogicException('Fotografie vyžaduje platné ID osoby.');
    hr_foto_over_vyrez($upload);
    if ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 2 * 1024 * 1024) {
        throw new CbUserVisibleException('Fotografie může mít nejvýše 2 MB.');
    }
    $source = (string)($upload['tmp_name'] ?? '');
    if (!is_uploaded_file($source)) throw new CbUserVisibleException('Nahraný soubor fotografie není platný.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new CbUserVisibleException('Fotografie musí být JPEG, PNG nebo WebP.');
    }
    $image = @imagecreatefromstring(file_get_contents($source));
    if ($image === false) throw new CbUserVisibleException('Nahraný soubor není čitelný obrázek.');
    $directory = dirname(__DIR__) . '/hr_img/zamestnanci';
    $temporary = false;
    try {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('Nelze připravit úložiště fotografií.');
        // Průhledné PNG/WebP mají v JPEG bílé pozadí.
        $jpeg = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($jpeg, 0, 0, imagecolorallocate($jpeg, 255, 255, 255));
        imagecopy($jpeg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        try {
            $temporary = tempnam($directory, 'foto_');
            if ($temporary === false || !imagejpeg($jpeg, $temporary, 90)) throw new RuntimeException('Fotografii se nepodařilo uložit.');
        } finally { imagedestroy($jpeg); }
        chmod($temporary, 0644);
        if (!rename($temporary, $directory . '/' . $idPerson . '.jpg')) throw new RuntimeException('Fotografii se nepodařilo nahradit.');
    } finally {
        imagedestroy($image);
        if (is_string($temporary) && is_file($temporary)) unlink($temporary);
    }
    return 'hr/hr_img/zamestnanci/' . $idPerson . '.jpg';
}
