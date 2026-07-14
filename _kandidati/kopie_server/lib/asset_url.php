<?php
// lib/asset_url.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

if (!function_exists('cb_asset_url')) {
    function cb_asset_url(string $path): string
    {
        $full = __DIR__ . '/../' . ltrim($path, '/');
        $ver = is_file($full) ? (string)filemtime($full) : '1';
        return cb_url($path) . '?v=' . $ver;
    }
}
