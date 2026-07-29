<?php
// admin_testy/kontrola_email.php * Verze: V1 * Aktualizace: 29.07.2026

declare(strict_types=1);

require_once __DIR__ . '/../../www/lib/session_boot.php';
require_once __DIR__ . '/../../www/db/db_connect.php';
require_once __DIR__ . '/../../www/config/secrets.php';
require_once __DIR__ . '/../../www/lib/mailer.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function absolutni_url(array $params = []): string
{
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/is/admin_testy/kontrola_email.php');
    $query = $params !== [] ? '?' . http_build_query($params) : '';

    return $scheme . '://' . $host . $script . $query;
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

function odesli_overovaci_email(string $email, string $potvrzovaciUrl): void
{
    $subject = 'Potvrzení platnosti e-mailové adresy';

    $body = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#eef6ff;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<div style="padding:28px 14px;">'
        . '<div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #cfe0f3;border-radius:8px;overflow:hidden;">'
        . '<div style="padding:22px 28px;background:#0b64b4;color:#ffffff;">'
        . '<h1 style="margin:0;font-size:22px;line-height:1.25;">Pizza Comeback</h1>'
        . '<p style="margin:6px 0 0;font-size:15px;">Ověření e-mailové adresy</p>'
        . '</div>'
        . '<div style="padding:26px 28px;">'
        . '<p style="margin:0 0 14px;">Dobrý den,</p>'
        . '<p style="margin:0 0 14px;">v našem interním systému evidujeme tuto e-mailovou adresu:</p>'
        . '<p style="margin:0 0 22px;font-weight:bold;">' . h($email) . '</p>'
        . '<p style="margin:0 0 22px;">Prosíme o potvrzení, že je adresa platná a používáte ji.</p>'
        . '<p style="margin:0 0 24px;text-align:center;">'
        . '<a href="' . h($potvrzovaciUrl) . '" style="display:inline-block;background:#0b64b4;color:#ffffff;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 26px;border-radius:6px;">Potvrdit e-mail</a>'
        . '</p>'
        . '<p style="margin:0;color:#64748b;font-size:13px;">Pokud tuto zprávu nepovažujete za určenou Vám, nemusíte na ni reagovat.</p>'
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
        'v našem interním systému evidujeme tuto e-mailovou adresu:',
        $email,
        '',
        'Prosíme o potvrzení, že je adresa platná a používáte ji.',
        '',
        'Potvrdit e-mail:',
        $potvrzovaciUrl,
        '',
        'Pizza Comeback',
        'Rolling dough s.r.o.',
        'IČ: 06636705',
        'DIČ: CZ06636705',
    ]);

    cb_mail_send('hr', $email, $subject, $body, $alt);
}

