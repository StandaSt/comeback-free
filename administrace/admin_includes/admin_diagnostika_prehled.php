<?php
/* Kompaktni prehled diagnostiky: prubeh mereni, filtry a detail pod radkem; technicka data neprotahuji tabulku. */
declare(strict_types=1);
if (!isset($diagConfig) || !cb_diag_admin_allowed()) return;
$diagNow=time();$diagStarted=$diagConfig['started_at']??null;$diagStop=$diagConfig['stopped_at']??null;
$diagEnd=$diagConfig['enabled']?$diagNow:($diagStop??$diagNow);
?>
<link rel="stylesheet" href="<?= h(cb_root_url('administrace/admin_style/admin_diagnostika.css').'?v='.filemtime(__DIR__.'/../admin_style/admin_diagnostika.css')) ?>">
<div class="admin_diag" data-admin-diag data-enabled="<?= $diagConfig['enabled']?'1':'0' ?>" data-started="<?= (int)$diagStarted ?>" data-until="<?= (int)$diagConfig['until'] ?>" data-server-now="<?= h((string)microtime(true)) ?>">
<section class="blok admin_diag_settings">
    <h2 class="blok_title">Diagnostika Co-Linku a E-mailu</h2>
    <?php if ($diagNotice!==''): ?><p role="status"><?= h($diagNotice) ?></p><?php endif; ?>
    <dl class="admin_diag_clock">
        <div><dt>Stav</dt><dd data-diag-state><?= $diagConfig['enabled']?'Zapnuto':'Vypnuto' ?></dd></div>
        <div><dt data-diag-elapsed-label><?= $diagConfig['enabled']?'Běží':'Poslední běh' ?></dt><dd data-diag-elapsed><?= $diagStarted? h(cb_admin_diag_duration($diagEnd-$diagStarted)):($diagConfig['enabled'] || $diagStop?'Začátek nezaznamenán':'—') ?></dd></div>
        <div><dt>Zbývá</dt><dd data-diag-remaining><?= $diagConfig['enabled']?h(cb_admin_diag_duration($diagConfig['until']-$diagNow)):'—' ?></dd></div>
        <div><dt><?= $diagConfig['enabled']?'Konec měření':'Poslední ukončení' ?></dt><dd><?= $diagConfig['enabled']?h(date('d. m. Y H:i:s',$diagConfig['until'])):($diagStop?h(date('d. m. Y H:i:s',$diagStop)):'—') ?></dd></div>
    </dl>
    <?php if ($diagStarted): ?><p class="admin_diag_note">Začátek běhu: <?= h(date('d. m. Y H:i:s',$diagStarted)) ?>.</p><?php elseif ($diagConfig['enabled']): ?><p class="admin_diag_note">Starší nastavení neobsahuje čas začátku. Přesnou délku ukážeme od příštího zapnutí.</p><?php endif; ?>
    <form class="admin_diag_form" method="post" action="<?= h(cb_root_url('administrace/admin_ajax/admin_diagnostika_nastaveni.php')) ?>">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <label class="admin_diag_switch"><input type="checkbox" name="enabled" value="1" <?= $diagConfig['enabled']?'checked':'' ?>> Zapnout diagnostiku</label>
        <label>Doba měření od uložení<select name="minutes"><?php foreach([15,30,60,120] as $minutes): ?><option value="<?= $minutes ?>" <?= ($diagConfig['minutes']??30)===$minutes?'selected':'' ?>><?= $minutes ?> minut</option><?php endforeach; ?></select></label>
        <label>ID uživatelů (čárkami; prázdné = všichni)<input type="text" name="users" maxlength="250" value="<?= h(implode(', ',$diagConfig['users'])) ?>"></label>
        <button type="submit">Uložit nastavení</button>
    </form>
    <details class="admin_diag_help"><summary>Jak měření funguje</summary>
        <p>Změnu zapnutí nebo vypnutí převezmou otevřená okna při nejbližší běžné komunikaci se serverem. Pro změření celé inicializace klienta modul znovu načtěte. Prodloužení zapnutého měření zachová jeho začátek.</p>
        <p>Ukládají se technická metadata a interní ID, bez obsahu zpráv, adres, hledaných slov a hesel. Limit je 16 MiB za den a uchování 14 dní. Starší soubory se odstraní při dalším zápisu; nedostupný zápis neblokuje práci v IS.</p>
    </details>
