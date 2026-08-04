<?php
// lib/asset_url.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

if (!function_exists('cb_asset_url')) {
    function cb_public_style_url(string $path): string
    {
        $path = ltrim($path, '/');
        if (function_exists('cb_public_url')) {
            return cb_public_url($path);
        }

        return 'https://comebacks.cz/common/' . $path;
    }

    function cb_asset_url(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $isProvozStyle = in_array($cleanPath, ['style/provoz.css', 'style/media.css'], true);
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
