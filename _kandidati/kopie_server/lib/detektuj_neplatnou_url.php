<?php
// ===== INVALID URL DETEKCE =====
$cbInvalidUrl = false;
$reqUri = (string)($_SERVER['REQUEST_URI'] ?? '');
$reqPath = (string)(parse_url($reqUri, PHP_URL_PATH) ?? '');

$cbNormalizePath = static function (string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = '/' . ltrim($path, '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return ($path === '') ? '/' : $path;
};

$reqPathNorm = $cbNormalizePath($reqPath);
$basePathNorm = $cbNormalizePath((string)($GLOBALS['BASE_PATH'] ?? ''));

$appPath = $reqPathNorm;
if ($basePathNorm !== '/' && str_starts_with($reqPathNorm, $basePathNorm)) {
    $appPath = substr($reqPathNorm, strlen($basePathNorm));
    $appPath = ($appPath === false || $appPath === '') ? '/' : $appPath;
}
$appPath = $cbNormalizePath((string)$appPath);

$isAssetPath = (bool)preg_match('/\.(css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)$/i', $appPath);
$allowedPaths = ['/', '/index.php'];
if (!$isAssetPath && !in_array($appPath, $allowedPaths, true)) {
    $cbInvalidUrl = true;

    require_once __DIR__ . '/../db/zapis_log_chyby.php';

    try {
        $cbUser = $_SESSION['cb_user'] ?? null;
        $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : null;

        db_zapis_log_chyby(
            conn: db(),
            idUser: $idUser,
            modul: 'HTTP',
            akce: 'neplatna_url',
            kod: 'INVALID_URL_PATH',
            zprava: 'Uživatel zadal neplatnou URL.',
            detail: json_encode([
                'request_uri' => $reqUri,
                'request_path' => $reqPathNorm,
                'base_path' => $basePathNorm,
                'app_path' => $appPath,
            ], JSON_UNESCAPED_UNICODE),
            soubor: __FILE__,
            radek: __LINE__,
            url: $reqUri,
            dataJson: null,
            vyreseno: 0,
            poznamka: null
        );
    } catch (Throwable $e) {
        // nesmí shodit aplikaci
    }
}
// ===== KONEC INVALID URL =====
