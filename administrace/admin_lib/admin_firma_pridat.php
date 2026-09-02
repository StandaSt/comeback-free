<?php
declare(strict_types=1);

/* Řídí dva kroky přidání firmy: načtení z ARES a potvrzené uložení do DB. */

function cb_admin_firma_csrf_token(): string
{
    $token = $_SESSION['cb_admin_firma_csrf'] ?? null;
    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['cb_admin_firma_csrf'] = $token;
    }
    return $token;
}

function cb_admin_firma_pravo_vyzaduj(): void
{
    if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(105)) {
        throw new RuntimeException('Nemáte právo přidat firmu.');
    }
}

function cb_admin_firma_flash(string $typ, string $text, string $ico = ''): void
{
    $_SESSION['cb_admin_firma_flash'] = [
        'typ' => $typ,
        'text' => $text,
        'ico' => $ico,
    ];
}

function cb_admin_firma_redirect(): never
{
    header('Location: ' . cb_root_url('index.php?m=administrace&page=firma_pridat'), true, 303);
    exit;
}

function cb_admin_firma_pridat_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $action = (string)($_POST['cb_action'] ?? '');
    if (!in_array($action, ['admin_firma_ares_nacist', 'admin_firma_ulozit'], true)) {
        return;
    }

    $ico = trim((string)($_POST['ico'] ?? ''));
    try {
        cb_admin_firma_pravo_vyzaduj();
        $token = (string)($_POST['csrf_token'] ?? '');
        if ($token === '' || !hash_equals(cb_admin_firma_csrf_token(), $token)) {
            throw new RuntimeException('Platnost formuláře vypršela. Obnovte stránku a zkuste to znovu.');
        }

        if ($action === 'admin_firma_ares_nacist') {
            $ico = cb_admin_firma_ico_normalizuj($ico);
            if (cb_admin_firma_ico_existuje(db(), $ico)) {
                throw new RuntimeException('Firma se zadaným IČO již v systému existuje.');
            }
            $data = cb_admin_firma_ares_nacti($ico);
            $_SESSION['cb_admin_firma_ares'] = [
                'nonce' => bin2hex(random_bytes(32)),
                'data' => $data,
            ];
            cb_admin_firma_flash('ok', 'Údaje byly načteny z ARES.', $ico);
            cb_admin_firma_redirect();
        }

        $stav = $_SESSION['cb_admin_firma_ares'] ?? null;
        $nonce = (string)($_POST['ares_nonce'] ?? '');
        if (!is_array($stav) || !is_array($stav['data'] ?? null) || $nonce === '' || !hash_equals((string)($stav['nonce'] ?? ''), $nonce)) {
            throw new RuntimeException('Načtené údaje ARES již nejsou platné. Načtěte firmu znovu.');
        }

        $hlavniJednatel = filter_var($_POST['hlavni_jednatel'] ?? null, FILTER_VALIDATE_INT);
        if ($hlavniJednatel === false) {
            throw new RuntimeException('Vyberte hlavního jednatele firmy.');
        }
        $user = $_SESSION['cb_user'] ?? [];
        $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
        $idFirma = cb_admin_firma_uloz(db(), $stav['data'], (int)$hlavniJednatel, $idUser);

        unset($_SESSION['cb_admin_firma_ares']);
        cb_admin_firma_flash('ok', 'Firma byla přidána do IS s ID ' . $idFirma . '.');
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'firma',
            'id_objektu' => $idFirma,
            'pole' => 'vytvoreni',
            'hodnota_new' => (string)($stav['data']['ico'] ?? ''),
            'vysledek' => 1,
            'zdroj' => 'administrace',
        ]);
        cb_admin_firma_redirect();
    } catch (Throwable $e) {
        cb_admin_firma_flash('chyba', $e->getMessage(), $ico);
        cb_admin_firma_redirect();
    }
}
