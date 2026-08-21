<?php
/*
 * Ucel souboru: Vykresluje blok formulare pro zadani HR pozadavku.
 * Neobsahuje nacitani dat ani zpracovani formulare.
 */
declare(strict_types=1);
?>
<section class="hr_panel">
    <div class="hr_panel_header">
        <div>
            <h2 class="hr_request_title hr_panel_title">Zadání požadavku</h2>
        </div>
    </div>

    <?php if ($pozadavkyMuzeZadat): ?>
        <form class="hr_form hr_request_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=pozadavky')) ?>" data-hr_request_form>
            <input type="hidden" name="cb_action" value="hr_pozadavek_vytvorit">
            <span class="hr_request_text">Požaduji</span>
            <select class="hr_request_select" name="pocet">
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= h($i) ?>"<?= $i === 1 ? ' selected' : '' ?>><?= h($i) ?></option>
                <?php endfor; ?>
            </select>
            <span class="hr_request_text">zaměstnance na pozici</span>
            <select class="hr_request_select" name="id_slot" data-hr-request-slot required>
                <option value="">Vyber</option>
                <option value="1">instor</option>
                <option value="2">kurýr</option>
            </select>
            <span class="hr_request_text">pro pobočku <?= h((string)$pozadavkyMainPobocka['nazev']) ?>.</span>
            <span class="hr_request_text">Poznámka:</span>
            <input class="hr_note_input hr_request_field" type="text" name="upresneni" maxlength="500" placeholder="Zde upřesněte, třeba termín nástupu.">
            <button class="hr_primary_button hr_request_submit" type="submit">Zadat požadavek</button>
        </form>
    <?php elseif ($pozadavkyChybaZadani !== ''): ?>
        <p class="hr_empty_state"><?= h($pozadavkyChybaZadani) ?></p>
    <?php else: ?>
        <p class="hr_empty_state">Na zadání požadavku nemáte právo.</p>
    <?php endif; ?>
</section>
