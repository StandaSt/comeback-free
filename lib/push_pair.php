<?php
// lib/push_pair.php * Verze: V1 * Aktualizace: 25.2.2026
declare(strict_types=1);

/*
 * Uložení párování mobilu (Push subscription) do DB tabulky push_zarizeni
 *
 * Vstup (JSON):
 * - subscription.endpoint
 * - subscription.keys.p256dh
 * - subscription.keys.auth
 * - nazev (volitelné)
 *
 * Ukládá:
 * - endpoint + endpoint_hash (UNHEX(SHA2(endpoint,256)))
 * - klic_public, klic_auth
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'err' => 'Neplatná metoda.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = 0;
    if (is_array($cbUser) && isset($cbUser['id_user'])) {
        $idUser = (int)$cbUser['id_user'];
    }
    if ($idUser <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí id_user.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí JSON.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatný JSON.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sub = $data['subscription'] ?? null;
    if (!is_array($sub)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí subscription.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $endpoint = $sub['endpoint'] ?? '';
    if (!is_string($endpoint) || trim($endpoint) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí endpoint.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $endpoint = trim($endpoint);

    $keys = $sub['keys'] ?? null;
    if (!is_array($keys)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí keys.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $kPublic = $keys['p256dh'] ?? '';
    $kAuth = $keys['auth'] ?? '';

    if (!is_string($kPublic) || trim($kPublic) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí klic_public.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_string($kAuth) || trim($kAuth) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Chybí klic_auth.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $kPublic = trim($kPublic);
    $kAuth = trim($kAuth);

    $nazev = $data['nazev'] ?? null;
    if (!is_string($nazev)) {
        $nazev = '';
    }
    $nazev = trim($nazev);

    $conn = db();

    $sql = '
        INSERT INTO push_zarizeni
        (id_user, endpoint, endpoint_hash, klic_public, klic_auth, nazev, aktivni, vytvoreno, naposledy)
        VALUES
        (?, ?, UNHEX(SHA2(?,256)), ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          klic_public = VALUES(klic_public),
          klic_auth   = VALUES(klic_auth),
          nazev       = VALUES(nazev),
          aktivni     = 1,
          naposledy   = NOW()
    ';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('DB: prepare selhal.');
    }

    $stmt->bind_param('isssss', $idUser, $endpoint, $endpoint, $kPublic, $kAuth, $nazev);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// lib/push_pair.php * Verze: V1 * Aktualizace: 25.2.2026 * Počet řádků: 140
// Konec souboru