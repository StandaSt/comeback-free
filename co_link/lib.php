<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
/* Co-Link: DB dotazy, trvale odvolatelne prihlaseni telefonu a casove omezene prihlaseni PC z IS. */
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/config/secrets.php';

/** Ocekavana chyba obsahuje jen zpravu vhodnou uzivateli. */
function co_chyba(string $text, int $status = 400): never
{
    throw new DomainException($text, $status);
}

/** Parametrizovany dotaz; nazvy tabulek a SQL jsou vzdy pevne v kodu. */
function co_sql(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params !== []) {
        $types = implode('', array_map(static fn($v): string => is_int($v) ? 'i' : 's', $params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** Cte radky a uzavira statement i u castych synchronizaci chatu. */
function co_rows(string $sql, array $params = []): array
{
    $s = co_sql($sql, $params);
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

/** Transakce spojuje kontrolu a zmenu, zejmena pri soubehu cteni a editace. */
function co_transakce(callable $fn): mixed
{
    db()->begin_transaction();
    try { $result = $fn(); db()->commit(); return $result; }
    catch (Throwable $e) { db()->rollback(); throw $e; }
}

/** Chybejici instalace nesmi rozbit prihlaseni ani ostatni moduly IS. */
function co_pripraveno(): bool
{
    return co_rows("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='co_nastaveni'") !== [];
}

/** Identita i platnost firemniho uctu se overuje pri kazdem pozadavku. */
function co_osoba(int $id): array
{
    return co_rows('SELECT u.id_user,p.id_person,p.id_firma,
        COALESCE(' . cb_osoba_jmeno_sql('p.id_person') . ',u.email) jmeno
        FROM user u JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user AND p.aktivni=1 WHERE u.id_user=?', [$id])[0] ?? [];
}

/** Pouziva stejnou unii roli a prednost individualnich vyjimek jako IS, bez sdileni jeho session. */
function co_prava(int $id, bool $obnovit = false): array
{
    static $cache = [];
    if($obnovit) unset($cache[$id]);
    if (isset($cache[$id])) return $cache[$id];
    $roles = co_rows('SELECT 1 FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person=? LIMIT 1', [$id]);
    $rows = co_rows('SELECT p.id_pravo,p.aktivni,
        EXISTS(SELECT 1 FROM hr_pristupovy_profil r JOIN prava_global g ON g.id_role=r.id_role WHERE r.id_person=? AND g.id_pravo=p.id_pravo) globalni,
        (SELECT v.povoleno FROM prava_vyjimky v WHERE v.id_user=? AND v.id_pravo=p.id_pravo) vyjimka
        FROM cis_prava p WHERE p.id_pravo BETWEEN 700 AND 703', [$id,$id]);
    $rights = [];
    foreach ($rows as $r) $rights[(int)$r['id_pravo']] = (int)$r['aktivni'] === 0 || ($roles !== [] && (int)($r['vyjimka'] ?? $r['globalni']) === 1);
    foreach ([701,702,703] as $r) $rights[$r] = !empty($rights[700]) && !empty($rights[$r]);
    return $cache[$id] = $rights;
}

/** Pravo spravy nikdy samo neobchazi clenstvi v soukrome konverzaci. */
function co_pravo(int $user, int $right): bool { return !empty(co_prava($user)[$right]); }

/** Cookie zustava omezena na Co-Link; prihlaseni do ostatnich agend tim nevznika. */
function co_cookie_jmeno(): string { return co_https() ? '__Secure-co_link' : 'co_link'; }
/** Sdili overeni HTTPS s nastavenim cookie za reverzni proxy. */
function co_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }
/** Jednotny rozsah cookie a odkazu plati i pri provozu IS v podadresari. */
function co_cesta(): string { return rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/co_link/index.php'))),'/') . '/'; }
/** Telefon ma trvalou cookie, PC pouze cookie do konce relace prohlizece. */
function co_cookie(string $token, int $expiry): void
{
    setcookie(co_cookie_jmeno(), $token, ['expires'=>$expiry,'path'=>co_cesta(),'secure'=>co_https(),'httponly'=>true,'samesite'=>'Strict']);
}

/** Telefon nema casovy limit; PC nadale vyzaduje platnou relaci IS a vlastni platnost. */
function co_identita(): array
{
    $token = (string)($_COOKIE[co_cookie_jmeno()] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return [];
    $d = co_rows('SELECT d.id,d.uzivatel,d.nazev,d.pc_hash FROM co_zarizeni d WHERE d.token_hash=? AND d.zruseno IS NULL AND (d.pc_hash IS NULL OR d.platnost_do>NOW())
        AND (d.pc_hash IS NULL OR EXISTS(SELECT 1 FROM user_pc_session p WHERE HEX(p.pc_hash)=UPPER(d.pc_hash) AND p.id_user=d.uzivatel AND p.zruseno IS NULL))', [hash('sha256',$token)])[0] ?? [];
    if (!$d || !($p=co_osoba((int)$d['uzivatel'])) || !co_pravo((int)$d['uzivatel'],700)) return [];
    // Prohlizec potrebuje konec platnosti cookie; pri pouzivani telefonu ji prubezne posouvame bez noveho QR.
    if($d['pc_hash']===null) co_cookie($token,time()+365*86400);
    return $p + ['zarizeni'=>(int)$d['id'],'csrf'=>hash_hmac('sha256','co-link-csrf',$token)];
}

/** Zapisove akce potrebuji vlastni CSRF token; QR vymena pouziva tajny jednorazovy token. */
function co_csrf(array $identity): void
{
    $v = (string)($_SERVER['HTTP_X_CO_CSRF'] ?? '');
    if ($v === '' || !hash_equals((string)$identity['csrf'], $v)) co_chyba('Neplatné ověření požadavku. Obnovte Co-Link.',403);
}

/** Povoli zapis pouze ze stejneho webu, vcetne QR vymeny bez existujici session. */
function co_puvod(): void
{
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $expected = (co_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== $expected) co_chyba('Požadavek nepochází z Co-Linku.',403);
}

/** Samostatny nahodny token ma v DB pouze hash; ztracene zarizeni lze odvolat. */
function co_pripoj(int $user, string $name, ?string $pcHash = null): array
{
    if (!co_osoba($user) || !co_pravo($user,700)) co_chyba('Nemáte povolen vstup do Co-Linku.',403);
    $token = bin2hex(random_bytes(32));
    // Existujici NOT NULL sloupec zachovavame bez migrace; u telefonu se datum nepouziva pri overovani.
    $expiry=$pcHash===null?'9999-12-31 23:59:59':date('Y-m-d H:i:s',time()+12*3600);
    co_sql('INSERT INTO co_zarizeni(uzivatel,token_hash,pc_hash,nazev,platnost_do) VALUES(?,?,?,?,?)',[$user,hash('sha256',$token),$pcHash,mb_substr(trim($name) ?: 'Moje zařízení',0,100),$expiry])->close();
    $device = (int)db()->insert_id;
    co_cookie($token,$pcHash===null?time()+365*86400:0);
    return ['zarizeni'=>$device,'csrf'=>hash_hmac('sha256','co-link-csrf',$token)];
}

/** Souborove uloziste lezi mimo verejny web, nikdy nepouziva uzivatelsky nazev jako cestu. */
function co_uloziste(): string { return dirname(__DIR__,2) . '/data/co_link/prilohy'; }

/** Pro audit se uklada akce a objekt, nikoli text soukrome komunikace nebo prihlasovaci tajemstvi. */
function co_audit(int $user, string $action, ?int $object = null): void
{
    co_sql('INSERT INTO co_audit(uzivatel,akce,objekt) VALUES(?,?,?)',[$user,$action,$object])->close();
}
