<?php
// lib/post_prg_redirect.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

$cbPrgKey = 'cb_prg_post_payload_v1';
$cbRequestUri = (string)($_SERVER['REQUEST_URI'] ?? '/index.php');
$cbRequestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($cbRequestMethod === 'GET' && isset($_SESSION[$cbPrgKey]) && is_array($_SESSION[$cbPrgKey])) {
    $cbPrgPayload = $_SESSION[$cbPrgKey];
    unset($_SESSION[$cbPrgKey]);

    $cbPrgTs = (int)($cbPrgPayload['ts'] ?? 0);
    $cbPrgUri = (string)($cbPrgPayload['uri'] ?? '');
    $cbPrgPost = $cbPrgPayload['post'] ?? [];

    $cbAgeOk = ($cbPrgTs > 0 && (time() - $cbPrgTs) <= 300);
    $cbUriOk = ($cbPrgUri !== '' && $cbPrgUri === $cbRequestUri);

    if ($cbAgeOk && $cbUriOk && is_array($cbPrgPost)) {
        $_POST = $cbPrgPost;
        $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
    }
}

if ($cbRequestMethod === 'POST') {
    $cbIsAjaxPost = false;
    foreach (array_keys($_SERVER) as $cbHeaderName) {
        if (strpos((string)$cbHeaderName, 'HTTP_X_COMEBACK_') === 0) {
            $cbIsAjaxPost = true;
            break;
        }
    }

    if (!$cbIsAjaxPost) {
        $_SESSION[$cbPrgKey] = [
            'ts' => time(),
            'uri' => $cbRequestUri,
            'post' => $_POST,
        ];
        header('Location: ' . $cbRequestUri, true, 303);
        exit;
    }
}
