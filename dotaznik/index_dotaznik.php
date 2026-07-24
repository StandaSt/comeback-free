<?php
declare(strict_types=1);

session_name('CB_DOTAZNIK');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . '/../www/db/db_connect.php';
require_once __DIR__ . '/../www/config/secrets.php';
require_once __DIR__ . '/../www/lib/mailer.php';

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function dotaznik_absolutni_url(string $path): string
{
    $https =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/dotaznik/index_dotaznik.php')));
    $dir = rtrim($dir === '/' ? '' : $dir, '/');

    return $scheme . '://' . $host . $dir . '/' . ltrim($path, '/');
}

function post_text(string $name, int $maxLength = 5000): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
}

// Overi jednoduche jmeno nebo prijmeni bez opakovani tri stejnych znaku za sebou.
function validni_jmeno(string $text): bool
{
    if (mb_strlen($text) < 2) {
        return false;
    }

    if (preg_match('/(.)\1\1/u', $text) === 1) {
        return false;
    }

    return preg_match('/^[\p{L}]+(?:[ -][\p{L}]+)*$/u', $text) === 1;
}

function post_ids(string $name): array
{
    $values = $_POST[$name] ?? [];
    if (!is_array($values)) {
        return [];
    }

    $ids = [];
    foreach ($values as $value) {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function post_allowed_values(string $name, array $allowed): array
{
    $values = $_POST[$name] ?? [];
    if (!is_array($values)) {
        return [];
    }

    $out = [];
    foreach ($values as $value) {
        $text = trim((string)$value);
        if (in_array($text, $allowed, true)) {
            $out[$text] = $text;
        }
    }
    return array_values($out);
}

// Prevede cesky telefon na jednotny tvar +420XXXXXXXXX, jinak vrati prazdny text.
function normalizuj_telefon(string $telefon): string
{
    $cisla = preg_replace('/\D+/', '', $telefon) ?? '';
    if (strlen($cisla) === 9) {
        return '+420' . $cisla;
    }

    if (strlen($cisla) === 12 && str_starts_with($cisla, '420')) {
        return '+' . $cisla;
    }

    if (strlen($cisla) === 14 && str_starts_with($cisla, '00420')) {
        return '+420' . substr($cisla, 5);
    }

    return '';
}

// Overi zakladni tvar e-mailu a rozumnou delku koncovky domeny.
function validni_email(string $email): bool
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    $casti = explode('.', substr(strrchr($email, '@') ?: '', 1));
    $koncovka = (string)end($casti);
    return preg_match('/^[a-z]{2,6}$/i', $koncovka) === 1;
}

// Posle uchazeci potvrzovaci e-mail se shrnutim verejneho dotazniku.
function odesli_overeni_vd_email(array $data, string $overovaciUrl, string $odmitnutiUrl): void
{
    $to = (string)$data['email'];
    $subject = 'Ověření dotazníku Pizza Comeback';
    $radky = [
        'Jméno' => (string)$data['jmeno'] . ' ' . (string)$data['prijmeni'],
        'Telefon' => (string)$data['telefon'],
        'E-mail' => (string)$data['email'],
        'Pozice' => (string)$data['pozice'],
        'Pracoviště' => (string)$data['pracoviste'],
        'Možný nástup' => (string)$data['mozny_nastup'] !== '' ? (string)$data['mozny_nastup'] : '-',
        'Očekávaná odměna' => $data['ocekavana_mzda'] !== null ? (string)$data['ocekavana_mzda'] . ' Kč/h' : '-',
    ];

    $tabulka = '';
    foreach ($radky as $label => $hodnota) {
        $tabulka .= '<tr>'
            . '<th style="padding:10px 12px;text-align:left;border-bottom:1px solid #d7e3f4;color:#244b7a;width:170px;">' . e($label) . '</th>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #d7e3f4;color:#111827;">' . e($hodnota) . '</td>'
            . '</tr>';
    }

    $body = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#eef6ff;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
        . '<div style="padding:28px 14px;">'
        . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #cfe0f3;border-radius:8px;overflow:hidden;">'
        . '<div style="padding:22px 28px;background:#0b64b4;color:#ffffff;">'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;">'
        . '<tr>'
        . '<td style="width:86px;vertical-align:middle;"><img src="' . e(dotaznik_absolutni_url('../www/img/logo_transparent_1.png')) . '" width="70" height="70" alt="Pizza Comeback" style="display:block;width:70px;height:70px;"></td>'
        . '<td style="vertical-align:middle;">'
        . '<h1 style="margin:0;font-size:22px;line-height:1.25;">Pizza Comeback</h1>'
        . '<p style="margin:6px 0 0;font-size:15px;">Ověření kontaktního formuláře</p>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</div>'
        . '<div style="padding:26px 28px;">'
        . '<p style="margin:0 0 14px;">Dobrý den,</p>'
        . '<p style="margin:0 0 14px;">děkujeme za vyplnění kontaktního formuláře společnosti Pizza Comeback.</p>'
        . '<p style="margin:0 0 22px;">Po potvrzení níže uvedených údajů Vás budeme co nejdříve kontaktovat.</p>'
        . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#f8fbff;border:1px solid #d7e3f4;margin:0 0 24px;">'
        . $tabulka
        . '</table>'
        . '<p style="margin:0 0 18px;text-align:center;"><a href="' . e($overovaciUrl) . '" style="display:inline-block;background:#0b64b4;color:#ffffff;text-decoration:none;font-weight:bold;font-size:17px;padding:14px 26px;border-radius:6px;">Potvrzuji uvedené údaje</a></p>'
        . '<p style="margin:0 0 26px;text-align:center;"><a href="' . e($odmitnutiUrl) . '" style="display:inline-block;background:#eef2f7;color:#334155;text-decoration:none;font-size:13px;padding:9px 14px;border-radius:5px;border:1px solid #cbd5e1;">Dotazník jsem nevyplnil. Prosím o odstranění mých údajů.</a></p>'
        . '<p style="margin:0;">personální oddělení<br>Pizza Comeback</p>'
        . '</div>'
        . '<div style="padding:16px 28px;background:#f3f7fc;color:#64748b;font-size:11px;line-height:1.45;border-top:1px solid #d7e3f4;">'
        . 'Pizza Comeback je provozována společností Rolling dough s.r.o.<br>'
        . 'Zapsaná v OR vedeném Městským soudem v Praze, oddíl C, vložka 285948, den zápisu: 28. 11. 2017<br>'
        . 'IČ: 06636705<br>'
        . 'DIČ: CZ06636705'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</body></html>';

    $alt = implode("\n", [
        'Dobrý den,',
        '',
        'děkujeme za vyplnění kontaktního formuláře společnosti Pizza Comeback.',
        'Po potvrzení níže uvedených údajů Vás budeme co nejdříve kontaktovat.',
        '',
        'Jméno: ' . $radky['Jméno'],
        'Telefon: ' . $radky['Telefon'],
        'E-mail: ' . $radky['E-mail'],
        'Pozice: ' . $radky['Pozice'],
        'Pracoviště: ' . $radky['Pracoviště'],
        'Možný nástup: ' . $radky['Možný nástup'],
        'Očekávaná odměna: ' . $radky['Očekávaná odměna'],
        '',
        'Potvrzení údajů: ' . $overovaciUrl,
        'Odstranění údajů: ' . $odmitnutiUrl,
        '',
        'personální oddělení',
        'Pizza Comeback',
        '',
        'Pizza Comeback je provozována společností Rolling dough s.r.o.',
        'Zapsaná v OR vedeném Městským soudem v Praze, oddíl C, vložka 285948, den zápisu: 28. 11. 2017',
        'IČ: 06636705',
        'DIČ: CZ06636705',
    ]);

    cb_mail_send('hr', $to, $subject, $body, $alt);
}

function klient_ip(): string
{
    return mb_substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
}

function novy_csrf_token(): string
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['dotaznik_csrf'] = $token;
    $_SESSION['dotaznik_zobrazen'] = time();
    return $token;
}

