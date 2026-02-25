<?php
// includes/login_form.php * Verze: V26 * Aktualizace: 24.2.2026

/*
 * Přihlášení – obsah do <div class="hc-col"> v hlavicka.php
 *
 * Cíl:
 * - když je uživatel přihlášen:
 *   - zobrazit 5 řádků informací (diagnostika + čas seance)
 *   - čas seance se po vykreslení udržuje „živě“ v prohlížeči (JS), bez překreslení hlavičky
 * - když přihlášen není:
 *   - zobrazit login form
 *
 * Důležité:
 * - tenhle soubor NEMÁ dělat DB dotazy
 * - všechno čte ze session, kterou naplní login_smeny.php + db/db_user_login.php
 */

declare(strict_types=1);

$cbUser = $_SESSION['cb_user'] ?? [];
$loginOk = (bool)($_SESSION['login_ok'] ?? false);

/**
 * Formát datumu/času pro CZ (jen pro zobrazení).
 */
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

/**
 * Bezpečné čtení integeru ze session.
 */
function cb_sess_int(string $k, int $default = 0): int
{
    $v = $_SESSION[$k] ?? null;
    if (is_int($v)) {
        return $v;
    }
    if (is_string($v) && $v !== '' && ctype_digit($v)) {
        return (int)$v;
    }
    return $default;
}

if ($loginOk) {

    $displayName = trim(
        (string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? '')
    );

    if ($displayName === '') {
        $displayName = (string)($cbUser['email'] ?? '');
    }

    if ($displayName === '') {
        $displayName = '---';
    }

    // Efektivní role pro UI (název role) je uložená do session při loginu (db/db_user_login.php).
    $roleName = (string)($cbUser['role'] ?? '');
    if ($roleName === '') {
        $roleName = '---';
    }

    // Login info (čas/IP, statistiky) – připravené v session při loginu.
    $info = $_SESSION['cb_login_info'] ?? null;
    if (!is_array($info)) {
        $info = [];
    }

    $prev = $info['prev'] ?? null;
    $prevKdy = is_array($prev) ? cb_fmt_dt_cz($prev['kdy'] ?? '') : '---';
    $prevIp  = is_array($prev) ? (string)($prev['ip'] ?? '---') : '---';

    $cur = $info['current'] ?? null;
    $curIp = is_array($cur) ? (string)($cur['ip'] ?? '---') : '---';

    $stats = $info['stats'] ?? null;
    $total = is_array($stats) ? (int)($stats['total'] ?? 0) : 0;
    $today = is_array($stats) ? (int)($stats['today'] ?? 0) : 0;

    /*
     * Timeout (neaktivita) – bez fallbacku.
     * - hodnota se nastavuje na JEDINÉM místě: lib/login_smeny.php
     * - tady ji jen čteme ze session
     */
    $timeoutMin = cb_sess_int('cb_timeout_min', 0);

    /*
     * Časy pro odpočet (sekundy)
     * - cb_session_start_ts: začátek seance
     * - cb_last_activity_ts: poslední aktivita
     */
    $lastTs = cb_sess_int('cb_last_activity_ts', 0);
    $startTs = cb_sess_int('cb_session_start_ts', 0);

    // Startovní (PHP) výpočet – jen pro první vykreslení.
    $runTxt = '!';
    $remainTxt = '!';

    if ($timeoutMin > 0 && $startTs > 0 && $lastTs > 0) {
        $runMin = (int)floor((time() - $startTs) / 60);
        if ($runMin < 0) {
            $runMin = 0;
        }

        $idleMin = (int)floor((time() - $lastTs) / 60);
        if ($idleMin < 0) {
            $idleMin = 0;
        }

        $remain = $timeoutMin - $idleMin;
        if ($remain < 0) {
            $remain = 0;
        }

        $runTxt = (string)$runMin;
        $remainTxt = (string)$remain;
    }

    $logoutUrl = cb_url('lib/logout.php');

    ?>
    <div class="login-status">
        <div class="login-grid"
             data-start-ts="<?= h((string)$startTs) ?>"
             data-last-ts="<?= h((string)$lastTs) ?>"
             <?php if ($timeoutMin > 0) { ?>data-timeout-min="<?= h((string)$timeoutMin) ?>"<?php } ?>
             data-logout-url="<?= h($logoutUrl) ?>">

            <div class="login-fields">

                <!-- 1) Identita + role -->
                <div class="login-user">
                    Přihlášen: <strong><?= h($displayName) ?></strong> (<?= h($roleName) ?>)
                </div>

                <div class="login-meta">

                    <!-- 2) Poslední přístup (předchozí login) -->
                    <div class="login-last">Poslední přístup: <?= h($prevKdy) ?> / <?= h($prevIp) ?></div>

                    <!-- 3) Aktuální seance (živě se dopočítává v JS) -->
                    <div class="login-last">
                        Čas seance: <span class="cb-run-min"><?= h($runTxt) ?></span> min /
                        zbývá <span class="cb-remain-min"><?= h($remainTxt) ?></span> min /
                        <?= h($curIp) ?>
                    </div>

                    <!-- 4) Statistiky loginů -->
                    <div class="login-last">Přihlášení celkem <?= h((string)$total) ?>× dnes <?= h((string)$today) ?>×</div>

                    <!-- 5) Rezerva -->
                    <div class="login-last">Rezerva</div>

                </div>
            </div>

            <a class="login-logout cb-tip ikona-svg"
               href="<?= h($logoutUrl) ?>"
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



/* includes/login_form.php * Verze: V26 * Aktualizace: 24.2.2026 * Počet řádků: 208 */
// Konec souboru