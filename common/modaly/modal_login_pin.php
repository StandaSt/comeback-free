<?php
declare(strict_types=1);
/* Samostatne overeni PINem nebo push a povinna prvni zmena bez pristupu do obsahu IS. */
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Přihlašovací PIN – Comeback</title>
    <link rel="stylesheet" href="<?= h(cb_public_url('style/login_pin.css') . '?v=' . filemtime(__DIR__ . '/../style/login_pin.css')) ?>">
    <script src="<?= h(cb_public_url('js/login_pin.js') . '?v=' . filemtime(__DIR__ . '/../js/login_pin.js')) ?>" defer></script>
</head>
<body class="cb-pin-page">
<main class="cb-pin-card"<?= $pinPush ? ' data-pin-push-url="' . h(cb_public_url('lib/push_2fa_api.php') . '?check=1') . '"' : '' ?>>
    <img class="cb-pin-logo" src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
    <h1><?= $pinPrvniZmena ? 'Nastavte si vlastní PIN' : 'Přihlašovací PIN' ?></h1>
    <p><?= $pinPrvniZmena ? 'Před prvním vstupem změňte počáteční PIN na vlastní čtyřmístný PIN.' : 'Zadejte svůj čtyřmístný PIN.' ?></p>
    <?php if ($pinPush): ?><p class="cb-pin-push-status" role="status">Přihlášení můžete také potvrdit v notifikaci na telefonu.</p><?php endif; ?>
    <?php if ($pinMessage !== ''): ?><p class="cb-pin-error" role="alert"><?= h($pinMessage) ?></p><?php endif; ?>
    <?php if ($pinPrvniZmena): ?>
    <form method="post" action="<?= h(cb_root_url('index.php')) ?>" data-pin-change>
        <input type="hidden" name="cb_action" value="login_pin_novy">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <label class="cb-pin-label" for="cb-new-pin">Nový PIN</label>
        <input id="cb-new-pin" class="cb-pin-input" type="password" name="novy_pin" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" required autofocus>
        <label class="cb-pin-label cb-pin-label-next" for="cb-new-pin-confirm">Nový PIN znovu</label>
        <input id="cb-new-pin-confirm" class="cb-pin-input" type="password" name="novy_pin_znovu" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" required>
        <button class="cb-pin-submit cb-pin-save" type="submit">Uložit PIN a vstoupit do IS</button>
    </form>
    <?php else: ?>
    <form method="post" action="<?= h(cb_root_url('index.php')) ?>" data-pin-form>
        <input type="hidden" name="cb_action" value="login_pin">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <label class="cb-pin-label" for="cb-login-pin">PIN</label>
        <input id="cb-login-pin" class="cb-pin-input" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" autocomplete="off" required autofocus>
        <div class="cb-pin-keys" role="group" aria-label="Číselná klávesnice">
            <?php foreach (['1','2','3','4','5','6','7','8','9'] as $digit): ?>
                <button type="button" data-pin-digit="<?= $digit ?>"><?= $digit ?></button>
            <?php endforeach; ?>
            <button type="button" data-pin-clear aria-label="Vymazat PIN">C</button>
            <button type="button" data-pin-digit="0">0</button>
            <button type="button" data-pin-back aria-label="Smazat poslední číslici">⌫</button>
        </div>
        <button class="cb-pin-submit" type="submit">Potvrdit</button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="cb_action" value="login_pin_zrusit">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <button class="cb-pin-cancel" type="submit">Zpět k přihlášení</button>
    </form>
</main>
</body>
</html>
