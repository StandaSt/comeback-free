<?php
// includes/login_form.php * Verze: V21 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * Přihlášení – obsah do <div class="hc-col"> v hlavicka.php
 * - zobrazuje stav přihlášení + flash hlášku
 * - odesílá na lib/login_smeny.php
 */

$cbUser  = $_SESSION['cb_user'] ?? null;
$cbFlash = (string)($_SESSION['cb_flash'] ?? '');
if ($cbFlash !== '') {
    unset($_SESSION['cb_flash']);
}

$loginOk = (bool)($_SESSION['login_ok'] ?? false);

function cb_fmt_dt_cz($v): string
{
    if ($v === null || $v === '') {
        return '---';
    }

    $ts = 0;
    if (is_int($v)) {
        $ts = $v;
    } else {
        $ts = (int)strtotime((string)$v);
    }

    if ($ts <= 0) {
        return '---';
    }
    return date('j.n.Y H:i', $ts);
}

if ($loginOk && is_array($cbUser) && !empty($cbUser['email'])) {

    $displayName = trim(
        (string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? '')
    );

    if ($displayName === '') {
        $displayName = (string)$cbUser['email'];
    }

    // Role a last login: bereme ze session, když tam není, použijeme výchozí hodnoty.
    $role = (string)($cbUser['role'] ?? 'admin');
    $lastLogin = cb_fmt_dt_cz($cbUser['last_login'] ?? '');

    ?>
    <div class="login-status">
        <div class="login-grid">
            <div class="login-fields">
                <div class="login-user">
                    Přihlášen: <strong><?= h($displayName) ?></strong>
                </div>

                <div class="login-meta">
                    <div class="login-role">Role: <?= h($role) ?></div>
                    <div class="login-role">Kdo ví co: něco sem dáme</div>
                    <div class="login-last">Last login: 7.2.2026 14:35</div>
                    <div class="login-last">Odhlášení za: 6 min.</div>
                </div>
            </div>

            <a class="login-logout cb-tip ikona-svg"
               href="<?= h(cb_url('lib/logout.php')) ?>"
               data-tip="Odhlásit"
               aria-label="Odhlásit">
                <img src="<?= h(cb_url('img/icons/exit.svg')) ?>" alt="">
            </a>
        </div>
    </div>
    <?php

} else {

    ?>
    <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>" class="login-form">
        <div class="login-grid">
            <div class="login-fields">
                <input type="email" name="email" placeholder="Email" required class="login-input">
                <input type="password" name="heslo" placeholder="Heslo" required class="login-input">
                <div class="login-help">Použij stejné přihlašovací údaje jako v Plánování směn</div>
            </div>

            <button type="submit" class="ikona-svg" aria-label="Přihlásit">
                <img src="<?= h(cb_url('img/icons/login.svg')) ?>" alt="">
            </button>
        </div>
    </form>
    <?php

}

/* Flash hlášku "Přihlášení OK" nezobrazujeme */
if ($cbFlash !== '' && $cbFlash !== 'Přihlášení OK') {
    ?>
    <div class="login-flash"><?= h($cbFlash) ?></div>
    <?php
}

/* includes/login_form.php * Verze: V21 * Aktualizace: 12.2.2026 * Počet řádků: 106 */
// Konec souboru