<?php
/* E-mail: pracovni plocha a osobni nastaveni; manazerske vysvetleni zabezpeceni se na desktopu rozbaluje vedle formulare. */
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE!=='email' || empty($_SESSION['login_ok'])) { http_response_code(404); exit; }
require_once __DIR__.'/core.php';
if (!em_pravo(800)) { http_response_code(403); return; }
if (!defined('CB_PP_ONLY') || CB_PP_ONLY!==true) {
    require_once __DIR__.'/../common/lib/menu_modulu.php'; cb_menu_modulu_vykreslit('email');
}
if (($_GET['page']??'')==='uprava_profilu') { require __DIR__.'/../common/pages/uprava_profilu.php'; return; }
?>
<section class="pp em-client" data-module="email" data-page="prehled" data-email-root data-api="<?= h(cb_root_url('email/api.php')) ?>" data-editor-src="<?= h(cb_root_url('email/editor.js').'?v='.filemtime(__DIR__.'/editor.js')) ?>" aria-label="E-mail">
    <div class="em-notice" role="status" aria-live="polite" data-em-notice>Načítám e-mail…</div>
    <div class="em-list-panel">
        <div class="em-list-toolbar"><button type="button" data-em-action="refresh" title="Zkontrolovat nové zprávy">↻ Obnovit</button><button type="button" data-em-action="select">Vybrat vše</button><button type="button" data-em-action="bulk_move">Přesunout</button></div>
        <form class="em-search" data-em-search><input name="search" type="search" placeholder="Hledat v poště…" aria-label="Hledat v poště"><button type="submit" title="Hledat">⌕</button><label title="Pouze nepřečtené"><input type="checkbox" name="unseen"> Nepřečtené</label></form>
        <div class="em-message-list" data-em-list role="list" aria-label="Seznam zpráv"><div class="em-empty">Vyberte schránku.</div></div>
        <div class="em-pages"><button type="button" data-em-action="prev" aria-label="Předchozí stránka">‹</button><span data-em-page>—</span><button type="button" data-em-action="next" aria-label="Další stránka">›</button></div>
    </div>
    <div class="em-reader-panel">
        <div class="em-toolbar" data-em-toolbar>
            <button type="button" data-em-action="reply" disabled>↶ Odpovědět</button><button type="button" data-em-action="reply_all" disabled>↶ Všem</button><button type="button" data-em-action="forward" disabled>➜ Přeposlat</button><button type="button" data-em-action="trash" disabled>♲ Do koše</button><button type="button" data-em-action="unread" disabled>Nepřečtené</button><button type="button" data-em-action="flag" disabled>⚑ Označit</button>
        </div>
        <div class="em-reader" data-em-reader><div class="em-empty">Vyberte zprávu ze seznamu.</div></div>
    </div>
    <dialog class="em-dialog" data-em-connect>
        <form data-em-connect-form autocomplete="off">
            <div class="em-connect-heading"><h2>Připojení schránky</h2><button type="button" class="em-security-button" data-em-action="security" data-em-security-toggle aria-expanded="false" aria-controls="em-mail-security" hidden>Zabezpečení schránky</button></div>
            <section class="em-security-info" id="em-mail-security" data-em-security-info aria-label="Zabezpečení schránky" hidden>
                <p><strong>Stejná pošta v IS i ve webmailu.</strong> IS zobrazuje obsah vaší schránky z poštovního serveru. Zprávy a složky zůstávají na serveru; přečtení, přesuny i mazání se projeví také ve webmailu. Zprávy odesílané z IS se předávají poštovnímu serveru a jejich kopie se ukládá do Odeslaných.</p>
                <p>Je na vás, zda budete pracovat v IS, nebo ve webovém rozhraní na adrese <a href="https://tan09.vas-server.cz/mail/" target="_blank" rel="noopener noreferrer">tan09.vas-server.cz/mail</a>. Oba přístupy můžete střídat.</p>
                <p><strong>Jak chráníme heslo.</strong> Uložené heslo je šifrované a v IS se nikomu nezobrazuje, ani ve správě připojení. Šifrovací klíč je uložen odděleně od databáze. Aplikace heslo odemkne pro přihlášení k poštovnímu serveru; spojení s ním je šifrované a jeho certifikát se ověřuje.</p>
                <p><strong>Vždy zadávat heslo do e-mailu:</strong> heslo se trvale neukládá do databáze. Schránku odemknete při otevření pošty a odemčení platí do odhlášení ze schránky nebo do skončení přihlášení do IS.</p>
                <p class="em-security-recommended"><strong>Ověřovat při přihlášení do IS:</strong> IS po přihlášení automaticky použije vaše uložené šifrované připojení. Do e-mailové schránky se nemusíte samostatně přihlašovat ani znovu zadávat heslo po celou dobu vašeho platného přihlášení v IS. <strong>Doporučujeme.</strong></p>
                <p>Heslo chrání šifrování AES-256-GCM, které ověřuje také neporušenost uložené hodnoty. Šifrovací klíč je uložen odděleně od databáze a chráněn před přímým přístupem z webu. Uložené heslo se ve formulářích ani správě IS nezobrazuje. Komunikace IS s poštovním serverem probíhá přes šifrované spojení s ověřením certifikátu.</p>
            </section>
            <div class="em-connect-fields">
            <label>Schránka<select name="type"><option value="osobni">Moje osobní schránka</option><option value="shared">Společná schránka</option></select></label>
            <div data-em-shared-fields hidden>
                <div class="em-shared-summary"><strong>Připojené adresy společné pošty</strong><div class="em-shared-list" data-em-shared-list></div></div>
                <label>Připojení<select name="shared_id"><option value="0">Připojit novou schránku</option></select></label>
                <label>Určení schránky<select name="shared_kind"><option value="pobocka">Pobočková schránka</option><option value="info">INFO</option></select></label>
                <label data-em-branch-label>Pobočka<select name="branch"></select></label>
                <p data-em-shared-status></p><button type="button" data-em-action="shared_active" hidden></button>
            </div>
            <label>Firemní e-mail<span class="em-address-input"><input name="local_part" type="text" required maxlength="64" autocomplete="username" autocapitalize="none" spellcheck="false" aria-label="Část firemní adresy před zavináčem"><span>@pizzacomeback.cz</span></span></label>
            <label>Heslo<input name="password" type="password" required maxlength="1024" autocomplete="new-password"></label>
            <label data-em-mode-label>Způsob přístupu<select name="mode"><option value="is">Ověřovat při přihlášení do IS</option><option value="heslo">Vždy zadávat heslo do e-mailu</option></select></label>
            <p data-em-connect-hint>Heslo se zadává jednou za přihlášení do IS, nebo se použije zabezpečené uložené připojení podle zvoleného režimu.</p>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Připojit</button></div>
            </div>
        </form>
    </dialog>
    <dialog class="em-dialog" data-em-unlock>
        <form data-em-unlock-form><h2>Přihlášení do e-mailu</h2><p data-em-unlock-email></p><label data-em-password-label>Heslo<input name="password" type="password" autocomplete="current-password" maxlength="1024"></label><div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Přihlásit</button></div></form>
    </dialog>
    <dialog class="em-dialog em-compose" data-em-compose>
        <form data-em-compose-form><h2 data-em-compose-title>Nová zpráva</h2>
            <label>Od<select name="account" required></select></label><label>Komu<input name="to" required autocomplete="off"></label>
            <div class="em-address-pair"><label>Kopie<input name="cc"></label><label>Skrytá kopie<input name="bcc"></label></div>
            <label>Předmět<input name="subject" maxlength="998"></label><div class="em-body-label"><span>Zpráva</span><div data-em-compose-editor></div></div>
            <label>Přílohy (celkem nejvýše 20 MB)<input name="attachments" type="file" multiple></label><p data-em-forward-files hidden>Původní přílohy budou přeposlány se zprávou.</p>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="button" data-em-action="draft">Uložit koncept</button><button type="submit">Odeslat</button></div>
        </form>
    </dialog>
    <dialog class="em-dialog em-compose" data-em-signature>
        <form data-em-signature-form><h2>Můj podpis</h2><p>Podpis se vloží do každé nové zprávy, odpovědi i přeposlání. V konkrétní zprávě jej můžete upravit.</p><div data-em-signature-editor></div><div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Uložit podpis</button></div></form>
    </dialog>
    <dialog class="em-dialog" data-em-order>
        <form data-em-order-form><h2>Pořadí složek</h2><p>Složky přetáhněte nebo posouvejte šipkami. Pořadí je vaše osobní nastavení této schránky.</p><div class="em-order-list" data-em-order-list></div><div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-action="order_reset">Výchozí pořadí</button><button type="button" data-em-close>Zrušit</button><button type="submit">Hotovo</button></div></form>
    </dialog>
</section>
