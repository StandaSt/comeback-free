<?php
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/../lib/osoba_jmeno.php';
/* Účel: Zobrazuje vlastní profil a formuláře pro přihlašovací heslo a e-mail IS.
 * Osobni a pracovni udaje se zobrazuji pouze pro cteni; MAIN se zobrazi jako cely vycet.
 */
require_once __DIR__ . '/../lib/profil.php';
$profil = cb_profil_data(db());
$profilUser = $_SESSION['cb_user'] ?? [];
$profilMessage = $_SESSION['cb_profil_zprava'] ?? null;
unset($_SESSION['cb_profil_zprava']);
$profilModule = defined('CB_EMBEDDED_MODULE') ? (string)constant('CB_EMBEDDED_MODULE') : (string)($GLOBALS['CURRENT_MODULE'] ?? 'provoz');
// Hlavní pobočku a povolená pracoviště zobrazujeme stejnými řádky jako ostatní údaje.
$profilMainNames = [];
foreach ($profil['pobocky'] as $branch) {
    if (in_array((int)$branch['id_pob'], $profil['main'], true)) {
        $profilMainNames[] = (string)$branch['nazev'];
    }
}
$profilFields = [
    'Jméno' => cb_osoba_jmeno($profilUser),
    'Přihlašovací e-mail IS' => $profil['email'],
    'Telefon' => (string)($profilUser['telefon'] ?? ''),
    'Role v IS' => implode(', ', array_column($profil['role'], 'nazev')),
    'Pracovní zařazení' => implode(', ', array_column($profil['sloty'], 'nazev')),
    'MAIN pobočky' => implode(', ', $profilMainNames),
    'Místo výkonu práce' => cb_hr_mista_popis(db(),(int)($profilUser['id_user']??0)),
];
// Jednotná dekorativní ikona; přístupný název a stav zůstávají na tlačítku.
$profilOko = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="common_profile_eye_slash" d="m3 3 18 18"/></svg>';
?>
<section class="pp common_profile_page" data-module="common" data-page="uprava_profilu">
    <header class="pp_header">
        <h1>Úprava profilu</h1>
    </header>
    <?php if (is_array($profilMessage)): ?>
        <p class="common_profile_notice<?= empty($profilMessage['ok']) ? ' is-error' : '' ?>" role="status"><?= h((string)$profilMessage['text']) ?></p>
    <?php endif; ?>
    <div class="common_profile_grid">
        <article class="blok common_profile_card">
            <h2 class="blok_title">Informace o uživateli</h2>
            <dl class="common_profile_details">
                <?php foreach ($profilFields as $label => $value): ?>
                    <div><dt><?= h($label) ?></dt><dd><?= h($value !== '' ? $value : '—') ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </article>
            <article class="blok common_profile_card">
                <h2 class="blok_title"><?= $profil['ma_heslo'] ? 'Změna hesla' : 'Vlastní heslo pro IS' ?></h2>
                <form method="post" action="<?= h(cb_root_url('index.php')) ?>" class="common_profile_form">
                    <input type="hidden" name="cb_action" value="<?= $profil['ma_heslo'] ? 'profil_heslo' : 'profil_nastavit_heslo' ?>">
                    <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
                    <input type="hidden" name="profil_modul" value="<?= h($profilModule) ?>">
                    <?php if ($profil['ma_heslo']): ?>
                        <div class="common_profile_field"><label for="profile-current-password">Současné heslo</label><div class="common_profile_password"><input id="profile-current-password" type="password" name="soucasne_heslo" autocomplete="current-password" required><button type="button" data-profile-password aria-label="Zobrazit současné heslo" aria-pressed="false" title="Zobrazit heslo"><?= $profilOko ?></button></div></div>
                        <div class="common_profile_field"><label for="profile-new-password">Nové heslo</label><div class="common_profile_password"><input id="profile-new-password" type="password" name="heslo" autocomplete="new-password" minlength="8" required aria-describedby="profile-password-rules"><button type="button" data-profile-password aria-label="Zobrazit nové heslo" aria-pressed="false" title="Zobrazit heslo"><?= $profilOko ?></button></div></div>
                        <div class="common_profile_field"><label for="profile-confirm-password">Nové heslo znovu</label><div class="common_profile_password"><input id="profile-confirm-password" type="password" name="heslo_znovu" autocomplete="new-password" minlength="8" required><button type="button" data-profile-password aria-label="Zobrazit nové heslo znovu" aria-pressed="false" title="Zobrazit heslo"><?= $profilOko ?></button></div></div>
                        <p class="common_profile_hint" id="profile-password-rules">Heslo musí mít alespoň 8 znaků, malé a velké písmeno a číslici.</p>
                        <p class="common_profile_hint">Po změně hesla budete odhlášen a budete se muset znova přihlásit.</p>
                        <button class="common_profile_submit common_profile_submit--right" type="submit">Změnit heslo</button>
                    <?php else: ?>
                        <p class="common_profile_hint">Zatím používáte přihlášení přes staré Směny. Na přihlašovací e-mail vám pošleme odkaz pro nastavení vlastního hesla IS. Heslo ve starých Směnách se nezmění.</p>
                        <button class="common_profile_submit" type="submit">Poslat odkaz na nastavení hesla</button>
                    <?php endif; ?>
                </form>
            </article>
            <article class="blok common_profile_card">
                <h2 class="blok_title">Změna přihlašovacího e-mailu</h2>
                <?php if (is_array($profil['ceka_email'])): ?>
                    <p class="common_profile_notice">Čeká na potvrzení: <strong><?= h((string)$profil['ceka_email']['novy_email']) ?></strong>. Nový požadavek nahradí předchozí odkaz.</p>
                <?php endif; ?>
                <?php if (!$profil['ma_heslo']): ?>
                    <p class="common_profile_hint">Nejprve si nastavte vlastní heslo pro IS.</p>
                <?php endif; ?>
                <form method="post" action="<?= h(cb_root_url('index.php')) ?>" class="common_profile_form">
                    <input type="hidden" name="cb_action" value="profil_email">
                    <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
                    <input type="hidden" name="profil_modul" value="<?= h($profilModule) ?>">
                    <fieldset<?= !$profil['email_pripraven'] || !$profil['ma_heslo'] ? ' disabled' : '' ?>>
                        <label>Nový přihlašovací e-mail<input type="email" name="email" autocomplete="email" maxlength="254" required></label>
                        <div class="common_profile_field"><label for="profile-email-password">Současné heslo</label><div class="common_profile_password"><input id="profile-email-password" type="password" name="soucasne_heslo" autocomplete="current-password" required><button type="button" data-profile-password aria-label="Zobrazit současné heslo" aria-pressed="false" title="Zobrazit heslo"><?= $profilOko ?></button></div></div>
                        <p class="common_profile_hint">Na zadaný-nový email bude odeslán potvrzovací odkaz a bude nutné změnu potvrdit.</p>
                        <button class="common_profile_submit common_profile_submit--right" type="submit">Odeslat potvrzení</button>
                    </fieldset>
                </form>
            </article>
    </div>
</section>
