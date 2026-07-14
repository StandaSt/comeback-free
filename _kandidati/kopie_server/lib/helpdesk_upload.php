<?php
// lib/helpdesk_upload.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

function cb_helpdesk_upload_dir(): string
{
    return dirname(__DIR__) . '/uploads/helpdesk';
}

function cb_helpdesk_upload_web_path(string $fileName): string
{
    return 'uploads/helpdesk/' . $fileName;
}

function cb_helpdesk_upload_priloha(mysqli $conn, int $idHelpdesk, ?int $idZprava, int $idUser, array $file): array
{
    if ($idHelpdesk <= 0 || $idUser <= 0) {
        throw new RuntimeException('Neplatný požadavek.');
    }

    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Soubor se nepodařilo nahrát.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Neplatný upload.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Soubor je prázdný.');
    }
    if ($size > 5242880) {
        throw new RuntimeException('Soubor je větší než 5 MB.');
    }

    $original = trim((string)($file['name'] ?? 'soubor'));
    if ($original === '') {
        $original = 'soubor';
    }

    $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Nepovolený typ souboru.');
    }

    $dir = cb_helpdesk_upload_dir();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Nepodařilo se vytvořit složku pro přílohy.');
        }
    }

    $stored = 'hd_' . (string)$idHelpdesk . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $stored;
    if (!move_uploaded_file($tmp, $target)) {
        throw new RuntimeException('Soubor se nepodařilo uložit.');
    }

    $mime = '';
    $info = finfo_open(FILEINFO_MIME_TYPE);
    if ($info) {
        $detected = finfo_file($info, $target);
        if (is_string($detected)) {
            $mime = $detected;
        }
        finfo_close($info);
    }

    $cesta = cb_helpdesk_upload_web_path($stored);

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_priloha
        (id_helpdesk, id_helpdesk_zprava, id_user, puvodni_nazev, ulozeny_nazev, cesta, mime_typ, velikost_b, vytvoreno)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Příloha se nepodařila zapsat do DB.');
    }

    $stmt->bind_param('iiissssi', $idHelpdesk, $idZprava, $idUser, $original, $stored, $cesta, $mime, $size);
    $stmt->execute();
    $idPriloha = (int)$stmt->insert_id;
    $stmt->close();

    return [
        'id_helpdesk_priloha' => $idPriloha,
        'puvodni_nazev' => $original,
        'ulozeny_nazev' => $stored,
        'cesta' => $cesta,
        'mime_typ' => $mime,
        'velikost_b' => $size,
    ];
}

// lib/helpdesk_upload.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