function uloz_a_odesli(mysqli $db, int $idUser, string $email): array
{
    $email = normalizuj_email($email);

    if (!validni_email($email)) {
        return ['ok' => false, 'email' => $email, 'zprava' => 'Neplatný formát e-mailu.'];
    }

    $token = vytvor_token();
    $url = absolutni_url(['token' => $token]);

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
        odesli_overovaci_email($email, $url);

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

$db = db_connect();

// Veřejná část: potvrzení e-mailu přes jedinečný token.
$token = trim((string)($_GET['token'] ?? ''));
if ($token !== '') {
    $potvrzeno = false;
    $zprava = 'Odkaz není platný.';

    if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        $stmt = $db->prepare(
            "SELECT id, stav
             FROM aaa_email_overeni
             WHERE token = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row) {
            if ((string)$row['stav'] === 'potvrzeno') {
                $potvrzeno = true;
                $zprava = 'Tento e-mail již byl potvrzen.';
            } else {
                $id = (int)$row['id'];
                $stmt = $db->prepare(
                    "UPDATE aaa_email_overeni
                     SET stav = 'potvrzeno',
                         potvrzeno = NOW(),
                         posledni_kontrola = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $potvrzeno = true;
                $zprava = 'E-mailová adresa byla úspěšně potvrzena.';
            }
        }
    }

    ?><!doctype html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Potvrzení e-mailu</title>
        <style>
            body { margin:0; background:#eef6ff; font-family:Arial,Helvetica,sans-serif; color:#111827; }
            .box { max-width:620px; margin:70px auto; background:#fff; border:1px solid #cfe0f3; border-radius:10px; padding:32px; box-sizing:border-box; text-align:center; }
            .ok { color:#166534; }
            .chyba { color:#b91c1c; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1 class="<?= $potvrzeno ? 'ok' : 'chyba' ?>"><?= h($zprava) ?></h1>
            <p>Pizza Comeback</p>
        </div>
    </body>
    </html><?php
    exit;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $stmt = $db->prepare(
                    "SELECT id_user
                     FROM user
                     WHERE LOWER(TRIM(email)) = ?
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
                }

                $vysledky[] = uloz_a_odesli($db, $idUser, $email);
            }
        } elseif ($akce === 'vsem') {
            $sql = "SELECT MIN(id_user) AS id_user, LOWER(TRIM(email)) AS email
                    FROM user
                    WHERE email IS NOT NULL
                      AND TRIM(email) <> ''
                    GROUP BY LOWER(TRIM(email))
                    ORDER BY email";

            $result = $db->query($sql);
            while ($row = $result->fetch_assoc()) {
                $email = normalizuj_email((string)$row['email']);
                $idUser = (int)$row['id_user'];

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

                $vysledky[] = uloz_a_odesli($db, $idUser, $email);
            }
            $result->free();
        }
    }
}

$prehled = $db->query(
    "SELECT stav, COUNT(*) AS pocet
     FROM aaa_email_overeni
     GROUP BY stav
     ORDER BY stav"
)->fetch_all(MYSQLI_ASSOC);

?><!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kontrola e-mailů</title>
    <style>
        body { margin:0; background:#f3f6f9; font-family:Arial,Helvetica,sans-serif; color:#1f2937; }
        .wrap { max-width:1000px; margin:30px auto; padding:0 16px; }
        .box { background:#fff; border:1px solid #d7dee7; border-radius:10px; padding:22px; margin-bottom:18px; }
        h1, h2 { margin-top:0; }
        label { display:block; font-weight:bold; margin-bottom:7px; }
        input[type=email] { width:100%; max-width:500px; box-sizing:border-box; padding:10px 12px; border:1px solid #b9c3cf; border-radius:6px; font-size:16px; }
        button { border:0; border-radius:6px; padding:11px 18px; font-size:15px; font-weight:bold; cursor:pointer; }
        .btn { background:#0b64b4; color:#fff; }
        .btn-danger { background:#b91c1c; color:#fff; }
        .row { display:flex; gap:10px; align-items:end; flex-wrap:wrap; }
        .field { flex:1 1 420px; }
        .msg { padding:12px; background:#fff4d6; border:1px solid #f1cf69; border-radius:6px; margin-bottom:18px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:9px 10px; border-bottom:1px solid #e5e7eb; text-align:left; vertical-align:top; }
        .ok { color:#166534; }
        .chyba { color:#b91c1c; }
        .small { color:#64748b; font-size:13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="box">
        <h1>Kontrola e-mailových adres</h1>
        <p class="small">Zdroj adres: <code>user.email</code>. Tabulka <code>user</code> se pouze čte.</p>
    </div>

    <?php if ($hlaska !== ''): ?>
        <div class="msg"><?= h($hlaska) ?></div>
    <?php endif; ?>

    <div class="box">
        <h2>Testovací odeslání</h2>
        <form method="post">
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
        <p>Odešle jednu zprávu na každou unikátní platnou adresu z <code>user.email</code>. Již potvrzené adresy přeskočí.</p>
        <form method="post" onsubmit="return confirm('Opravdu odeslat potvrzovací e-mail na všechny unikátní adresy?');">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="akce" value="vsem">
            <button class="btn-danger" type="submit">Odeslat všem</button>
        </form>
    </div>

    <?php if ($vysledky !== []): ?>
        <div class="box">
            <h2>Výsledek odesílání</h2>
            <table>
                <thead><tr><th>E-mail</th><th>Výsledek</th></tr></thead>
                <tbody>
                <?php foreach ($vysledky as $vysledek): ?>
                    <tr>
                        <td><?= h($vysledek['email']) ?></td>
                        <td class="<?= !empty($vysledek['ok']) ? 'ok' : 'chyba' ?>"><?= h($vysledek['zprava']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="box">
        <h2>Souhrn evidence</h2>
        <table>
            <thead><tr><th>Stav</th><th>Počet</th></tr></thead>
            <tbody>
            <?php foreach ($prehled as $radek): ?>
                <tr>
                    <td><?= h($radek['stav']) ?></td>
                    <td><?= (int)$radek['pocet'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="small">Pozdější vratné zprávy typu „user unknown“ tento soubor sám nečte. Pro jejich automatické zpracování bude potřeba samostatné čtení schránky pro nedoručené zprávy.</p>
    </div>
</div>
</body>
</html>