function nacti_ciselniky(mysqli $db): array
{
    $pozice = [];

    $result = $db->query("SELECT id_slot, slot FROM cis_slot WHERE LOWER(slot) IN ('instor', 'instore', 'kurýr', 'kuryr') ORDER BY id_slot");
    while ($row = $result->fetch_assoc()) {
        $nazev = (string)$row['slot'];
        $kod = mb_strtolower($nazev);
        $pozice[] = [
            'id' => (int)$row['id_slot'],
            'nazev' => str_contains($kod, 'kur') ? 'Kurýr' : 'Instor',
        ];
    }
    $result->free();

    return $pozice;
}

function formatuj_datum_cas(string $datumCas): string
{
    $datum = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumCas);
    return $datum ? $datum->format('d. m. Y H:i') : $datumCas;
}

function existuje_pouzity_kontakt(mysqli $db, string $telefonNormalizovany, string $email): bool
{
    $stmt = $db->prepare('
        SELECT 1
        FROM hr_vd
        WHERE telefon_normalizovany = ?
           OR email = ?
        LIMIT 1
    ');
    $stmt->bind_param('ss', $telefonNormalizovany, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($row)) {
        return true;
    }

    $stmt = $db->prepare('
        SELECT 1
        FROM hr_telefon
        WHERE telefon_normalizovany = ?
        LIMIT 1
    ');
    $stmt->bind_param('s', $telefonNormalizovany);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($row)) {
        return true;
    }

    $stmt = $db->prepare('
        SELECT 1
        FROM hr_email
        WHERE email = ?
        LIMIT 1
    ');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row);
}

$chyby = [];
// Vysledek posledniho odeslani se prenasi pres session po presmerovani.
$vysledekOdeslani = is_array($_SESSION['dotaznik_vysledek'] ?? null) ? $_SESSION['dotaznik_vysledek'] : null;
unset($_SESSION['dotaznik_vysledek']);
$dbChyba = '';
$db = null;
$pozice = [];

try {
    $db = db_connect();
    $pozice = nacti_ciselniky($db);
} catch (Throwable $e) {
    error_log('Dotaznik uchazece - DB chyba: ' . $e->getMessage());
    $dbChyba = 'Dotazník je nyní dočasně nedostupný. Zkuste to prosím později.';
}

if (!isset($_SESSION['dotaznik_csrf']) || !is_string($_SESSION['dotaznik_csrf'])) {
    novy_csrf_token();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db instanceof mysqli) {
    $csrf = post_text('csrf', 64);
    $sessionCsrf = (string)($_SESSION['dotaznik_csrf'] ?? '');

    if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        $chyby[] = 'Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.';
    }
    if (post_text('dot_kontrola_pole', 200) !== '') {
        $chyby[] = 'Formulář se nepodařilo odeslat.';
    }
    if ((time() - (int)($_SESSION['dotaznik_zobrazen'] ?? 0)) < 3) {
        $chyby[] = 'Formulář byl odeslán příliš rychle. Zkontrolujte prosím údaje.';
    }
    $jmeno = post_text('jmeno', 60);
    $prijmeni = post_text('prijmeni', 80);
    $telefon = post_text('telefon', 18);
    $telefonNormalizovany = normalizuj_telefon($telefon);
    $email = mb_strtolower(post_text('email', 150));
    $moznyNastup = post_text('mozny_nastup', 10);
    $mzdaText = str_replace(',', '.', post_text('ocekavana_mzda', 20));
    $povidani = post_text('povidani', 5000);
    $poziceIds = post_ids('pozice');
    $mesta = post_allowed_values('mesto', ['Praha', 'Plzeň']);
    $pracovistePreference = implode(', ', $mesta);
    $souhlas = isset($_POST['souhlas']) ? 1 : 0;

    if (!validni_jmeno($jmeno)) $chyby[] = 'Vyplňte platné křestní jméno, alespoň 2 znaky.';
    if (!validni_jmeno($prijmeni)) $chyby[] = 'Vyplňte platné příjmení, alespoň 2 znaky.';
    if ($telefonNormalizovany === '') $chyby[] = 'Vyplňte platné telefonní číslo.';
    if ($email === '' || !validni_email($email)) $chyby[] = 'Vyplňte platnou e-mailovou adresu.';
    if ($poziceIds === []) $chyby[] = 'Vyberte alespoň jednu pozici.';
    if ($mesta === []) $chyby[] = 'Vyberte alespoň jedno město, ve kterém chcete pracovat.';
    if ($souhlas !== 1) $chyby[] = 'Pro odeslání je potřeba souhlasit se zpracováním údajů pro účely náboru.';

    if ($moznyNastup !== '') {
        $datum = DateTimeImmutable::createFromFormat('Y-m-d', $moznyNastup);
        if (!$datum || $datum->format('Y-m-d') !== $moznyNastup) {
            $chyby[] = 'Datum možného nástupu není platné.';
        }
    }

    $ocekavanaMzda = null;
    if ($mzdaText !== '') {
        if (preg_match('/^\d+$/', $mzdaText) !== 1) {
            $chyby[] = 'Představa o hodinové odměně musí být celé číslo.';
        } elseif ((int)$mzdaText < 0 || (int)$mzdaText > 600) {
            $chyby[] = 'Představa o hodinové odměně může být maximálně 600 Kč/h.';
        } else {
            $ocekavanaMzda = (int)$mzdaText;
        }
    }

    if ($chyby === []) {
        // Zabrani opakovanemu ulozeni kontaktu, ktery uz evidujeme ve VD nebo u zamestnance.
        if (existuje_pouzity_kontakt($db, $telefonNormalizovany, $email)) {
            $chyby[] = 'Tento e-mail nebo telefon již byl v minulosti použit a dotazník tedy nelze uložit.';
        }
    }

    if ($chyby === []) {
        try {
            $db->begin_transaction();

            // Stav 0 znamena neovereny verejny dotaznik cekajici na potvrzeni e-mailu.
            $idStav = 0;
            $idZdroj = 1;
            $idSlot = (int)$poziceIds[0];
            $ip = klient_ip();
            $userAgent = mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
            $overovaciToken = bin2hex(random_bytes(32));
            $overovaciHash = hash('sha256', $overovaciToken);

            // Ulozi verejny dotaznik jako samostatny naborovy pripad.
            $stmt = $db->prepare('
                INSERT INTO hr_vd (
                    id_vd_stav, id_vd_zdroj, id_person,
                    jmeno, prijmeni, telefon, telefon_normalizovany, email,
                    id_slot, pracoviste_preference, mozny_nastup, ocekavana_mzda, povidani,
                    ip_adresa, user_agent, odeslano, aktivni
                ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, \'\'), ?, ?, ?, ?, NOW(), 1)
            ');
            $stmt->bind_param(
                'iisssssissdsss',
                $idStav,
                $idZdroj,
                $jmeno,
                $prijmeni,
                $telefon,
                $telefonNormalizovany,
                $email,
                $idSlot,
                $pracovistePreference,
                $moznyNastup,
                $ocekavanaMzda,
                $povidani,
                $ip,
                $userAgent
            );
            $stmt->execute();
            $idVd = (int)$db->insert_id;
            $stmt->close();

            // Ulozi hash overovaciho tokenu, skutecny token je pouze v e-mailovem odkazu.
            $idDotaznikTyp = 1;
            $stmt = $db->prepare('
                INSERT INTO hr_vd_token (id_vd, token_hash, id_dotaznik_typ, platnost_do, pouzito, vytvoreno, aktivni)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR), NULL, NOW(), 1)
            ');
            $stmt->bind_param('isi', $idVd, $overovaciHash, $idDotaznikTyp);
            $stmt->execute();
            $stmt->close();

            $poziceNazev = '-';
            foreach ($pozice as $polozka) {
                if ((int)$polozka['id'] === $idSlot) {
                    $poziceNazev = (string)$polozka['nazev'];
                    break;
                }
            }

            odesli_overeni_vd_email([
                'jmeno' => $jmeno,
                'prijmeni' => $prijmeni,
                'telefon' => $telefonNormalizovany,
                'email' => $email,
                'pozice' => $poziceNazev,
                'pracoviste' => $pracovistePreference,
                'mozny_nastup' => $moznyNastup,
                'ocekavana_mzda' => $ocekavanaMzda,
            ], dotaznik_absolutni_url('overit.php?t=' . rawurlencode($overovaciToken)), dotaznik_absolutni_url('overit.php?a=odmitnout&t=' . rawurlencode($overovaciToken)));

            $db->commit();
            $_SESSION['dotaznik_vysledek'] = [
                'stav' => 'cekame_na_overeni',
                'email' => $email,
            ];
            unset($_SESSION['dotaznik_csrf'], $_SESSION['dotaznik_zobrazen']);
            header('Location: ./', true, 303);
            exit;
        } catch (Throwable $e) {
            $db->rollback();
            error_log('Dotaznik uchazece - chyba ulozeni: ' . $e->getMessage());
            $chyby[] = 'Dotazník se nepodařilo uložit. Zkuste to prosím znovu.';
        }
    }
}

$csrf = (string)($_SESSION['dotaznik_csrf'] ?? novy_csrf_token());
$vybranePozice = post_ids('pozice');
$vybranaMesta = post_allowed_values('mesto', ['Praha', 'Plzeň']);
?><!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Máte zájem pracovat v Pizza Comeback? Vyplňte krátký dotazník a ozveme se vám.">
    <title>Práce v Pizza Comeback</title>
    <link rel="stylesheet" href="dot_assets/dotaznik.css">
</head>
<body>
<div class="page">
    <header class="hero">
        <div class="hero-content">
            <h1>Přidejte se k nám</h1>
            <p>Hledáme nové kolegy do restaurací i na rozvoz.<p>
            <p>Chcete připravovat pizzu nebo objednávky rozvážet?<br>
            Je to možné. Volba je na Vás.<p> 
            <p>Vyplňte dotazník vpravo a budeme Vás co nejdříve kontaktovat.<br>
            Domluvíme si pohovor a probereme detaily případné spolupráce.</p>
        </div>
    </header>

    <main class="form-column">
        <?php if (($vysledekOdeslani['stav'] ?? '') === 'cekame_na_overeni'): ?>
            <section class="success-card">
                <div class="success-icon">✓</div>
                <h2>Děkujeme za váš zájem</h2>
                <p>Dotazník jsme přijali. Na adresu <?= e((string)$vysledekOdeslani['email']) ?> jsme odeslali shrnutí právě odeslaného dotazníku.</p>
                <p><strong>Pro zařazení do náboru je potřeba údaje potvrdit odkazem v e-mailu.</strong></p>
                <a class="button secondary" href="./">Zpět na dotazník</a>
            </section>
        <?php else: ?>
            <section class="form-card">
                <div class="form-headline">
                    <a class="brand" href="https://www.pizzacomeback.cz/" target="_blank" rel="noopener">
                        <img src="../www/img/logo_transparent_1.png" alt="Pizza Comeback bez ramecku">
                    </a>

                    <div class="form-heading">
                        <span>První kontakt</span>
                        <h2>Sdělte nám prosím Vaše kontaktní údaje</h2>
                        <p>Údaje použijeme k tomu, abychom Vás mohli kontaktovat ohledně pracovních možností.</p>
                    </div>
                </div>

                <?php if ($dbChyba !== ''): ?><div class="message error"><?= e($dbChyba) ?></div><?php endif; ?>
                <?php if ($chyby !== []): ?>
                    <div class="message error"><strong>Formulář prosím zkontrolujte:</strong><ul><?php foreach ($chyby as $chyba): ?><li><?= e($chyba) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if ($dbChyba === ''): ?>
                <form method="post" action="./" autocomplete="on">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <div class="honeypot" aria-hidden="true"><label>Kontrola<input type="text" name="dot_kontrola_pole" tabindex="-1" autocomplete="new-password"></label></div>

                    <fieldset>
                        <legend>Kontaktní údaje</legend>
                        <div class="grid two">
                            <label class="field"><span>Křestní jméno *</span><input type="text" name="jmeno" value="<?= e(post_text('jmeno', 60)) ?>" maxlength="60" autocomplete="given-name" required></label>
                            <label class="field"><span>Příjmení *</span><input type="text" name="prijmeni" value="<?= e(post_text('prijmeni', 80)) ?>" maxlength="80" autocomplete="family-name" required></label>
                        </div>
                        <div class="grid two">
                            <label class="field"><span>Telefon *</span><input type="tel" name="telefon" value="<?= e(post_text('telefon', 18)) ?>" maxlength="18" autocomplete="tel" placeholder="+420 777 123 456" required></label>
                            <label class="field"><span>E-mail *</span><input type="email" name="email" value="<?= e(post_text('email', 150)) ?>" maxlength="150" autocomplete="email" placeholder="vas@email.cz" required></label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>O jakou pozici máte zájem?</legend>
                        <div class="position-grid">
                            <?php foreach ($pozice as $polozka): ?>
                                <?php $jeKuryr = $polozka['nazev'] === 'Kurýr'; ?>
                                <label class="position-card">
                                    <span class="position-main">
                                        <input type="checkbox" name="pozice[]" value="<?= $polozka['id'] ?>" <?= in_array($polozka['id'], $vybranePozice, true) ? 'checked' : '' ?>>
                                        <strong><?= e($polozka['nazev']) ?></strong>
                                    </span>
                                    <span class="position-points">
                                        <ul>
                                            <?php if ($jeKuryr): ?>
                                                <li>Rozvoz objednávek zákazníkům</li>
                                                <li>Komunikace se zákazníky</li>
                                                <li>Péče o svěřené vozidlo</li>
                                                <li>Směnný provoz</li>
                                            <?php else: ?>
                                                <li>Příprava pizzy</li>
                                                <li>Obsluha zákazníků</li>
                                                <li>Práce v restauraci</li>
                                                <li>Směnný provoz</li>
                                            <?php endif; ?>
                                        </ul>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Kde chcete pracovat?</legend>
                        <div class="city-grid">
                            <?php foreach (['Praha', 'Plzeň'] as $mestoVolba): ?>
                                <label class="city-choice">
                                    <input type="checkbox" name="mesto[]" value="<?= e($mestoVolba) ?>" <?= in_array($mestoVolba, $vybranaMesta, true) ? 'checked' : '' ?>>
                                    <span><?= e($mestoVolba) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Ještě pár informací</legend>
                        <div class="grid two">
                            <label class="field"><span>Nástup možný od:</span><input type="date" name="mozny_nastup" value="<?= e(post_text('mozny_nastup', 10)) ?>"></label>
                            <label class="field"><span>Představa o hodinové odměně</span><div class="input-suffix"><input type="number" name="ocekavana_mzda" value="<?= e(post_text('ocekavana_mzda', 20)) ?>" min="0" max="600" step="1"><span>Kč/h</span></div></label>
                        </div>
                        <label class="field"><span>Napište nám něco o sobě</span><textarea name="povidani" rows="2" maxlength="5000" placeholder="Například jakou máte praxi, jaké směny vám vyhovují nebo proč vás práce u nás zaujala."><?= e(post_text('povidani', 5000)) ?></textarea><small>Nemusí to být formální. Stačí pár vět.</small></label>
                    </fieldset>

                    <label class="consent"><input type="checkbox" name="souhlas" value="1" <?= isset($_POST['souhlas']) ? 'checked' : '' ?> required><span>Souhlasím se zpracováním uvedených osobních údajů společností Rolling dough s.r.o. IČ: 06636705,<br>provozovatelem Pizza Comeback pro účely výběrového řízení.</span></label>
                    <button class="button primary" type="submit">Odeslat dotazník <span>→</span></button>
                </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
