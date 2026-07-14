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
            $base = preg_replace('~/is(?:/.*)?$~', '', $scriptName) ?? '';
            return rtrim($base, '/') . '/www/' . $path;
        }

        return 'https://www.comebacks.cz/' . $path;
    }

    function cb_asset_url(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $isPublicStyle = str_starts_with($cleanPath, 'style/');
        $full = $isPublicStyle
            ? __DIR__ . '/../../www/' . $cleanPath
            : __DIR__ . '/../' . $cleanPath;
        $ver = is_file($full) ? (string)filemtime($full) : '1';
        $url = $isPublicStyle
            ? cb_public_style_url($cleanPath)
            : (function_exists('cb_module_asset_url')
            ? cb_module_asset_url($path, 'is')
            : cb_url($path));
        return $url . '?v=' . $ver;
    }
}

?>
<!-- styly -->
<link rel="stylesheet" href="<?= h(cb_asset_url('style/1/global.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_asset_url('style/1/modal_alert.css')) ?>">
<style>
.head_user_gap{grid-column:-2/-1;grid-row:1;justify-self:end;width:48px;height:100%;align-self:stretch;overflow:visible;display:grid;grid-template-rows:1fr 1fr;gap:5px}
.head_user_gap_btn{display:flex;align-items:center;justify-content:center;min-width:0;min-height:0;font-size:11px;font-weight:700;line-height:1;text-align:center;text-decoration:none;white-space:nowrap;border-radius:8px}
.head_user_gap_btn--hr{border:2px solid #e7b7b7;color:#9f1d1d;background:#f9dede}
.head_user_gap_btn--smeny{border:2px solid #b8d0ef;color:#0f3f91;background:#dcecff}
</style>



<?php

/* lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026 * Pocet radku: 51
   konec souboru */
?>
