<?php
// is/includes/kontrola_email.php * Verze: V2 * Aktualizace: 29.07.2026

declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/db/db_connect.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/mailer.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$cbKontrolaEmailEmbed = defined('CB_KONTROLA_EMAIL_EMBED') && CB_KONTROLA_EMAIL_EMBED;
if (!$cbKontrolaEmailEmbed) {
    http_response_code(404);
    exit;
}

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function absolutni_url(array $params = []): string
{
$base = 'https://comebacks.cz/provoz/overit_email.php';
    $query = $params !== [] ? '?' . http_build_query($params) : '';

    return $base . $query;
}

function normalizuj_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function validni_email(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function vytvor_token(): string
{
    return bin2hex(random_bytes(32));
}

function nacti_csrf(): string
{
    if (empty($_SESSION['kontrola_email_csrf'])) {
        $_SESSION['kontrola_email_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['kontrola_email_csrf'];
}

function over_csrf(string $token): bool
{
    $sessionToken = (string)($_SESSION['kontrola_email_csrf'] ?? '');
    return $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function cele_jmeno(array $row): string
{
    return trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? ''));
}

function datum_cesky(?string $hodnota): string
{
    if ($hodnota === null || $hodnota === '') {
        return '';
    }

    return (new DateTime($hodnota))->format('j. n. Y H:i');
}

function skupina_stavu(?string $stav): string
{
    return match ($stav) {
        'potvrzeno' => 'Potvrzené',
        'nedorucitelne' => 'Nedoručitelné',
        default => 'Čeká se na potvrzení',
    };
}

function nacti_prehled_emailu(mysqli $db): array
{
    return $db->query(
        "SELECT e.email,
                TRIM(CONCAT(COALESCE(u.prijmeni, ''), ' ', COALESCE(u.jmeno, ''))) AS cele_jmeno,
                e.stav,
                e.odeslano,
                e.potvrzeno
         FROM aaa_email_overeni e
         LEFT JOIN user u ON u.id_user = e.id_user
         WHERE e.id = (
             SELECT MAX(e2.id)
             FROM aaa_email_overeni e2
             WHERE e2.email = e.email
         )
         ORDER BY CASE
                      WHEN e.stav = 'potvrzeno' THEN 1
                      WHEN e.stav = 'nedorucitelne' THEN 3
                      ELSE 2
                  END,
                  e.email"
    )->fetch_all(MYSQLI_ASSOC);
}

function souhrn_kontroly(array $prehledEmailu): array
{
    $souhrn = [
        'potvrzene' => 0,
        'ceka' => 0,
        'nedorucene' => 0,
    ];

    foreach ($prehledEmailu as $radek) {
        $stav = (string)($radek['stav'] ?? '');
        if ($stav === 'potvrzeno') {
            $souhrn['potvrzene']++;
        } elseif ($stav === 'nedorucitelne') {
            $souhrn['nedorucene']++;
        } else {
            $souhrn['ceka']++;
        }
    }

    return $souhrn;
}

function html_souhrn_kontroly(array $prehledEmailu): string
{
    $souhrn = souhrn_kontroly($prehledEmailu);

    return 'Aktuálně potvrzené: ' . (int)$souhrn['potvrzene']
        . '&nbsp;&nbsp; Čeká se: ' . (int)$souhrn['ceka']
        . '&nbsp;&nbsp; Nedoručené: ' . (int)$souhrn['nedorucene'];
}

function nacti_aktivni_emaily(mysqli $db): array
{
    $rows = [];
    $result = $db->query(
        "SELECT id_user, jmeno, prijmeni, LOWER(TRIM(email)) AS email
         FROM user
         WHERE email IS NOT NULL
           AND TRIM(email) <> ''
           AND aktivni = 1
           AND NOT EXISTS (
               SELECT 1
               FROM aaa_email_overeni e
               WHERE e.email = LOWER(TRIM(user.email))
           )
         ORDER BY email"
    );

    $zpracovaneEmaily = [];
    while ($row = $result->fetch_assoc()) {
        $email = normalizuj_email((string)$row['email']);
        if (isset($zpracovaneEmaily[$email])) {
            continue;
        }

        $zpracovaneEmaily[$email] = true;
        $row['email'] = $email;
        $rows[] = $row;
    }
    $result->free();

    return $rows;
}

function odpovez_json(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function html_radky_kontroly(array $prehledEmailu): string
{
    $html = '';
    $aktualniSkupina = '';
    $poradi = 0;

    foreach ($prehledEmailu as $radek) {
        $skupina = skupina_stavu($radek['stav'] ?? null);
        if ($skupina !== $aktualniSkupina) {
            $aktualniSkupina = $skupina;
            $html .= '<tr><th colspan="5" class="group">' . h($aktualniSkupina) . '</th></tr>'
                . '<tr class="table-head"><th>p.č.</th><th>Email</th><th>Celé jméno</th><th>Odesláno</th><th>Kdy potvrzeno</th></tr>';
        }

        $poradi++;
        $html .= '<tr>'
            . '<td>' . $poradi . '</td>'
            . '<td>' . h((string)$radek['email']) . '</td>'
            . '<td>' . h((string)$radek['cele_jmeno']) . '</td>'
            . '<td>' . h(datum_cesky($radek['odeslano'] ?? null)) . '</td>'
            . '<td>' . h(datum_cesky($radek['potvrzeno'] ?? null)) . '</td>'
            . '</tr>';
    }

    return $html !== '' ? $html : '<tr><td colspan="5">Žádné kontrolní e-maily.</td></tr>';
}

function exportuj_kontrolu_pdf(array $prehledEmailu, string $kontrolaCas): void
{
    require_once __DIR__ . '/../../../vendor/autoload.php';

    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8">'
        . '<style>'
        . 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111827;}'
        . 'h1{font-size:18px;margin:0 0 14px 0;}'
        . 'table{width:100%;border-collapse:collapse;}'
        . 'th,td{border:1px solid #d7e0ec;padding:6px 7px;text-align:left;vertical-align:top;}'
        . 'td:first-child,th:first-child{width:24px;text-align:right;}'
        . 'th{background:#eef4fb;font-weight:bold;}'
        . '.group{background:#dbeafe;padding-top:12px;}'
        . '.table-head th{background:#eef4fb;}'
        . '</style></head><body>'
        . '<h1>Kontrola e-mailů: ' . h($kontrolaCas) . '</h1>'
        . '<table><tbody>'
        . html_radky_kontroly($prehledEmailu)
        . '</tbody></table>'
        . '</body></html>';

    $options = new Dompdf\Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('kontrola_emailu_' . date('Y-m-d_H-i') . '.pdf', ['Attachment' => true]);
}

function odesli_overovaci_email(string $email, string $celeJmeno, string $potvrzovaciUrl): void
{
    $subject = 'Opravené potvrzení platnosti e-mailové adresy';
    $osloveniJmeno = $celeJmeno !== '' ? $celeJmeno : 'uživatele';
$obrazekUrl = 'https://comebacks.cz/provoz/img/mejl_email.jpg';
    $omluvaHtml = '<p style="margin:0 0 10px;color:#b91c1c;font-weight:bold;">Velmi se omlouváme.</p>'
        . '<p style="margin:0 0 12px;color:#b91c1c;font-weight:bold;">Předchozí potvrzovací e-mail mohl obsahovat nefunkční odkaz.</p>'
        . '<p style="margin:0 0 16px;color:#b91c1c;font-weight:bold;">Prosíme, potvrďte e-mailovou adresu znovu pomocí tohoto již opraveného e-mailu.</p>';
    $omluvaText = "Velmi se omlouváme.\n"
        . "Předchozí potvrzovací e-mail mohl obsahovat nefunkční odkaz.\n"
        . "Prosíme, potvrďte e-mailovou adresu znovu pomocí tohoto již opraveného e-mailu.\n\n";

    $body = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#eef6ff;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<div style="padding:28px 14px;">'
        . '<div style="max-width:800px;margin:0 auto;background:#ffffff;border:1px solid #cfe0f3;border-radius:8px;overflow:hidden;">'
        . '<div style="padding:22px 28px;background:#0b64b4;color:#ffffff;">'
        . '<h1 style="margin:0;font-size:22px;line-height:1.25;">Pizza Comeback</h1>'
        . '<p style="margin:6px 0 0;font-size:15px;">Ověření e-mailové adresy</p>'
        . '</div>'
        . '<div style="padding:26px 28px;">'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">'
        . '<tr>'
        . '<td style="vertical-align:top;padding:0 18px 0 0;line-height:1.45;">'
        . '<p style="margin:0 0 14px;">Dobrý den,</p>'
        . $omluvaHtml
        . '<p style="margin:0 0 8px;">V našem IS evidujeme u uživatele<br>'
        . '<strong>&quot;' . h($osloveniJmeno) . '&quot;</strong></p>'
        . '<p style="margin:0 0 8px;">tuto e-mailovou adresu:</p>'
        . '<p style="margin:0 0 16px;"><strong>' . h($email) . '</strong></p>'
        . '<p style="margin:0 0 22px;">Prosíme o potvrzení, že je adresa platná a že ji používáte.</p>'
        . '<p style="margin:0 0 24px;">'
        . '<a href="' . h($potvrzovaciUrl) . '" style="display:inline-block;background:#0b64b4;color:#ffffff;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 26px;border-radius:6px;">Potvrdit platnost e-mailu</a>'
        . '</p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;">Pokud tuto zprávu nepovažujete za určenou Vám, nemusíte na ni reagovat.</p>'
        . '</td>'
        . '<td style="width:340px;vertical-align:top;padding:0;text-align:right;">'
        . '<img src="' . h($obrazekUrl) . '" width="340" alt="Omluva Pizza Comeback" style="display:block;width:340px;max-width:340px;height:auto;border:0;border-radius:8px;margin:0 0 0 auto;">'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</div>'
        . '<div style="padding:16px 28px;background:#f3f7fc;color:#64748b;font-size:11px;line-height:1.45;border-top:1px solid #d7e3f4;">'
        . 'Pizza Comeback je provozována společností Rolling dough s.r.o.<br>'
        . 'IČ: 06636705<br>'
        . 'DIČ: CZ06636705'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</body></html>';

    $alt = implode("\n", [
        'Dobrý den,',
        '',
        $omluvaText
            . 'v našem IS evidujeme u uživatele "' . $osloveniJmeno . '"',
        'tuto e-mailovou adresu',
        $email,
        '',
        'Prosíme o potvrzení, že je adresa platná a používáte ji.',
        '',
        'Potvrdit platnost e-mailu:',
        $potvrzovaciUrl,
        '',
        'Pizza Comeback',
        'Rolling dough s.r.o.',
        'IČ: 06636705',
        'DIČ: CZ06636705',
    ]);

    cb_mail_send('hr', $email, $subject, $body, $alt);
}

function uloz_a_odesli(mysqli $db, int $idUser, string $email, string $celeJmeno): array
{
    $email = normalizuj_email($email);

    if (!validni_email($email)) {
        return ['ok' => false, 'email' => $email, 'zprava' => 'Neplatný formát e-mailu.'];
    }

    $token = vytvor_token();
    $url = absolutni_url(['t' => $token]);

    $stmt = $db->prepare(
        "INSERT INTO aaa_email_overeni
            (id_user, email, token, stav, odeslano, potvrzeno, posledni_kontrola, smtp_kod, smtp_odpoved, pocet_pokusu)
         VALUES
            (?, ?, ?, 'ceka_na_odeslani', NULL, NULL, NOW(), NULL, NULL, 0)"
    );
    $stmt->bind_param('iss', $idUser, $email, $token);
    $stmt->execute();
    $idOvereni = (int)$db->insert_id;
    $stmt->close();

    try {
        odesli_overovaci_email($email, $celeJmeno, $url);

        $stmt = $db->prepare(
            "UPDATE aaa_email_overeni
             SET stav = 'odeslano',
                 odeslano = NOW(),
                 posledni_kontrola = NOW(),
                 smtp_kod = NULL,
                 smtp_odpoved = NULL,
                 pocet_pokusu = pocet_pokusu + 1
             WHERE id = ?"
        );
        $stmt->bind_param('i', $idOvereni);
        $stmt->execute();
        $stmt->close();

        return ['ok' => true, 'email' => $email, 'zprava' => 'Odesláno.'];
    } catch (Throwable $e) {
        $chyba = mb_substr($e->getMessage(), 0, 65000);

        $stmt = $db->prepare(
            "UPDATE aaa_email_overeni
             SET stav = 'docasna_chyba',
                 posledni_kontrola = NOW(),
                 smtp_odpoved = ?,
                 pocet_pokusu = pocet_pokusu + 1
             WHERE id = ?"
        );
        $stmt->bind_param('si', $chyba, $idOvereni);
        $stmt->execute();
        $stmt->close();

        return ['ok' => false, 'email' => $email, 'zprava' => $chyba];
    }
}

function pop3_radek($socket): string
{
    $line = fgets($socket, 4096);
    if ($line === false) {
        throw new RuntimeException('POP3 spojení bylo ukončeno.');
    }

    return rtrim($line, "\r\n");
}

function pop3_prikaz($socket, string $command): string
{
    fwrite($socket, $command . "\r\n");
    $response = pop3_radek($socket);
    if (!str_starts_with($response, '+OK')) {
        throw new RuntimeException('POP3 chyba: ' . $response);
    }

    return $response;
}

function pop3_cti_zpravu($socket, int $cislo): string
{
    pop3_prikaz($socket, 'RETR ' . $cislo);
    $radky = [];

    while (true) {
        $line = pop3_radek($socket);
        if ($line === '.') {
            break;
        }
        if (str_starts_with($line, '..')) {
            $line = substr($line, 1);
        }
        $radky[] = $line;
    }

    return implode("\n", $radky);
}

function je_nedorucenka(string $zprava): bool
{
    $text = mb_strtolower($zprava);
    $vzory = [
        'delivery status notification',
        'undelivered mail',
        'mail delivery failed',
        'failure notice',
        'returned mail',
        'recipient address rejected',
        'user unknown',
        'mailbox unavailable',
        'no such user',
        '550 ',
        '5.1.1',
    ];

    foreach ($vzory as $vzor) {
        if (str_contains($text, $vzor)) {
            return true;
        }
    }

    return false;
}

function emaily_ze_zpravy(string $zprava): array
{
    preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $zprava, $matches);
    $emaily = [];

    foreach ($matches[0] ?? [] as $email) {
        $email = normalizuj_email($email);
        if (validni_email($email)) {
            $emaily[$email] = true;
        }
    }

    return array_keys($emaily);
}

function zkontroluj_nedorucenky(mysqli $db): array
{
    $cfg = $GLOBALS['SECRETS']['mail']['hr'] ?? null;
    if (!is_array($cfg)) {
        return ['ok' => false, 'email' => '', 'zprava' => 'Chybí konfigurace mail/hr.'];
    }

    $host = trim((string)($cfg['pop3_host'] ?? $cfg['host'] ?? 'tan09.vas-server.cz'));
    $port = (int)($cfg['pop3_port'] ?? 995);
    $user = trim((string)($cfg['user'] ?? ''));
    $pass = (string)($cfg['pass'] ?? '');

    if ($host === '' || $port <= 0 || $user === '' || $pass === '') {
        return ['ok' => false, 'email' => '', 'zprava' => 'Neúplná konfigurace pro čtení schránky.'];
    }

    $odeslane = [];
    $result = $db->query(
        "SELECT email
         FROM aaa_email_overeni
         WHERE stav IN ('odeslano', 'nepotvrzeno')
         GROUP BY email"
    );
    while ($row = $result->fetch_assoc()) {
        $odeslane[normalizuj_email((string)$row['email'])] = true;
    }
    $result->free();

    $socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 20);
    if (!$socket) {
        return ['ok' => false, 'email' => '', 'zprava' => 'Nelze se připojit k POP3: ' . $errstr];
    }

    stream_set_timeout($socket, 30);
    $oznacene = 0;

    try {
        $uvod = pop3_radek($socket);
        if (!str_starts_with($uvod, '+OK')) {
            throw new RuntimeException('POP3 server neodpověděl správně: ' . $uvod);
        }

        pop3_prikaz($socket, 'USER ' . $user);
        pop3_prikaz($socket, 'PASS ' . $pass);
        $stat = pop3_prikaz($socket, 'STAT');
        $casti = preg_split('/\s+/', $stat);
        $pocet = isset($casti[1]) ? (int)$casti[1] : 0;

        for ($i = 1; $i <= $pocet; $i++) {
            $zprava = pop3_cti_zpravu($socket, $i);
            if (!je_nedorucenka($zprava)) {
                continue;
            }

            foreach (emaily_ze_zpravy($zprava) as $email) {
                if (empty($odeslane[$email])) {
                    continue;
                }

                $odpoved = mb_substr('Nedoručenka nalezena ve schránce ' . $user, 0, 65000);
                $stmt = $db->prepare(
                    "UPDATE aaa_email_overeni
                     SET stav = 'nedorucitelne',
                         posledni_kontrola = NOW(),
                         smtp_odpoved = ?
                     WHERE email = ?
                       AND stav IN ('odeslano', 'nepotvrzeno')"
                );
                $stmt->bind_param('ss', $odpoved, $email);
                $stmt->execute();
                $oznacene += $stmt->affected_rows;
                $stmt->close();
            }
        }

        pop3_prikaz($socket, 'QUIT');
        fclose($socket);
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
        }
        return ['ok' => false, 'email' => '', 'zprava' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'email' => '',
        'zprava' => 'Kontrola nedoručenek dokončena. Označeno záznamů: ' . $oznacene . '.',
    ];
}

