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

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function post_text(string $name, int $maxLength = 5000): string
{
    $value = trim((string)($_POST[$name] ?? ''));
    return mb_strlen($value) > $maxLength ? mb_substr($value, 0, $maxLength) : $value;
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

function normalizuj_telefon(string $telefon): string
{
    $plus = str_starts_with(trim($telefon), '+');
    $cisla = preg_replace('/\D+/', '', $telefon) ?? '';
    return ($plus ? '+' : '') . $cisla;
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

function nacti_pracoviste_ids(mysqli $db, string $mesto): array
{
    $stmt = $db->prepare('SELECT id_pob FROM pobocka WHERE mesto = ? AND aktivni = 1 AND id_pob > 0 ORDER BY id_pob');
    $stmt->bind_param('s', $mesto);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id_pob'];
    }

    $stmt->close();
    return $ids;
}

function formatuj_datum_cas(string $datumCas): string
{
    $datum = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumCas);
    return $datum ? $datum->format('d. m. Y H:i') : $datumCas;
}

function najdi_presnou_duplicitu(mysqli $db, string $jmeno, string $prijmeni, string $telefonNormalizovany, string $email): ?array
{
    $stmt = $db->prepare('
        SELECT id_uchazec, zadano
        FROM hr_uchazec
        WHERE jmeno = ?
            AND prijmeni = ?
            AND telefon_normalizovany = ?
            AND email = ?
        ORDER BY zadano DESC, id_uchazec DESC
        LIMIT 1
    ');
    $stmt->bind_param('ssss', $jmeno, $prijmeni, $telefonNormalizovany, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function najdi_kontaktni_duplicitu(mysqli $db, string $jmeno, string $prijmeni, string $telefonNormalizovany, string $email): ?array
{
    $stmt = $db->prepare('
        SELECT id_uchazec, jmeno, prijmeni, telefon, telefon_normalizovany, email, zadano
        FROM hr_uchazec
        WHERE (telefon_normalizovany = ? OR email = ?)
            AND NOT (jmeno = ? AND prijmeni = ? AND telefon_normalizovany = ? AND email = ?)
        ORDER BY zadano DESC, id_uchazec DESC
        LIMIT 1
    ');
    $stmt->bind_param('ssssss', $telefonNormalizovany, $email, $jmeno, $prijmeni, $telefonNormalizovany, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

$chyby = [];
$odeslano = !empty($_SESSION['dotaznik_odeslano']);
unset($_SESSION['dotaznik_odeslano']);
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
    if ((time() - (int)($_SESSION['dotaznik_posledni_odeslani'] ?? 0)) < 60) {
        $chyby[] = 'Dotazník už byl nedávno odeslán. Vyčkejte prosím chvíli.';
    }

    $jmeno = post_text('jmeno', 60);
    $prijmeni = post_text('prijmeni', 80);
    $telefon = post_text('telefon', 30);
    $telefonNormalizovany = normalizuj_telefon($telefon);
    $email = mb_strtolower(post_text('email', 150));
    $moznyNastup = post_text('mozny_nastup', 10);
    $mzdaText = str_replace(',', '.', post_text('ocekavana_mzda', 20));
    $povidani = post_text('povidani', 5000);
    $poziceIds = post_ids('pozice');
    $mesto = post_text('mesto', 20);
    $souhlas = isset($_POST['souhlas']) ? 1 : 0;

    if ($jmeno === '') $chyby[] = 'Vyplňte křestní jméno.';
    if ($prijmeni === '') $chyby[] = 'Vyplňte příjmení.';
    if ($telefon === '' || mb_strlen($telefonNormalizovany) < 9) $chyby[] = 'Vyplňte platné telefonní číslo.';
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) $chyby[] = 'Vyplňte platnou e-mailovou adresu.';
    if ($poziceIds === []) $chyby[] = 'Vyberte alespoň jednu pozici.';
    if (!in_array($mesto, ['Praha', 'Plzeň'], true)) $chyby[] = 'Vyberte město, ve kterém chcete pracovat.';
    if ($souhlas !== 1) $chyby[] = 'Pro odeslání je potřeba souhlasit se zpracováním údajů pro účely náboru.';

    if ($moznyNastup !== '') {
        $datum = DateTimeImmutable::createFromFormat('Y-m-d', $moznyNastup);
        if (!$datum || $datum->format('Y-m-d') !== $moznyNastup) {
            $chyby[] = 'Datum možného nástupu není platné.';
        }
    }

    $ocekavanaMzda = null;
    if ($mzdaText !== '') {
        if (!is_numeric($mzdaText) || (float)$mzdaText < 0) {
            $chyby[] = 'Představa o hodinové odměně musí být číslo.';
        } else {
            $ocekavanaMzda = round((float)$mzdaText, 2);
        }
    }

    $presnaDuplicita = null;
    $kontaktniDuplicita = null;
    $idUchazecDuplicita = null;

    if ($chyby === []) {
        $presnaDuplicita = najdi_presnou_duplicitu($db, $jmeno, $prijmeni, $telefonNormalizovany, $email);
        if ($presnaDuplicita !== null) {
            $zadano = (string)$presnaDuplicita['zadano'];
            $zadanoCas = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $zadano);
            if ($zadanoCas && $zadanoCas >= new DateTimeImmutable('-30 days')) {
                $chyby[] = 'Vámi zadané údaje již byly použity ' . formatuj_datum_cas($zadano) . '.';
            } else {
                $idUchazecDuplicita = (int)$presnaDuplicita['id_uchazec'];
            }
        }

        if ($chyby === []) {
            $kontaktniDuplicita = najdi_kontaktni_duplicitu($db, $jmeno, $prijmeni, $telefonNormalizovany, $email);
            if ($idUchazecDuplicita === null && $kontaktniDuplicita !== null) {
                $idUchazecDuplicita = (int)$kontaktniDuplicita['id_uchazec'];
            }
        }
    }

    if ($chyby === []) {
        try {
            $db->begin_transaction();

            $stav = $db->query("SELECT id_uchazec_stav FROM hr_uchazec_stav WHERE kod = 'novy' AND aktivni = 1 LIMIT 1")->fetch_assoc();
            if (!$stav) throw new RuntimeException('Chybí stav novy.');
            $idStav = (int)$stav['id_uchazec_stav'];

            $zdroj = $db->query("SELECT id_uchazec_zdroj FROM hr_uchazec_zdroj WHERE nazev = 'Veřejný dotazník' AND aktivni = 1 LIMIT 1")->fetch_assoc();
            $idZdroj = $zdroj ? (int)$zdroj['id_uchazec_zdroj'] : null;

            $stmt = $db->prepare("INSERT INTO hr_uchazec (
                id_uchazec_stav, id_uchazec_zdroj, id_uchazec_duplicita, jmeno, prijmeni, telefon,
                telefon_normalizovany, email, mozny_nastup, ocekavana_mzda,
                povidani, prvni_kontakt, posledni_aktivita, zadal, zadano, aktivni
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, NOW(), NOW(), NULL, NOW(), 1)");
            $stmt->bind_param('iiissssssds', $idStav, $idZdroj, $idUchazecDuplicita, $jmeno, $prijmeni, $telefon, $telefonNormalizovany, $email, $moznyNastup, $ocekavanaMzda, $povidani);
            $stmt->execute();
            $idUchazec = (int)$db->insert_id;
            $stmt->close();

            $stmt = $db->prepare("INSERT INTO hr_uchazec_stav_historie (id_uchazec, id_uchazec_stav, duvod, zadal, zadano) VALUES (?, ?, 'Nový uchazeč z veřejného dotazníku', NULL, NOW())");
            $stmt->bind_param('ii', $idUchazec, $idStav);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_uchazec_pozice (id_uchazec, id_slot, hlavni, poznamka, zadal, zadano, aktivni) VALUES (?, ?, ?, NULL, NULL, NOW(), 1)');
            foreach ($poziceIds as $i => $idSlot) {
                $hlavni = $i === 0 ? 1 : 0;
                $stmt->bind_param('iii', $idUchazec, $idSlot, $hlavni);
                $stmt->execute();
            }
            $stmt->close();

            $pracovisteIds = nacti_pracoviste_ids($db, $mesto);
            if ($pracovisteIds === []) throw new RuntimeException('Chybí pracoviště pro zvolené město.');

            $poznamkaPracoviste = 'Zvolené město ve veřejném dotazníku: ' . $mesto;
            $stmt = $db->prepare('INSERT INTO hr_uchazec_pracoviste (id_uchazec, id_pob, hlavni, poznamka, zadal, zadano, aktivni) VALUES (?, ?, ?, ?, NULL, NOW(), 1)');
            foreach ($pracovisteIds as $i => $idPob) {
                $hlavni = $i === 0 ? 1 : 0;
                $stmt->bind_param('iiis', $idUchazec, $idPob, $hlavni, $poznamkaPracoviste);
                $stmt->execute();
            }
            $stmt->close();

            $typ = $db->query("SELECT id_dotaznik_typ FROM hr_dotaznik_typ WHERE kod = 'prvni_kontakt' AND aktivni = 1 ORDER BY verze DESC LIMIT 1")->fetch_assoc();
            if (!$typ) throw new RuntimeException('Chybí dotazník prvni_kontakt.');
            $idDotaznikTyp = (int)$typ['id_dotaznik_typ'];

            $dataJson = json_encode([
                'jmeno' => $jmeno,
                'prijmeni' => $prijmeni,
                'telefon' => $telefon,
                'email' => $email,
                'pozice' => $poziceIds,
                'mesto' => $mesto,
                'mozny_nastup' => $moznyNastup,
                'ocekavana_mzda' => $ocekavanaMzda,
                'povidani' => $povidani,
                'souhlas' => $souhlas,
                'souhlas_kdy' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $ip = klient_ip();
            $userAgent = mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
            $stmt = $db->prepare("INSERT INTO hr_uchazec_dotaznik (
                id_uchazec, id_dotaznik_typ, stav, odeslano, otevreno, ulozeno,
                data_json, ip_adresa, user_agent, zadal, zadano
            ) VALUES (?, ?, 'vyplnen', NOW(), NULL, NOW(), ?, ?, ?, NULL, NOW())");
            $stmt->bind_param('iisss', $idUchazec, $idDotaznikTyp, $dataJson, $ip, $userAgent);
            $stmt->execute();
            $stmt->close();

            $aktivitaTyp = $db->query("SELECT id_uchazec_aktivita_typ FROM hr_uchazec_aktivita_typ WHERE kod = 'systemova_udalost' AND aktivni = 1 LIMIT 1")->fetch_assoc();
            if (!$aktivitaTyp) throw new RuntimeException('Chybí typ aktivity systemova_udalost.');
            $idAktivitaTyp = (int)$aktivitaTyp['id_uchazec_aktivita_typ'];
            $predmet = 'Dorazil veřejný dotazník';
            $obsah = 'Uchazeč odeslal veřejný dotazník pro první kontakt.';
            $stmt = $db->prepare('INSERT INTO hr_uchazec_aktivita (id_uchazec, id_uchazec_aktivita_typ, provedeno_kdy, predmet, obsah, id_user, zadano) VALUES (?, ?, NOW(), ?, ?, NULL, NOW())');
            $stmt->bind_param('iiss', $idUchazec, $idAktivitaTyp, $predmet, $obsah);
            $stmt->execute();
            $stmt->close();

            if ($presnaDuplicita !== null && $idUchazecDuplicita !== null) {
                $predmet = 'Opakovaný kontakt uchazeče';
                $obsah = 'Uchazeč zadal stejné údaje jako u staršího záznamu #' . $idUchazecDuplicita . '. Původní záznam byl vytvořen ' . formatuj_datum_cas((string)$presnaDuplicita['zadano']) . '.';
                $stmt = $db->prepare('INSERT INTO hr_uchazec_aktivita (id_uchazec, id_uchazec_aktivita_typ, provedeno_kdy, predmet, obsah, id_user, zadano) VALUES (?, ?, NOW(), ?, ?, NULL, NOW())');
                $stmt->bind_param('iiss', $idUchazec, $idAktivitaTyp, $predmet, $obsah);
                $stmt->execute();
                $stmt->close();
            }

            if ($kontaktniDuplicita !== null) {
                $idKontaktniDuplicita = (int)$kontaktniDuplicita['id_uchazec'];
                $predmet = 'Možná duplicita kontaktu';
                $obsah = 'Stejný telefon nebo e-mail už existuje u uchazeče #' . $idKontaktniDuplicita . ': '
                    . trim((string)$kontaktniDuplicita['jmeno'] . ' ' . (string)$kontaktniDuplicita['prijmeni'])
                    . ', telefon ' . (string)$kontaktniDuplicita['telefon']
                    . ', e-mail ' . (string)$kontaktniDuplicita['email']
                    . ', záznam vytvořen ' . formatuj_datum_cas((string)$kontaktniDuplicita['zadano']) . '.';
                $stmt = $db->prepare('INSERT INTO hr_uchazec_aktivita (id_uchazec, id_uchazec_aktivita_typ, provedeno_kdy, predmet, obsah, id_user, zadano) VALUES (?, ?, NOW(), ?, ?, NULL, NOW())');
                $stmt->bind_param('iiss', $idUchazec, $idAktivitaTyp, $predmet, $obsah);
                $stmt->execute();
                $stmt->close();
            }

            $db->commit();
            $_SESSION['dotaznik_posledni_odeslani'] = time();
            $_SESSION['dotaznik_odeslano'] = 1;
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
        <a class="brand" href="https://www.pizzacomeback.cz/" target="_blank" rel="noopener">
            <img src="https://www.comebacks.cz/img/logo_comeback.png" alt="Pizza Comeback">
        </a>
        <div class="hero-content">
            <span class="eyebrow">PRÁCE V PIZZA COMEBACK</span>
            <h1>Máte zájem pracovat v Pizza Comeback?</h1>
            <p>Vyplňte prosím několik základních údajů. Budeme Vás co nejdříve kontaktovat ohledně dalšího postupu.</p>
        </div>
    </header>

    <main class="form-column">
        <?php if ($odeslano): ?>
            <section class="success-card">
                <div class="success-icon">✓</div>
                <h2>Děkujeme za váš zájem</h2>
                <p>Dotazník jsme v pořádku přijali. Ozveme se vám na uvedený telefon nebo e-mail.</p>
                <a class="button secondary" href="./">Zpět na dotazník</a>
            </section>
        <?php else: ?>
            <section class="form-card">
                <div class="form-heading">
                    <span>První kontakt</span>
                    <h2>Sdělte nám prosím Vaše kontaktní údaje</h2>
                    <p>Údaje použijeme k tomu, abychom Vás mohli kontaktovat ohledně pracovních možností.</p>
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
                            <label class="field"><span>Telefon *</span><input type="tel" name="telefon" value="<?= e(post_text('telefon', 30)) ?>" maxlength="30" autocomplete="tel" placeholder="+420 777 123 456" required></label>
                            <label class="field"><span>E-mail *</span><input type="email" name="email" value="<?= e(post_text('email', 150)) ?>" maxlength="150" autocomplete="email" placeholder="vas@email.cz" required></label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>O jakou pozici máte zájem?</legend>
                        <div class="position-grid">
                            <?php foreach ($pozice as $polozka): ?>
                                <?php $jeKuryr = $polozka['nazev'] === 'Kurýr'; ?>
                                <label class="position-card">
                                    <input type="checkbox" name="pozice[]" value="<?= $polozka['id'] ?>" <?= in_array($polozka['id'], $vybranePozice, true) ? 'checked' : '' ?>>
                                    <span class="position-content">
                                        <strong><?= e($polozka['nazev']) ?></strong>
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
                                    <input type="radio" name="mesto" value="<?= e($mestoVolba) ?>" <?= post_text('mesto', 20) === $mestoVolba ? 'checked' : '' ?> required>
                                    <span><?= e($mestoVolba) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Ještě pár informací</legend>
                        <div class="grid two">
                            <label class="field"><span>Nástup možný od:</span><input type="date" name="mozny_nastup" value="<?= e(post_text('mozny_nastup', 10)) ?>"></label>
                            <label class="field"><span>Představa o hodinové odměně</span><div class="input-suffix"><input type="number" name="ocekavana_mzda" value="<?= e(post_text('ocekavana_mzda', 20)) ?>" min="0" max="10000" step="1"><span>Kč/h</span></div></label>
                        </div>
                        <label class="field"><span>Napište nám něco o sobě</span><textarea name="povidani" rows="6" maxlength="5000" placeholder="Například jakou máte praxi, jaké směny vám vyhovují nebo proč vás práce u nás zaujala."><?= e(post_text('povidani', 5000)) ?></textarea><small>Nemusí to být formální. Stačí pár vět.</small></label>
                    </fieldset>

                    <label class="consent"><input type="checkbox" name="souhlas" value="1" <?= isset($_POST['souhlas']) ? 'checked' : '' ?> required><span>Souhlasím se zpracováním uvedených osobních údajů společností Pizza Comeback pro účely výběrového řízení. *</span></label>
                    <button class="button primary" type="submit">Odeslat dotazník <span>→</span></button>
                    <p class="privacy-note">Údaje použijeme pouze pro komunikaci a posouzení vašeho zájmu o práci.</p>
                </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <footer><strong>Pizza Comeback</strong><span>První krok k práci u nás</span></footer>
    </main>
</div>
</body>
</html>