</section>
<section class="blok admin_diag_results">
    <h2 class="blok_title">Naměřené čekání a data</h2>
    <form class="admin_diag_form admin_diag_filters" method="get" action="<?= h(cb_root_url('index.php')) ?>">
        <input type="hidden" name="m" value="administrace"><input type="hidden" name="page" value="diagnostika_vykonu">
        <label>Den<input type="date" name="diag_day" value="<?= h($diagDay) ?>"></label>
        <label>ID uživatele<input type="number" name="diag_user" min="0" value="<?= $diagUser ?>"></label>
        <label>Modul<select name="diag_module"><option value="">Oba moduly</option><option value="email" <?= $diagModule==='email'?'selected':'' ?>>E-mail</option><option value="co_link" <?= $diagModule==='co_link'?'selected':'' ?>>Co-Link</option></select></label>
        <label>Druh<select name="diag_kind"><?php foreach(['action'=>'Celé pracovní akce','request'=>'HTTP požadavky','server'=>'Server',''=>'Vše'] as $kind=>$label): ?><option value="<?= h($kind) ?>" <?= $diagKind===$kind?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
        <label>Čekání alespoň (ms)<input type="number" name="diag_slow" min="0" value="<?= h((string)$diagSlow) ?>"></label>
        <button type="submit">Zobrazit</button>
    </form>
    <p class="admin_diag_note">Počet záznamů: <?= count($diagRows) ?>. Detail se otevře přímo pod vybranou akcí.</p>
    <details class="admin_diag_help"><summary>Jak číst časy a objemy dat</summary>
        <p>Nejvýše 300 posledních shod z konce denního souboru (4 MiB). Přenos zahrnuje HTTP režii; rozbalená data jsou velikost těla po rozbalení. „—“ znamená nezjištěno, nikoli nulu. Neúplné součty mají uvedený počet nezjištěných velikostí.</p>
        <p>PHP u celé akce ukazuje maximum jednoho dílčího požadavku. Souběžné časy ani záznamy akcí, požadavků a serveru se nesčítají. Čas čekání zahrnuje server i síť; rozdíl proti PHP sám o sobě neprokazuje pomalý internet. Dokončení rozhraní nezaručuje načtení externích obrázků ani otevření přílohy v jiné aplikaci.</p>
    </details>
    <div class="cb_table_wrap"><table class="cb_table admin_diag_table"><colgroup><col><col><col><col><col><col><col><col><col></colgroup><thead><tr><th>Čas / uživatel</th><th>Modul / akce</th><th>Výsledek</th><th>Čekání</th><th>PHP / max. v akci</th><th>Nejdelší měřená fáze</th><th>Přenos</th><th>Rozbalená data</th><th>Detail</th></tr></thead><tbody>
    <?php foreach ($diagRows as $diagIndex=>$row): $diagDetailId='admin-diag-detail-'.$diagIndex;$diagTimestamp=strtotime((string)($row['at']??'')); ?>
        <tr class="admin_diag_event"><td><?= $diagTimestamp?h(date('H:i:s',$diagTimestamp)):'—' ?><small><?= $diagTimestamp?h(date('d. m. Y',$diagTimestamp)):'' ?> · ID <?= (int)($row['user']??0) ?></small></td>
        <td><?= h(cb_admin_diag_label($row)) ?><small><?= ($row['module']??'')==='email'?'E-mail':'Co-Link' ?><?= !empty($row['meta']['background'])?' · automaticky':'' ?></small></td>
        <td><?= h(cb_admin_diag_status($row['status']??null)) ?><small><?= h(['server'=>'Server','request'=>'Požadavek','action'=>'Celá akce'][$row['kind']??'']??'') ?></small></td>
        <td><?= h(cb_admin_diag_ms($row['total_ms']??$row['response_ms']??$row['php_ms']??null)) ?></td>
        <td><?= isset($row['server']['aggregation'])?'max. ':'' ?><?= h(cb_admin_diag_ms(cb_admin_diag_php($row))) ?></td>
        <td class="admin_diag_phase"><?= h(cb_admin_diag_phase($row)) ?></td>
        <td><?= h(cb_admin_diag_bytes(isset($row['transfer_bytes'])?(float)$row['transfer_bytes']:null)) ?><?= !empty($row['unknown_sizes'])?'<small>+ '.(int)$row['unknown_sizes'].'× nezjištěno</small>':'' ?></td>
        <td><?= h(isset($row['decoded_bytes'])?cb_admin_diag_bytes((float)$row['decoded_bytes']):(isset($row['response_body_bytes'])?cb_admin_diag_bytes((float)$row['response_body_bytes']).' (server)':'—')) ?><?= !empty($row['unknown_decoded_sizes'])?'<small>+ '.(int)$row['unknown_decoded_sizes'].'× nezjištěno</small>':'' ?></td>
        <td><button type="button" data-diag-toggle="<?= h($diagDetailId) ?>" aria-controls="<?= h($diagDetailId) ?>" aria-expanded="false">Detail</button></td></tr>
        <tr id="<?= h($diagDetailId) ?>" class="admin_diag_detail_row" hidden><td colspan="9"><?php require __DIR__.'/admin_diagnostika_detail.php'; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$diagRows): ?><tr><td colspan="9">Pro tento filtr nejsou zaznamenaná měření.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
</div>
