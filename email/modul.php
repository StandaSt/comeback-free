<?php
/* E-mail: MAIN vedoucich a volba treninku ve sdilenych schrankach; kompaktni plocha, osobni a sdilena pripojeni a spravni prehled kdo ma pristup s vysvetlenim. */
declare(strict_types=1);
if (!defined('CB_EMBEDDED_MODULE') || CB_EMBEDDED_MODULE!=='email' || empty($_SESSION['login_ok'])) { http_response_code(404); exit; }
require_once __DIR__.'/core.php';
if (!em_pravo(800)) { http_response_code(403); return; }
if (!defined('CB_PP_ONLY') || CB_PP_ONLY!==true) {
    require_once __DIR__.'/../common/lib/menu_modulu.php'; cb_menu_modulu_vykreslit('email');
}
if (($_GET['page']??'')==='uprava_profilu') { require __DIR__.'/../common/pages/uprava_profilu.php'; return; }
?>
<section class="pp em-client" data-module="email" data-page="prehled" data-email-root data-api="<?= h(cb_root_url('email/email_pozadavky.php')) ?>" data-editor-src="<?= h(cb_root_url('email/editor.js').'?v='.filemtime(__DIR__.'/editor.js')) ?>" aria-label="E-mail">
    <div class="em-notice" role="status" aria-live="polite" data-em-notice hidden></div>
    <div class="em-list-panel">
        <div class="em-list-toolbar"><button type="button" data-em-action="refresh" title="Zkontrolovat nové zprávy">↻ Obnovit</button><button type="button" data-em-action="select">Vybrat vše</button><button type="button" data-em-action="bulk_move">Přesunout</button><button type="button" data-em-action="bulk_trash">Do koše</button></div>
        <form class="em-search" data-em-search><div class="em-search-query"><button type="button" class="em-search-clear" data-em-action="clear_search" data-em-search-clear aria-label="Vymazat hledání a obnovit seznam" title="Vymazat hledání a obnovit seznam" hidden>×</button><input name="search" type="search" maxlength="200" placeholder="Hledat v poště…" aria-label="Hledat v poště"><button type="submit" title="Hledat v předmětu a adresách; potvrďte Enterem nebo lupou" aria-label="Hledat"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button><label title="Pouze nepřečtené"><input type="checkbox" name="unseen"> Nepřečtené</label></div><div class="em-search-options"><label class="em-search-body" title="Pomalejší hledání také v textu zpráv; potvrďte Enterem nebo lupou"><input type="checkbox" name="body"> Hledat také v obsahu</label><label title="Při změně schránky zachovat potvrzené hledání"><input type="checkbox" name="remember"> Pamatuj filtr</label></div></form>
        <div class="em-search-active" data-em-search-active hidden><span data-em-search-term></span><button type="button" data-em-action="clear_search">Zrušit hledání</button></div>
        <div class="em-message-list" data-em-list role="list" aria-label="Seznam zpráv"><div class="em-empty">Vyberte schránku.</div></div>
        <div class="em-pages"><button type="button" data-em-action="prev" aria-label="Předchozí stránka" title="Předchozí stránka">‹</button><span data-em-page aria-live="polite">—</span><button type="button" data-em-action="next" aria-label="Další stránka" title="Další stránka">›</button><label>Počet <select data-em-page-size aria-label="Počet zpráv na stránku"><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option></select></label></div>
    </div>
    <div class="em-reader-panel">
        <div class="em-toolbar" data-em-toolbar>
            <button type="button" data-em-action="reply" disabled>↶ Odpovědět</button><button type="button" data-em-action="reply_all" disabled>↶ Všem</button><button type="button" data-em-action="forward" disabled>➜ Přeposlat</button><button type="button" data-em-action="trash" disabled>♲ Do koše</button><button type="button" data-em-action="unread" disabled>Nepřečtené</button><button type="button" data-em-action="flag" disabled>⚑ Označit</button>
        </div>
        <div class="em-reader" data-em-reader><div class="em-empty">Vyberte zprávu ze seznamu.</div></div>
    </div>
    <dialog class="em-dialog em-attachment-dialog" data-em-attachment-dialog>
        <form><h2 data-em-attachment-title>Příloha</h2><div class="em-attachment-preview" data-em-attachment-preview></div>
            <div class="em-dialog-actions"><a data-em-attachment-office hidden>Otevřít v aplikaci</a><a data-em-attachment-save hidden>Uložit přílohu</a><button type="button" data-em-close>Zavřít</button></div>
        </form>
    </dialog>
    <dialog class="em-dialog" data-em-move>
        <form data-em-move-form><h2 data-em-move-title>Přesunout zprávy</h2><p data-em-move-summary></p>
            <label data-em-move-target-label>Cílová složka<select name="target" required></select></label>
            <p data-em-move-trash-hint hidden>Zprávy se přesunou do koše své schránky. Z koše je můžete přesunout zpět.</p>
            <label class="em-inline-check" data-em-delete-preference hidden><input type="checkbox" name="skip_confirm"> Vždy mazat ihned, bez potvrzování</label>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Přesunout</button></div>
        </form>
    </dialog>
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
            <label>Schránka<select name="type"><option value="osobni">Osobní schránka</option><option value="shared">Sdílená schránka</option></select></label>
            <label data-em-personal-owner hidden>Uživatel<select name="target_user" aria-label="Uživatel osobní schránky"></select></label>
            <label data-em-personal-account hidden>Připojení<select name="personal_id" aria-label="Osobní připojení"><option value="0">Připojit další schránku</option></select></label>
            <div data-em-shared-fields hidden>
                <div class="em-shared-summary"><strong>Připojené adresy společné pošty</strong><div class="em-shared-list" data-em-shared-list></div></div>
                <label>Připojení<select name="shared_id"><option value="0">Připojit novou schránku</option></select></label>
                <label>Určení schránky<select name="shared_kind"><option value="pobocka">Pobočková schránka</option><option value="info">Sdílená schránka</option></select></label>
                <label data-em-branch-label>Pobočka<select name="branch"></select></label>
                <p data-em-shared-status></p><button type="button" data-em-action="shared_active" hidden></button>
            </div>
            <label>Firemní e-mail<span class="em-address-input"><input name="local_part" type="text" required maxlength="64" autocomplete="username" autocapitalize="none" spellcheck="false" aria-label="Část firemní adresy před zavináčem"><span>@pizzacomeback.cz</span></span></label>
            <label>Heslo<input name="password" type="password" required maxlength="1024" autocomplete="new-password"></label>
            <label data-em-mode-label>Přístup uživatele<select name="mode"><option value="is">Ověřovat při přihlášení do IS</option><option value="heslo">Vždy zadávat heslo do e-mailu</option></select></label>
            <div data-em-personal-passwords hidden></div>
            <p data-em-connect-hint>Heslo se zadává jednou za přihlášení do IS, nebo se použije zabezpečené uložené připojení podle zvoleného režimu.</p>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" class="em-disconnect-button" data-em-action="disconnect" hidden>Odpojit osobní schránku z IS</button><button type="button" data-em-close>Zrušit</button><button type="submit">Připojit</button></div>
            </div>
        </form>
    </dialog>
    <!-- Samostatna sprava neobsahuje pripojovani; meni pristup, ulozene heslo nebo odebere pripojeni z IS. -->
    <dialog class="em-dialog em-shared-admin" data-em-shared-admin aria-labelledby="em-shared-admin-title">
        <form data-em-shared-admin-form autocomplete="off">
            <h2 id="em-shared-admin-title">Správa sdílených schránek</h2>
            <label class="em-admin-field">Schránka<select name="managed_account" aria-label="Spravovaná sdílená schránka"></select></label>
            <p data-em-admin-empty hidden>Žádná sdílená schránka není připojená. Připojení zůstává v „Připojení a ověřování“.</p>
            <div data-em-admin-content>
                <h3>Přístup ke schránce</h3><p data-em-admin-access-hint></p>
                <fieldset data-em-admin-access>
                    <div class="em-admin-members">
                        <div><strong>Role z IS</strong><div class="em-admin-checks" data-em-admin-roles></div></div>
                        <div><strong>Jednotliví uživatelé</strong><input type="search" data-em-admin-filter aria-label="Hledat uživatele" placeholder="Hledat uživatele…"><div class="em-admin-checks" data-em-admin-users></div></div>
                    </div>
                    <label class="em-admin-main" data-em-admin-main><input type="checkbox" name="access_training"> Zahrnout i osoby v tréninku</label>
                    <p class="em-admin-note">U pobočkové schránky platí vedoucí role jen pro její MAIN. Ostatní role a jednotliví uživatelé mají přístup podle výběru.</p>
                </fieldset>
                <div class="em-admin-access-actions"><button type="button" data-em-action="shared_access_report">Kdo má přístup?</button><button type="button" class="em-admin-save" data-em-action="shared_save_access">Uložit přístup</button></div>
                <!-- Uprava hesla zustava viditelna; ovladani tvori jeden kompaktni radek podle sirky displeje. -->
                <section class="em-admin-password" aria-labelledby="em-admin-password-title">
                    <h3 id="em-admin-password-title">Upravit schránku</h3>
                    <div class="em-admin-password-row">
                        <label for="em-admin-password">Nové heslo:</label>
                        <input id="em-admin-password" type="password" name="managed_password" maxlength="1024" autocomplete="new-password">
                        <button type="button" data-em-action="shared_update_password">Ověřit a uložit</button>
                    </div>
                    <p>Nejprve změňte heslo na poštovním serveru. Zde se ověří a uloží nové připojení v IS.</p>
                </section>
                <!-- Prazdny radek zachovava odstup pred spodnimi akcemi. -->
                <p class="em-admin-note" aria-hidden="true">&nbsp;</p>
            </div>
            <div class="em-form-error" role="alert"></div><div class="em-admin-feedback" role="status" data-em-admin-feedback></div>
            <div class="em-dialog-actions"><button type="button" class="em-admin-remove" data-em-action="shared_delete">Odebrat z IS</button><button type="button" data-em-close>Zavřít</button></div>
        </form>
    </dialog>
    <!-- Cteci prehled vychazi z ulozeneho stavu; neulozene zmeny spravniho formulare zustavaji zachovane. -->
    <dialog class="em-dialog em-access-report" data-em-access-report aria-labelledby="em-access-report-title">
        <h2 id="em-access-report-title">Kdo má přístup?</h2>
        <p class="em-access-account" data-em-access-account></p><p data-em-access-summary role="status"></p>
        <div class="em-form-error" role="alert"></div>
        <div class="em-access-table" data-em-access-results hidden><table><thead><tr><th>Uživatel</th><th>Proč má přístup</th></tr></thead><tbody data-em-access-users></tbody></table></div>
        <p data-em-access-unresolved hidden></p>
        <div class="em-dialog-actions"><button type="button" data-em-close>Zavřít</button></div>
    </dialog>
    <!-- Pri vice osobnich schrankach vlastnika vybira uzivatel konkretni adresu pred potvrzenim. -->
    <dialog class="em-dialog" data-em-disconnect aria-labelledby="em-disconnect-question">
        <h2 id="em-disconnect-question" data-em-disconnect-question></h2>
        <label data-em-disconnect-choice hidden>Schránka<select data-em-disconnect-account aria-label="Schránka k odpojení"></select></label>
        <div class="em-form-error" role="alert"></div>
        <div class="em-dialog-actions"><button type="button" class="em-disconnect-button" data-em-action="disconnect_confirm">Odpojit</button><button type="button" data-em-close>Zachovat</button></div>
    </dialog>
    <dialog class="em-dialog" data-em-unlock>
        <form data-em-unlock-form><h2>Přihlášení do e-mailu</h2><p data-em-unlock-email></p><label data-em-password-label>Heslo<input name="password" type="password" autocomplete="current-password" maxlength="1024"></label><div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Přihlásit</button></div></form>
    </dialog>
    <dialog class="em-dialog em-compose" data-em-compose>
        <form data-em-compose-form>
            <div class="em-compose-heading"><h2 data-em-compose-title>Nová zpráva</h2><div class="em-copy-toggles"><button type="button" data-em-copy-toggle="cc" aria-expanded="false" aria-controls="em-copy-cc">Kopie</button><button type="button" data-em-copy-toggle="bcc" aria-expanded="false" aria-controls="em-copy-bcc">Skrytá kopie</button></div></div>
            <div class="em-compose-content" data-em-compose-content>
            <label class="em-compose-field">Od<select name="account" required></select><span class="em-compose-sender" data-em-compose-sender hidden></span></label>
            <?php foreach(['to'=>'Komu','cc'=>'Kopie','bcc'=>'Skrytá kopie'] as $field=>$caption): ?>
            <?php if($field==='cc'): ?><div class="em-address-pair" data-em-copies hidden><?php endif; ?>
            <div class="em-recipient" <?= $field!=='to'?'id="em-copy-'.$field.'" data-em-copy-field="'.$field.'" hidden':'' ?>><label for="em-recipient-<?= $field ?>"><?= $caption ?></label><input id="em-recipient-<?= $field ?>" name="<?= $field ?>" <?= $field==='to'?'required':'' ?> autocomplete="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="em-options-<?= $field ?>" data-em-recipient><div id="em-options-<?= $field ?>" class="em-recipient-options" role="listbox" aria-label="Nabídka příjemců" hidden></div></div>
            <?php if($field==='bcc'): ?></div><?php endif; ?>
            <?php endforeach; ?>
            <p class="em-recipient-status" data-em-recipient-status role="status" hidden></p>
            <label class="em-compose-field">Předmět<input name="subject" maxlength="998"></label>
            <div class="em-body-label"><div class="em-compose-message-heading"><span>Zpráva</span><label data-em-quote hidden><input type="checkbox" name="include_quote" checked> Připojit původní zprávu</label></div>
                <div class="em-compose-message"><div data-em-compose-editor></div><section data-em-quote-content hidden><div class="em-quote-separator">Původní zpráva</div><div data-em-quote-preview></div></section></div>
            </div>
            <label class="em-compose-attachments"><span data-em-file-limit>Přílohy</span><input name="attachments" type="file" multiple></label>
            <div class="em-compose-files" data-em-compose-files role="list" aria-label="Přílohy rozepsané zprávy"></div>
            <p data-em-file-summary aria-live="polite"></p>
            <p class="em-size-warning" data-em-size-warning role="status" hidden>U zpráv nad 50 MB nelze zaručit přijetí poštovním serverem příjemce. Přílohy se při přenosu zvětší.</p>
            <p data-em-forward-files hidden>Vložené obrázky se připojí společně s původní zprávou.</p>
            </div>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="button" data-em-action="draft">Uložit koncept</button><button type="submit">Odeslat</button></div>
        </form>
    </dialog>
    <dialog class="em-dialog em-settings" data-em-signature aria-labelledby="em-settings-title">
        <form data-em-signature-form><h2 id="em-settings-title">Podpis a nastavení</h2>
            <div class="em-settings-tabs" role="tablist" aria-label="Osobní nastavení pošty">
                <button type="button" role="tab" id="em-tab-signature" aria-controls="em-panel-signature" data-em-settings-tab="signature" aria-selected="true">Podpis</button>
                <button type="button" role="tab" id="em-tab-settings" aria-controls="em-panel-settings" data-em-settings-tab="settings" aria-selected="false" tabindex="-1">Nastavení</button>
                <button type="button" role="tab" id="em-tab-accounts" aria-controls="em-panel-accounts" data-em-settings-tab="accounts" aria-selected="false" tabindex="-1">Pořadí ve společné schránce</button>
                <button type="button" role="tab" id="em-tab-folders" aria-controls="em-panel-folders" data-em-settings-tab="folders" aria-selected="false" tabindex="-1">Pořadí složek</button>
            </div>
            <div class="em-settings-content">
                <section role="tabpanel" id="em-panel-signature" aria-labelledby="em-tab-signature" data-em-settings-panel="signature"><p>Podpis se vloží do nové zprávy, odpovědi i přeposlání.</p><div data-em-signature-editor></div></section>
                <section role="tabpanel" id="em-panel-settings" aria-labelledby="em-tab-settings" data-em-settings-panel="settings" hidden>
                    <label>Obrázky ve zprávách<select name="show_images"><option value="0">Zobrazovat až na vyžádání</option><option value="1">Vždy zobrazovat ihned</option></select></label>
                    <label>Mazání pošty<select name="confirm_delete"><option value="1">S potvrzením</option><option value="0">Ihned bez potvrzování</option></select></label>
                </section>
                <section role="tabpanel" id="em-panel-accounts" aria-labelledby="em-tab-accounts" data-em-settings-panel="accounts" hidden><p>Šipkami změňte vlastní pořadí společných schránek. Přesunout můžete i souhrn Všechny pobočky a INFO. Osobní schránka zůstává nahoře.</p><div data-em-account-order class="em-account-order"></div></section>
                <section role="tabpanel" id="em-panel-folders" aria-labelledby="em-tab-folders" data-em-settings-panel="folders" hidden><p data-em-folder-order-hint></p><div class="em-order-list" data-em-order-list></div><button type="button" data-em-action="order_reset">Výchozí pořadí</button></section>
            </div>
            <div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-close>Zrušit</button><button type="submit">Uložit</button></div>
        </form>
    </dialog>
    <div class="em-dialog em-image-choice" data-em-image-choice popover="auto" role="dialog" aria-labelledby="em-image-choice-title">
        <form data-em-image-choice-form><h2 id="em-image-choice-title">Obrázky ve zprávách</h2><label>Pro další zprávy<select name="show_images"><option value="0">Obrázky zobrazovat až na vyžádání</option><option value="1">Vždy zobrazovat obrázky ihned</option></select></label><div class="em-form-error" role="alert"></div><div class="em-dialog-actions"><button type="button" data-em-image-dismiss>Teď neměnit</button><button type="submit">Uložit volbu</button></div></form>
    </div>
</section>
