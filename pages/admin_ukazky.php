<?php
// pages/admin_ukazky.php * Verze: V6 * Aktualizace: 26.2.2026 * Počet řádků: 129
declare(strict_types=1);

/*
 * ADMIN – NÁHLEDY ZOBRAZENÍ
 *
 * Účel:
 * - testovací stránka pro vizuální ladění modálů (makety)
 * - bez DB logiky, bez tokenů, bez session
 * - používá jednotné CSS pro modály (style/1/login_modal.css) načtené globálně v hlavicka.php
 *
 * Přepínání:
 * - bez URL parametrů
 * - bez reloadu
 * - bez vlastního fetch
 *
 * Pozn.:
 * - stránka se u tebe vkládá do <main> přes AJAX; skripty vložené do HTML se často nespustí.
 * - proto je přepínání udělané inline přes onchange atribut (funguje vždy).
 */

?>
<div class="page-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <div style="flex:1;"></div>

    <h2 style="margin:0; text-align:center; flex:2;">Náhledy zobrazení</h2>

    <div style="flex:1; display:flex; justify-content:flex-end;">
        <select
            id="ukSelect"
            style="padding:6px 10px;"
            onchange="
              (function(v){
                var a=document.getElementById('uk_prvni_login');
                var b=document.getElementById('uk_parovani_mobilu');
                if(!a||!b){return;}
                if(v==='parovani_mobilu'){a.style.display='none'; b.style.display='';}
                else{a.style.display=''; b.style.display='none';}
              })(this.value);
            "
        >
            <option value="prvni_login" selected>modal - prvni_login.php</option>
            <option value="parovani_mobilu">modal - parovani_mobilu.php</option>
        </select>
    </div>
</div>

<section class="card">
    <div id="ukWrap">

        <!-- 1) Maketa: prvni_login -->
        <div id="uk_prvni_login">
            <div class="modal-page" style="display:flex; justify-content:center;">
                <div class="modal" style="max-width:560px; width:100%;">

                    <button type="button" class="modal-x" aria-label="Zavřít" disabled>×</button>

                    <div class="modal-head">
                        <div class="modal-logo">
                            <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
                        </div>
                        <div>
                            <p class="modal-title">První přihlášení</p>
                            <p class="modal-sub">Je toto Vaše telefonní číslo?</p>
                        </div>
                    </div>

                    <div class="modal-box">
                        <p class="modal-label">Telefon (ze Směn):</p>
                        <p class="modal-phone">+420 777 123 456</p>
                    </div>

                    <div class="modal-row">
                        <div class="modal-qr">
                            <img src="<?= h(cb_url('img/icons/maketa.svg')) ?>" alt="QR maketa">
                        </div>

                        <div class="modal-instr">
                            Pokud telefonní číslo používáte, naskenujte QR kód, nebo zadejte do prohlížeče v mobilním telefonu tuto adresu:
                            <div class="modal-url">https://example.cz/includes/parovani_mobilu.php?t=TESTTOKEN123</div>
                            Dále postupujte podle pokynů v mobilním telefonu.
                        </div>
                    </div>

                    <div class="modal-foot">
                        <div class="modal-status">Čekám na spárování mobilu…</div>
                        <button type="button" class="modal-btn" disabled>Zkontrolovat</button>
                    </div>

                </div>
            </div>
        </div>

        <!-- 2) Maketa: parovani_mobilu -->
        <div id="uk_parovani_mobilu" style="display:none;">
            <div class="modal-page" style="display:flex; justify-content:center;">
                <div class="modal" style="max-width:560px; width:100%;">

                    <button type="button" class="modal-x" aria-label="Zavřít" disabled>×</button>

                    <div class="modal-head">
                        <div class="modal-logo">
                            <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
                        </div>
                        <div>
                            <p class="modal-title">Párování mobilu</p>
                            <div class="modal-sub">Povol notifikace a spáruj mobil pro schvalování přihlášení.</div>
                        </div>
                    </div>

                    <p class="muted">Postup: 1) Povolit notifikace → 2) Spárovat mobil.</p>

                    <button type="button" class="modal-btn" disabled>1) Povolit notifikace</button>
                    <div style="height:10px"></div>
                    <button type="button" class="modal-btn primary" disabled>2) Spárovat mobil</button>

                    <div class="out">Stav: čekám…</div>

                </div>
            </div>
        </div>

    </div>
</section>

<?php
/* pages/admin_ukazky.php * Verze: V6 * Aktualizace: 26.2.2026 * Počet řádků: 129 */
// Konec souboru