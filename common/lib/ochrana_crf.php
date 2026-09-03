<?php
/*
 * Účel souboru: Jednotná ochrana přihlášeného IS proti podvrženým zápisovým
 * požadavkům. Token patří do session, formuláře jej posílají v cb_crf a AJAX
 * v hlavičce X-Comeback-Crf.
 */
declare(strict_types=1);

function cb_crf_token(): string
{
    $token = $_SESSION['cb_crf_token'] ?? null;
    if (!is_string($token) || preg_match('~^[a-f0-9]{64}$~', $token) !== 1) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['cb_crf_token'] = $token;
    }

    return $token;
}

function cb_crf_zapisova_metoda(): bool
{
    return in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function cb_crf_prijaty_token(): string
{
    $header = trim((string)($_SERVER['HTTP_X_COMEBACK_CRF'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_POST['cb_crf'] ?? ''));
}

function cb_crf_platny(): bool
{
    $token = cb_crf_prijaty_token();
    return $token !== '' && hash_equals(cb_crf_token(), $token);
}

function cb_crf_vyzaduj(): void
{
    if (!cb_crf_zapisova_metoda() || cb_crf_platny()) {
        return;
    }

    http_response_code(403);
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $isJson = str_contains($accept, 'application/json') || isset($_SERVER['HTTP_X_REQUESTED_WITH']);
    if ($isJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'err' => 'Neplatný bezpečnostní token. Obnovte stránku a akci zopakujte.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Neplatný bezpečnostní token. Obnovte stránku a akci zopakujte.';
    }
    exit;
}

// common/lib/ochrana_crf.php * Konec souboru
