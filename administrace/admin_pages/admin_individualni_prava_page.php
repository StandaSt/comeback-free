<?php
// Vyhledani osob a sprava individualnich vyjimek prav jsou dostupne pouze s pravem 103.
declare(strict_types=1);
if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(103)) {
    http_response_code(403);
    echo '<section class="blok"><h2 class="blok_title">Přístup zamítnut</h2><p>Nemáte právo spravovat individuální práva uživatelů (103).</p></section>';
    return;
}
?>
<!-- Stranka pro vyhledani uzivatele a spravu jeho individualnich vyjimek prav. -->
<div class="admin_individual" data-admin-individual="1">
    <div class="admin_individual_search">
        <label for="admin_individual_search">Vyhledání osoby podle jména, emailu nebo telefonu</label>
        <input
            id="admin_individual_search"
            type="search"
            autocomplete="off"
            placeholder="Piš sem"
            data-admin-individual-search
        >
    </div>
    <div class="admin_individual_results" data-admin-individual-results></div>
    <section class="admin_individual_exception_users" data-admin-individual-exception-users-wrap>
        <h2>Uzivatele s vyjimkami</h2>
        <div class="admin_individual_results" data-admin-individual-exception-users></div>
    </section>
    <div class="admin_individual_detail" data-admin-individual-detail></div>
</div>
