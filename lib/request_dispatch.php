<?php
// lib/request_dispatch.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

$cbIsPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_PARTIAL'])) {
    $cbIsPartial = ((string)($_SERVER['HTTP_X_COMEBACK_PARTIAL']) === '1');
}

$cbIsCardPartial = false;
if (isset($_SERVER['HTTP_X_COMEBACK_CARD'])) {
    $cbIsCardPartial = ((string)($_SERVER['HTTP_X_COMEBACK_CARD']) === '1');
}

if ($cbIsCardPartial) {
    $cbCardId = (int)($_GET['cb_card_id'] ?? 0);
    cb_emit_card_json_response($cbCardId, ((int)($_GET['cb_load_max'] ?? 0) === 1), 'card_partial');
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_MAX_FORM'])
) {
    $cbCardId = (int)($_POST['cb_card_id'] ?? 0);
    cb_emit_card_json_response($cbCardId, true, 'max_form');
}

if ($cbIsPartial) {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo '<section class="card odstup_vnitrni_14"><p>Nutne prihlaseni.</p></section>';
        exit;
    }

    if ($cbPageExists) {
        require $file;
    } else {
        echo '<div class="page-head"><h2>Stranka nenalezena</h2></div>';
        echo '<section class="card odstup_vnitrni_14"><p>Pozadovana stranka neexistuje.</p></section>';
    }
    exit;
}