$db = db_connect();

// Správa odesílání je dostupná pouze přihlášenému administrátorovi.
if (empty($_SESSION['login_ok']) || (int)($_SESSION['cb_user']['id_role'] ?? 0) !== 1) {
    http_response_code(403);
    exit('Přístup je povolen pouze administrátorovi.');
}

set_time_limit(0);
ignore_user_abort(true);

$csrf = nacti_csrf();
$vysledky = [];
$hlaska = '';
$kontrolaCas = date('j. n. Y H:i');

if (($_GET['export'] ?? '') === 'pdf') {
    exportuj_kontrolu_pdf(nacti_prehled_emailu($db), $kontrolaCas);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['ajax'] ?? '') === '1') {
    if (!over_csrf((string)($_POST['csrf'] ?? ''))) {
        odpovez_json(['ok' => false, 'zprava' => 'Neplatný bezpečnostní token formuláře.']);
    }

    $akce = (string)($_POST['akce'] ?? '');
    if ($akce === 'vsem_start') {
        $prehled = nacti_prehled_emailu($db);
        odpovez_json([
            'ok' => true,
            'celkem' => count(nacti_aktivni_emaily($db)),
            'tabulka' => html_radky_kontroly($prehled),
            'souhrn' => html_souhrn_kontroly($prehled),
        ]);
    }

    if ($akce === 'vsem_jeden') {
        $emaily = nacti_aktivni_emaily($db);

        if ($emaily === []) {
            $prehled = nacti_prehled_emailu($db);
            odpovez_json([
                'ok' => true,
                'hotovo' => true,
                'zbyva' => 0,
                'tabulka' => html_radky_kontroly($prehled),
                'souhrn' => html_souhrn_kontroly($prehled),
            ]);
        }

        $row = $emaily[0];
        $email = normalizuj_email((string)$row['email']);
        $zprava = '';
        $ok = true;

        if (!validni_email($email)) {
            $ok = false;
            $zprava = 'Neplatný formát e-mailu.';
        } else {
            $stmt = $db->prepare(
                "SELECT id
                 FROM aaa_email_overeni
                 WHERE email = ?
                   AND stav = 'potvrzeno'
                 LIMIT 1"
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $potvrzeny = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($potvrzeny) {
                $zprava = 'Přeskočeno, již potvrzeno.';
            } else {
                $vysledek = uloz_a_odesli($db, (int)$row['id_user'], $email, cele_jmeno($row));
                $ok = !empty($vysledek['ok']);
                $zprava = (string)$vysledek['zprava'];
            }
        }

        $prehled = nacti_prehled_emailu($db);
        odpovez_json([
            'ok' => $ok,
            'hotovo' => false,
            'zbyva' => count(nacti_aktivni_emaily($db)),
            'email' => $email,
            'zprava' => $zprava,
            'tabulka' => html_radky_kontroly($prehled),
            'souhrn' => html_souhrn_kontroly($prehled),
        ]);
    }

    odpovez_json(['ok' => false, 'zprava' => 'Neznámá akce.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && trim((string)($_POST['akce'] ?? '')) !== '') {
    $postCsrf = (string)($_POST['csrf'] ?? '');
    if (!over_csrf($postCsrf)) {
        $hlaska = 'Neplatný bezpečnostní token formuláře.';
    } else {
        $akce = (string)($_POST['akce'] ?? '');

        if ($akce === 'test') {
            $email = normalizuj_email((string)($_POST['email'] ?? ''));

            if (!validni_email($email)) {
                $hlaska = 'Zadej platnou e-mailovou adresu.';
            } else {
                $idUser = 0;
                $celeJmeno = '';
                $stmt = $db->prepare(
                    "SELECT id_user, jmeno, prijmeni
                     FROM user
                     WHERE LOWER(TRIM(email)) = ?
                       AND aktivni = 1
                     ORDER BY id_user
                     LIMIT 1"
                );
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $idUser = (int)$row['id_user'];
                    $celeJmeno = cele_jmeno($row);
                }

                if ($idUser <= 0) {
                    $hlaska = 'Přeskočeno, e-mail nepatří aktivnímu uživateli.';
                } else {
                    $vysledky[] = uloz_a_odesli($db, $idUser, $email, $celeJmeno);
                    $hlaska = (string)$vysledky[array_key_last($vysledky)]['zprava'];
                }
            }
        } elseif ($akce === 'vsem') {
            $sql = "SELECT id_user, jmeno, prijmeni, LOWER(TRIM(email)) AS email
                    FROM user
                    WHERE email IS NOT NULL
                      AND TRIM(email) <> ''
                      AND aktivni = 1
                      AND NOT EXISTS (
                          SELECT 1
                          FROM aaa_email_overeni e
                          WHERE e.email = LOWER(TRIM(user.email))
                      )
                    ORDER BY email";

            $result = $db->query($sql);
            $zpracovaneEmaily = [];
            while ($row = $result->fetch_assoc()) {
                $email = normalizuj_email((string)$row['email']);
                $idUser = (int)$row['id_user'];
                $celeJmeno = cele_jmeno($row);

                if (isset($zpracovaneEmaily[$email])) {
                    continue;
                }
                $zpracovaneEmaily[$email] = true;

                if (!validni_email($email)) {
                    $vysledky[] = ['ok' => false, 'email' => $email, 'zprava' => 'Neplatný formát e-mailu.'];
                    continue;
                }

                $stmt = $db->prepare(
                    "SELECT id
                     FROM aaa_email_overeni
                     WHERE email = ?
                       AND stav = 'potvrzeno'
                     LIMIT 1"
                );
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $potvrzeny = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($potvrzeny) {
                    $vysledky[] = ['ok' => true, 'email' => $email, 'zprava' => 'Přeskočeno, již potvrzeno.'];
                    continue;
                }

                $vysledky[] = uloz_a_odesli($db, $idUser, $email, $celeJmeno);
            }
            $result->free();
            $hlaska = 'Hromadné odesílání dokončeno.';
        } elseif ($akce === 'nedorucenky') {
            $vysledky[] = zkontroluj_nedorucenky($db);
            $hlaska = (string)$vysledky[array_key_last($vysledky)]['zprava'];
        }
    }
}

$prehledEmailu = nacti_prehled_emailu($db);
$exportPdfUrl = ($cbKontrolaEmailEmbed && function_exists('cb_url'))
    ? cb_url('/provoz.php?open_kontrola_email=1&export=pdf')
    : '?export=pdf';

?><?php if (!$cbKontrolaEmailEmbed): ?><!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kontrola e-mailů</title>
<?php endif; ?>
    <style>
        <?php if (!$cbKontrolaEmailEmbed): ?>
        body { margin:0; background:#f3f6f9; font-family:Arial,Helvetica,sans-serif; color:#1f2937; }
        <?php endif; ?>
        .cb-kontrola-email.wrap { max-width:1000px; margin:30px auto; padding:0 16px; }
        .cb-kontrola-email-scroll { height:calc(100vh - 190px); min-height:360px; overflow:auto; padding-right:8px; box-sizing:border-box; }
        .cb-kontrola-email .box { background:#fff; border:1px solid #d7dee7; border-radius:10px; padding:22px; margin-bottom:18px; }
        .cb-kontrola-email h1, .cb-kontrola-email h2 { margin-top:0; }
        .cb-kontrola-email label { display:block; font-weight:bold; margin-bottom:7px; }
        .cb-kontrola-email input[type=email] { width:100%; max-width:500px; box-sizing:border-box; padding:10px 12px; border:1px solid #b9c3cf; border-radius:6px; font-size:16px; }
        .cb-kontrola-email button, .cb-kontrola-email a.btn { border:0; border-radius:6px; padding:11px 18px; font-size:15px; font-weight:bold; cursor:pointer; text-decoration:none; display:inline-block; }
        .cb-kontrola-email .btn { background:#0b64b4; color:#fff; }
        .cb-kontrola-email .btn-danger { background:#b91c1c; color:#fff; }
        .cb-kontrola-email .kontrola-head { display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap; }
        .cb-kontrola-email .kontrola-head h2 { margin:0; }
        .cb-kontrola-email .kontrola-summary { flex:1 1 auto; text-align:center; font-weight:bold; color:#334155; }
        .cb-kontrola-email .row { display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
        .cb-kontrola-email .field { flex:1 1 420px; }
        .cb-kontrola-email .msg { padding:12px; background:#fff4d6; border:1px solid #f1cf69; border-radius:6px; margin-bottom:18px; }
        .cb-kontrola-email .progress { margin-top:10px; color:#0b64b4; font-weight:bold; }
        .cb-kontrola-email table { width:100%; min-width:760px; border-collapse:collapse; }
        .cb-kontrola-email th, .cb-kontrola-email td { padding:9px 10px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .cb-kontrola-email th:first-child, .cb-kontrola-email td:first-child { width:42px; text-align:right; }
        .cb-kontrola-email .group { padding-top:18px; background:#f3f6f9; }
        .cb-kontrola-email .table-head th { background:#eef4fb; font-weight:bold; }
        .cb-kontrola-email .ok { color:#166534; }
        .cb-kontrola-email .chyba { color:#b91c1c; }
        .cb-kontrola-email .small { color:#64748b; font-size:13px; }
    </style>
<?php if (!$cbKontrolaEmailEmbed): ?>
</head>
<body>
<?php endif; ?>
<div class="<?= $cbKontrolaEmailEmbed ? 'cb-kontrola-email cb-kontrola-email-scroll' : 'cb-kontrola-email wrap' ?>">
    <div class="box">
        <h1>Kontrola e-mailových adres</h1>
        <p class="small">Zdroj adres: <code>user.email</code>. Tabulka <code>user</code> se pouze čte.</p>
    </div>

    <?php if ($hlaska !== ''): ?>
        <div class="msg"><?= h($hlaska) ?></div>
    <?php endif; ?>

    <div class="box">
        <h2>Testovací odeslání</h2>
        <form method="post" data-cb-max-form="1">
            <?php if ($cbKontrolaEmailEmbed): ?><input type="hidden" name="open_kontrola_email" value="1"><?php endif; ?>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="akce" value="test">
            <div class="row">
                <div class="field">
                    <label for="email">E-mailová adresa</label>
                    <input id="email" name="email" type="email" required autocomplete="off">
                </div>
                <div>
                    <button class="btn" type="submit">Odeslat test</button>
                </div>
            </div>
        </form>
    </div>

    <div class="box">
        <h2>Hromadné odeslání</h2>
        <p>Odešle jednu zprávu na každou unikátní platnou adresu aktivního uživatele z <code>user.email</code>. Již potvrzené adresy přeskočí.</p>
        <form method="post" data-hromadne-form>
            <?php if ($cbKontrolaEmailEmbed): ?><input type="hidden" name="open_kontrola_email" value="1"><?php endif; ?>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="akce" value="vsem">
            <button class="btn-danger" type="submit">Odeslat všem</button>
        </form>
        <div class="progress" data-hromadne-progress></div>
    </div>

    <div class="box">
        <h2>Kontrola nedoručenek</h2>
        <p>Projede schránku <code>hr@comebacks.cz</code> přes POP3 SSL a nalezené nedoručenky označí jako <code>nedorucitelne</code>.</p>
        <form method="post" data-cb-max-form="1">
            <?php if ($cbKontrolaEmailEmbed): ?><input type="hidden" name="open_kontrola_email" value="1"><?php endif; ?>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="akce" value="nedorucenky">
            <button class="btn" type="submit">Zkontrolovat nedoručenky</button>
        </form>
    </div>

    <div class="box">
        <div class="kontrola-head">
            <h2>Kontrola: <?= h($kontrolaCas) ?></h2>
            <div class="kontrola-summary" data-kontrola-summary><?= html_souhrn_kontroly($prehledEmailu) ?></div>
            <a class="btn" href="<?= h($exportPdfUrl) ?>">Export PDF</a>
        </div>
        <table>
            <tbody data-kontrola-tbody>
            <?= html_radky_kontroly($prehledEmailu) ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    const form = document.querySelector('[data-hromadne-form]');
    const progress = document.querySelector('[data-hromadne-progress]');
    const tbody = document.querySelector('[data-kontrola-tbody]');
    const summary = document.querySelector('[data-kontrola-summary]');
    if (!form || !progress || !tbody || !summary) {
        return;
    }

    function ceskyCas(date) {
        return date.toLocaleString('cs-CZ', {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    function delkaBehu(ms) {
        const sekund = Math.round(ms / 1000);
        const min = Math.floor(sekund / 60);
        const sec = sekund % 60;
        return min + ' min ' + sec + ' s';
    }

    async function postAkce(sourceForm, akce, data) {
        const body = new FormData(sourceForm);
        body.set('ajax', '1');
        body.set('akce', akce);
        Object.entries(data || {}).forEach(([key, value]) => body.set(key, value));

        const response = await fetch(form.action || window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {
                'X-COMEBACK-KONTROLA-EMAIL': '1'
            }
        });
        return await response.json();
    }

    async function spustDavku(sourceForm, progressEl, startAkce, krokAkce, potvrzeni) {
        if (!window.confirm(potvrzeni)) {
            return;
        }

        const button = sourceForm.querySelector('button[type="submit"]');
        const start = new Date();
        button.disabled = true;
        progressEl.textContent = 'Spuštěno: ' + ceskyCas(start) + '. Připravuji seznam...';

        try {
            const init = await postAkce(sourceForm, startAkce);
            if (!init.ok) {
                progressEl.textContent = init.zprava || 'Dávku se nepodařilo spustit.';
                button.disabled = false;
                return;
            }

            const celkem = Number(init.celkem || 0);
            let odeslano = 0;
            let preskoceno = 0;
            let chyby = 0;
            if (init.tabulka) {
                tbody.innerHTML = init.tabulka;
            }
            if (init.souhrn) {
                summary.innerHTML = init.souhrn;
            }

            let krokIndex = 0;
            while (true) {
                krokIndex++;
                progressEl.textContent = 'Spuštěno: ' + ceskyCas(start) + '. Odesílám ' + krokIndex + ' / ' + celkem + '...';
                const krok = await postAkce(sourceForm, krokAkce);
                if (krok.tabulka) {
                    tbody.innerHTML = krok.tabulka;
                }
                if (krok.souhrn) {
                    summary.innerHTML = krok.souhrn;
                }
                if (krok.hotovo) {
                    break;
                }
                if (!krok.ok) {
                    chyby++;
                } else if ((krok.zprava || '').indexOf('Přeskočeno') === 0) {
                    preskoceno++;
                } else {
                    odeslano++;
                }
            }

            const konec = new Date();
            progressEl.textContent = 'Hotovo. Odesláno: ' + odeslano + ', přeskočeno: ' + preskoceno + ', chyby: ' + chyby + '. Spuštěno: ' + ceskyCas(start) + '. Skončeno: ' + ceskyCas(konec) + '. Doba běhu: ' + delkaBehu(konec - start) + '.';
        } catch (error) {
            progressEl.textContent = 'Odesílání se přerušilo: ' + error.message;
            button.disabled = false;
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        await spustDavku(form, progress, 'vsem_start', 'vsem_jeden', 'Opravdu odeslat potvrzovací e-mail na všechny nové adresy?');
    });
})();
</script>
<?php if (!$cbKontrolaEmailEmbed): ?>
</body>
</html>
<?php endif; ?>
