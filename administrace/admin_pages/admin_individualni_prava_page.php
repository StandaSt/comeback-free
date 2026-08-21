<?php
declare(strict_types=1);
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
