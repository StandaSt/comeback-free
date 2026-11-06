<?php
/* Účel: Řídí oprávnění, validaci, číslování a společný vstup ručně psaných změn IS. */
declare(strict_types=1);
require_once __DIR__ . '/../db/db_verzovani.php';

/** Pevné typy mají stabilní ID; název nerozhoduje o ukládání. */
function cb_verze_typy(): array
{
    return [1 => 'Oprava', 2 => 'Doplnění', 3 => 'Vylepšeno'];
}

/** Každá verze včetně první obsahuje denní pořadí. */
function cb_verze_cislo(array $radek): string
{
    return str_replace('-', '.', (string)$radek['datum']) . '.' . (int)$radek['poradi'];
}

/** Stejná kontrola chrání stránku i zápis, nezávisle na viditelnosti menu. */
function cb_verze_sprava_povolena(): bool
{
    return !empty($_SESSION['login_ok']) && (int)($_SESSION['cb_user']['id_user'] ?? 0) > 0
        && function_exists('cb_pravo_ma') && cb_pravo_ma(100) && cb_pravo_ma(112);
}

/** Změna musí mít známý typ a neprázdný prostý text; HTML se při výpisu escapuje. */
function cb_verze_overit(array $post): array
{
    $typ = filter_var($post['typ'] ?? null, FILTER_VALIDATE_INT);
    $obsah = is_string($post['obsah'] ?? null) ? trim(str_replace(["\r\n", "\r"], "\n", $post['obsah'])) : '';
    if (!isset(cb_verze_typy()[$typ ?: 0])) throw new CbUserVisibleException('Vyberte typ změny.');
    if ($obsah === '' || !mb_check_encoding($obsah, 'UTF-8') || mb_strlen($obsah) > 4000) {
        throw new CbUserVisibleException('Napište text změny, nejvýše 4 000 znaků.');
    }
    return ['typ' => (int)$typ, 'obsah' => $obsah];
}

/** Oba přehledy čtou tentýž zdroj; chybějící tabulka nesmí rozbít celý IS. */
function cb_verze_nacist(mysqli $db, int $strana): array
{
    try { $radky = cb_db_verze_seznam($db, $strana); }
    catch (mysqli_sql_exception $error) {
        if ($error->getCode() === 1146) throw new CbUserVisibleException('Tabulka admin_verzovani zatím není připravena. Spusťte připravený SQL soubor.');
        throw $error;
    }
    return ['radky' => array_slice($radky, 0, 50), 'dalsi' => count($radky) > 50];
}

/** Jeden renderer zachovává shodný obsah v okně uživatele i ve správě. */
function cb_verze_prehled_html(array $radky): string
{
    ob_start();
    try { require __DIR__ . '/../includes/verze_prehled.php'; return (string)ob_get_contents(); }
    finally { ob_end_clean(); }
}

/** Při souběhu unikátní index odmítne stejné číslo; nová transakce načte další pořadí. */
function cb_verze_ulozit(mysqli $db, array $data, int $autor, string $kod): string
{
    $nyni = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
    $datum = $nyni->format('Y-m-d');
    for ($pokus = 0; $pokus < 10; $pokus++) {
        $db->begin_transaction();
        try {
            $existuje = cb_db_verze_pozadavek($db, $kod);
            if ($existuje !== null) { $db->commit(); return cb_verze_cislo($existuje); }
            $poradi = cb_db_verze_dalsi_poradi($db, $datum);
            cb_db_verze_vlozit($db, $datum, $poradi, $data['typ'], $data['obsah'], $autor, $nyni->format('Y-m-d H:i:s'), $kod);
            $db->commit();
            return cb_verze_cislo(['datum' => $datum, 'poradi' => $poradi]);
        } catch (Throwable $error) {
            $db->rollback();
            if ($error instanceof mysqli_sql_exception && in_array($error->getCode(), [1062, 1213], true)) continue;
            throw $error;
        }
    }
    throw new CbUserVisibleException('Právě se ukládá jiná změna. Zkuste uložení znovu.');
}

/** Vstup běží před HTML a používá ochranu session, zařízení a CRF společného indexu. */
function cb_verze_vstup(): void
{
    $akce = (string)($_GET['cb_verze'] ?? '');
    if ($akce === '') return;
    if ($akce === 'ulozit' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        if (!cb_verze_sprava_povolena()) { http_response_code(403); echo 'Nemáte právo spravovat změny v IS.'; exit; }
        cb_crf_vyzaduj();
        $draft = ['typ' => is_scalar($_POST['typ'] ?? null) ? $_POST['typ'] : '', 'obsah' => is_string($_POST['obsah'] ?? null) ? $_POST['obsah'] : ''];
        try {
            $data = cb_verze_overit($_POST);
            $kod = is_string($_POST['kod_pozadavku'] ?? null) ? $_POST['kod_pozadavku'] : '';
            if (preg_match('/^[a-f0-9]{64}$/D', $kod) !== 1
                || !isset($_SESSION['cb_verze_formulare'][$kod])) throw new CbUserVisibleException('Formulář vypršel. Zkontrolujte text a uložte jej znovu.');
            $cislo = cb_verze_ulozit(db(), $data, (int)$_SESSION['cb_user']['id_user'], $kod);
            $_SESSION['cb_verze_notice'] = ['ok' => true, 'text' => 'Uložena verze ' . $cislo . '.'];
        } catch (Throwable $error) {
            $_SESSION['cb_verze_notice'] = ['ok' => false, 'text' => $error instanceof CbUserVisibleException ? $error->getMessage() : 'Změnu se nepodařilo uložit. Text zůstal ve formuláři.', 'draft' => $draft];
        }
        header('Location: ' . cb_root_url('index.php?m=administrace&page=zmeny_is'), true, 303);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if (empty($_SESSION['login_ok'])) { http_response_code(401); echo json_encode(['ok' => false, 'err' => 'Přihlaste se do IS.']); exit; }
    try {
        if ($akce !== 'seznam' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') throw new CbUserVisibleException('Neplatný požadavek.');
        $strana = filter_var($_GET['strana'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        if ($strana === false) throw new CbUserVisibleException('Neplatná stránka.');
        $data = cb_verze_nacist(db(), $strana);
        echo json_encode(['ok' => true, 'html' => cb_verze_prehled_html($data['radky']), 'dalsi' => $data['dalsi']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'err' => 'Přehled změn zatím není k dispozici.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
