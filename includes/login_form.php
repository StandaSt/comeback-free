<?php
/* includes/login_form.php * Verze: V18 * 
   Aktualizace: 2.2.2026  */

declare(strict_types=1);

/*
 * Přihlášení – obsah do <div class="header-login"> v hlavicka.php
 * - zobrazuje stav přihlášení + flash hlášku
 * - odesílá na lib/login_smeny.php
 */

/* require_once __DIR__ . '/../lib/bootstrap.php'; */

$cbUser  = $_SESSION['cb_user'] ?? null;
$cbFlash = (string)($_SESSION['cb_flash'] ?? '');
if ($cbFlash !== '') {
    unset($_SESSION['cb_flash']);
}

function cb_fmt_dt_cz($v): string
{
    if ($v === null || $v === '') {
        return '---';
    }
    $ts = is_int($v) ? $v : strtotime((string)$v);
    if (!$ts) {
        return '---';
    }
    return date('j.n.Y H:i', $ts);
}

if (is_array($cbUser) && !empty($cbUser['email'])) {

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
        <div class="login-user">
            Přihlášen: <strong><?= h($displayName) ?></strong>
            <a class="login-logout cb-tip ikona-svg"
               href="<?= h(cb_url('lib/logout.php')) ?>"
               data-tip="Odhlásit">
                <img src="<?= h(cb_url('img/icons/exit.svg')) ?>" alt="Odhlásit">
            </a>
        </div>

        <div class="login-meta">
            <div class="login-role">Role: <?= h($role) ?></div>
            <div class="login-last">Last login: <?= h($lastLogin) ?></div>
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

/* includes/login_form.php * Verze: V18 * 
   Aktualizace: 2.2.2026 * Počet řádků: 91
   konec souboru */
