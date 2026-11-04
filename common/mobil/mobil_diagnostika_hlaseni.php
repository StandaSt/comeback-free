<?php
declare(strict_types=1);
/* Prijima podepsane hlaseni o nacteni mobilni diagnostiky a pres existujici notifikace IS upozorni spravce1. Kazde nove nacteni ma vlastni udalost; prihlaseni ani registraci telefonu nemeni. */

/** Overi podpis konkretniho nacteni a omezi prijate udaje na kratke jednoradkove hodnoty bez tajemstvi odberu. */
function cb_mobil_diag_hlaseni_overit(array $data, string $key, int $now): array
{
    $opening = $data['opening'] ?? null;
    if (!is_array($opening) || !is_string($opening['id'] ?? null) || !preg_match('/\A[a-f0-9]{32}\z/', $opening['id'])
        || !is_int($opening['time'] ?? null) || !is_string($opening['signature'] ?? null)
        || !preg_match('/\A[a-f0-9]{64}\z/', $opening['signature'])) {
        throw new DomainException('Neplatne hlaseni.');
    }
    $expected = hash_hmac('sha256', 'cb-mobil-diag-open|' . $opening['id'] . '|' . $opening['time'], $key);
    if ($key === '' || $opening['time'] > $now + 60 || $opening['time'] < $now - 3600
        || !hash_equals($expected, $opening['signature'])) {
        throw new DomainException('Neplatne hlaseni.');
    }
    // Model hlasi prohlizec; netvrdime, ze tim byla overena totoznost cloveka nebo platnost push odberu.
    $result = ['id' => $opening['id']];
    foreach (['phone' => 100, 'model' => 80, 'android' => 30] as $field => $max) {
        $value = $data[$field] ?? '';
        if (!is_string($value)) throw new InvalidArgumentException('Neplatny udaj telefonu.');
        $result[$field] = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? ''), 0, $max, 'UTF-8');
    }
    $browsers = ['Chrome', 'Samsung Internet', 'Edge', 'Opera', 'prohlížeč'];
    $result['browser'] = in_array($data['browser'] ?? null, $browsers, true) ? $data['browser'] : 'jiný prohlížeč';
    $permissions = ['granted' => 'povolená', 'denied' => 'zakázaná', 'default' => 'dosud nepovolená', 'unsupported' => 'nepodporovaná'];
    $permission = $data['permission'] ?? '';
    $result['permission'] = is_string($permission) && isset($permissions[$permission]) ? $permissions[$permission] : 'nezjištěno';
    return $result;
}

/** Pripravi obsah pro existujici odesilac; klic odstrani jen opakovany POST stejneho nacteni, ne dalsi otevreni stranky. */
function cb_mobil_diag_hlaseni_zprava(array $data): array
{
    $phone = $data['phone'] !== '' ? $data['phone'] : 'nezjištěn';
    $content = $phone . ' · ' . $data['browser'] . ' · oznámení ' . $data['permission'] . "\n\n"
        . "Byl otevřen diagnostický odkaz pro Lukáše Vondráčka.\n\nTelefon: " . $phone . "\n"
        . 'Model: ' . ($data['model'] !== '' ? $data['model'] : 'nezjištěn') . "\n"
        . 'Prohlížeč: ' . $data['browser'] . "\n"
        . 'Android: ' . ($data['android'] !== '' ? $data['android'] : 'verze nezjištěna') . "\n"
        . 'Oznámení webu: ' . $data['permission'] . "\n\n"
        . 'Údaje nahlásil prohlížeč při otevření stránky. Test zobrazení oznámení ještě nemusí být proveden.';
    return ['title' => 'Otevřen odkaz pro Lukáše', 'content' => $content,
        'key' => 'mobil-diag-open:' . $data['id'], 'detail' => $data];
}

// CLI dovoluje offline overeni cistych funkci, ale nikdy neodesila zpravu ani neotevira DB.
if (PHP_SAPI === 'cli') return;

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) { http_response_code(415); exit; }
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) { http_response_code(413); exit; }

// Pozadavek smi pochazet jen z webu, na kterem byl pruvodce otevren; cizi stranky nesmeji vyvolat notifikaci.
$origin = parse_url((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$originHost = is_array($origin) ? strtolower((string)($origin['host'] ?? '')) . (isset($origin['port']) ? ':' . (int)$origin['port'] : '') : '';
$scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') ? 'https' : 'http';
if (!is_array($origin) || ($origin['scheme'] ?? '') !== $scheme || $originHost !== $host
    || in_array((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''), ['cross-site', 'same-site'], true)) {
    http_response_code(403); exit;
}

try {
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    if ($raw === false || strlen($raw) > 4096) { http_response_code(413); exit; }
    $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('Chybi data.');
    require_once __DIR__ . '/../config/secrets.php';
    $key = defined('CB_VAPID_PRIVATE') ? (string)CB_VAPID_PRIVATE : '';
    $event = cb_mobil_diag_hlaseni_overit($data, $key, time());

    // Lokalni overeni je bez DB a skutecnych push; odesilani zacne az po nasazeni na server.
    $hostname = preg_replace('/:\d+$/', '', $host) ?? $host;
    if (in_array($hostname, ['localhost', '127.0.0.1', '[::1]'], true)) { http_response_code(204); exit; }

    require_once __DIR__ . '/../lib/app.php';
    if (($PROSTREDI ?? '') !== 'SERVER') { http_response_code(204); exit; }
    require_once __DIR__ . '/../notifikace/notifikace_info.php';
    $message = cb_mobil_diag_hlaseni_zprava($event);
    // Pevny prijemce odpovida administratorskym upozornenim IS; klient nesmi zvolit adresata ani obsah zpravy.
    $result = cb_info_posli_jednou([1], 'mobil_diag_open', $message['title'], $message['content'], $message['key'], $message['detail']);
    http_response_code((int)($result['ok'] ?? 0) === 1 ? 204 : 503);
} catch (DomainException) { http_response_code(403); }
catch (JsonException | InvalidArgumentException) { http_response_code(400); }
catch (Throwable) {
    // Do odpovedi ani logu nepatri podpis, klice nebo obsah notifikace.
    error_log('Mobilni diagnostika: upozorneni na otevreni se nepodarilo zpracovat.');
    http_response_code(503);
}
