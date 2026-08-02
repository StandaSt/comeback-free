<?php
// lib/asset_url.php * Verze: V1 * Aktualizace: 23.04.2026
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
        $isIsStyle = ($cleanPath === 'style/1/global.css');
        $isPublicStyle = str_starts_with($cleanPath, 'style/') && !$isIsStyle;
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
