<?php
// Overuje ulozeni a nahrazeni verze; po uspechu vraci vyplnenou kontrolu s novym tokenem pro dalsi praci.
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/kontrola_trzeb_formular.php';
require_once __DIR__ . '/../db/db_kontrola_trzeb_archiv_zapis.php';

// Klient smi menit jen kontrolni castky a poznamky; automaticke hodnoty bere server ze snimku.
function cb_kt_radky(array $snapshot, array $post): array
{
    foreach (['spocitane', 'poznamka'] as $field) {
        if (isset($post[$field]) && !is_array($post[$field])) {
            throw new CbUserVisibleException('Neplatná struktura formuláře.');
        }
    }
    $right = $snapshot['automatic'];
    $rows = [];
    foreach (cb_kt_polozky() as $key => [$label, $manual]) {
        if ($manual) {
            $raw = $post['spocitane'][$key] ?? '';
            if (!is_scalar($raw)) { throw new CbUserVisibleException('Neplatná částka: ' . $label); }
            try { $right[$key] = cb_kt_halere((string)$raw); }
            catch (CbUserVisibleException $e) { throw new CbUserVisibleException($label . ': ' . $e->getMessage()); }
            if ($right[$key] === null) { throw new CbUserVisibleException('Vyplňte spočítanou částku: ' . $label . '. Pro shodnou částku použijte šipku z Odevzdané.'); }
        }
        $note = $post['poznamka'][$key] ?? '';
        if (!is_scalar($note) || mb_strlen((string)$note, 'UTF-8') > 4000) {
            throw new CbUserVisibleException('Poznámka smí mít nejvýše 4000 znaků: ' . $label);
        }
        $rows[$key] = ['left' => $snapshot['left'][$key], 'right' => $right[$key] ?? null, 'note' => trim((string)$note)];
    }
    return cb_kt_souhrn($rows);
}

// POST skonci presmerovanim; chyba zachova castky i puvodni serverove podklady.
function cb_kt_handle_post(): never
{
    if (($_POST['cb_action'] ?? '') === 'kt_platnost') {
        require_once __DIR__ . '/kontrola_trzeb_platnost.php';
        cb_kt_platnost_post();
    }
    $token = is_string($_POST['kt_token'] ?? null) ? $_POST['kt_token'] : '';
    $form = $_SESSION['kt_forms'][$token] ?? null;
    $snapshot = $form['snapshot'] ?? null;
    $fallback = cb_root_url('index.php?m=provoz&page=kontrola_trzeb');
    try {
        if (empty($_SESSION['login_ok']) || !cb_modul_ma_pristup('provoz') || !cb_kt_ma_pravo() || !cb_kt_ma_pravo(CB_KT_ULOZIT)) {
            throw new CbUserVisibleException('Nemáte právo uložit Kontrolu tržeb.');
        }
        if (!cb_crf_platny()) { throw new CbUserVisibleException('Neplatný bezpečnostní token. Obnovte stránku.'); }
        if (!is_array($snapshot) || ($form['time'] ?? 0) < time() - 43200) {
            throw new CbUserVisibleException('Formulář vypršel. Načtěte znovu podklady kontroly.');
        }
        cb_kt_vyzaduj_pobocku(db(), $snapshot['branch']);
        if (($_POST['cb_action'] ?? '') !== 'kt_ulozit') { throw new CbUserVisibleException('Neplatná akce.'); }
        if (!cb_kt_archiv_pripraven(db())) { throw new CbUserVisibleException('Archiv zatím není připraven k ukládání.'); }
        unset($_SESSION['kt_forms'][$token]['saved_id']);
        $rows = cb_kt_radky($snapshot, $_POST);
        $user = $_SESSION['cb_user'];
        $name = cb_osoba_jmeno($user);
        $offerKey = is_string($_POST['kt_offer'] ?? null) ? $_POST['kt_offer'] : '';
        $confirmed = ($_POST['kt_nahradit'] ?? '') === '1' && is_array($form['replacement_offer'] ?? null)
            && isset($form['replacement_offer_key']) && hash_equals($form['replacement_offer_key'], $offerKey) ? $form['replacement_offer'] : null;
        $id = cb_kt_archiv_uloz(db(), $snapshot, $rows, $token, (int)$user['id_user'], $name, $confirmed);
        // Zobrazime skutecne ulozene hodnoty i pri opakovanem odeslani puvodniho tokenu.
        $saved = cb_kt_archiv_detail(db(), $id, $snapshot['branch']);
        $input = ['spocitane'=>[], 'poznamka'=>[]];
        foreach ($saved['rows'] as $row) {
            $input['spocitane'][$row['polozka']] = $row['spocitane'];
            $input['poznamka'][$row['polozka']] = $row['poznamka'];
        }
        // Puvodni token zustava idempotentni; dalsi uprava musi dostat vlastni token.
        $nextToken = bin2hex(random_bytes(24));
        while (count($_SESSION['kt_forms']) >= 20) { array_shift($_SESSION['kt_forms']); }
        $_SESSION['kt_forms'][$nextToken] = ['time'=>time(), 'snapshot'=>$snapshot, 'input'=>$input, 'saved_id'=>$id];
        unset($_SESSION['kt_flash']);
        $fallback = cb_kt_url($snapshot, 'kontrola_trzeb', ['kt_resume' => $nextToken]);
    } catch (Throwable $e) {
        if ($e instanceof CbKtKonflikt && is_array($form)) {
            $_SESSION['kt_forms'][$token]['ask_replace'] = true;
        } else {
            $_SESSION['kt_flash'] = ['success' => false, 'message' => $e instanceof CbUserVisibleException
                ? $e->getMessage() : cb_chyba_uzivatel($e, ['module' => 'PROVOZ', 'action' => 'Uložení kontroly tržeb'])];
        }
        if (is_array($snapshot) && is_array($form)) {
            $input = [];
            foreach (['spocitane', 'poznamka'] as $field) {
                $input[$field] = is_array($_POST[$field] ?? null) ? array_filter($_POST[$field], 'is_scalar') : [];
            }
            $_SESSION['kt_forms'][$token]['input'] = $input;
            $fallback = cb_kt_url($snapshot, 'kontrola_trzeb', ['kt_resume' => $token]);
        }
    }
    header('Location: ' . $fallback, true, 303);
    exit;
}
