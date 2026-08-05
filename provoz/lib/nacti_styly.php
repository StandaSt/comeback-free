<?php
// lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026
// Pocet radku: 51
// Predchozi pocet radku: 51

/*
 * Nacteni CSS stylu do <head>
 *
 *
 * Zavislosti:
 * - cb_url() (sestaveni URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

if (!function_exists('cb_asset_url')) {
    function cb_public_style_url(string $path): string
    {
        $path = ltrim($path, '/');
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $isLocal =
            $host === 'localhost' ||
            str_starts_with($host, 'localhost:') ||
            $host === '127.0.0.1' ||
            str_starts_with($host, '127.0.0.1:');

        if ($isLocal) {
            $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
            $base = preg_replace('~/provoz(?:/.*)?$~', '', $scriptName) ?? '';
            return rtrim($base, '/') . '/common/' . $path;
        }

        return 'https://comebacks.cz/common/' . $path;
    }

    function cb_asset_url(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $isProvozStyle = in_array($cleanPath, ['style/global.css', 'style/media.css'], true);
        $isPublicStyle = str_starts_with($cleanPath, 'style/') && !$isProvozStyle;
        $full = $isPublicStyle
            ? __DIR__ . '/../../common/' . $cleanPath
            : __DIR__ . '/../' . $cleanPath;
        $ver = is_file($full) ? (string)filemtime($full) : '1';
        $url = $isPublicStyle
            ? cb_public_style_url($cleanPath)
            : (function_exists('cb_module_asset_url')
            ? cb_module_asset_url($path, 'provoz')
            : cb_url($path));
        return $url . '?v=' . $ver;
    }
}

?>
<!-- styly -->
<link rel="stylesheet" href="<?= h(cb_asset_url('style/global.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_asset_url('style/modal_alert.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_asset_url('style/provoz.css')) ?>">



<?php

/* lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026 * Pocet radku: 51
   konec souboru */
?>
