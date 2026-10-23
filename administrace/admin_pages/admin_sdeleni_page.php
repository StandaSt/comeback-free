<?php
declare(strict_types=1);
/* Účel: Kompaktní správa sdělení s náhledem příjemců a samostatně obnovovaným archivem jejich přečtení. */
require_once __DIR__.'/../admin_lib/admin_sdeleni.php';
require_once __DIR__.'/../../common/lib/admin_sdeleni.php';
$sdFirmy=cb_admin_sdeleni_firmy(db(),cb_sdeleni_user());
$sdPage=max(0,(int)($_GET['sd_strana']??0));
$sdId=max(0,(int)($_GET['sd_id']??0));
$sdArchive=cb_sdeleni_admin_archiv(db(),$sdFirmy,$sdId,$sdPage*50);
$sdMore=count($sdArchive)>50;$sdArchive=array_slice($sdArchive,0,50);
?>
<div class="sd_admin" data-sdeleni-admin>
    <details class="sd_compose"<?= $sdId===0?' open':'' ?>>
        <summary>Nové sdělení</summary>
        <form method="post" action="<?= h(cb_root_url('index.php')) ?>" class="sd_form" data-sdeleni-form>
            <label>Nadpis<input name="nadpis" maxlength="120" required></label>
            <label>Text<textarea name="obsah" rows="3" maxlength="1000" required></textarea></label>
            <div class="sd_form_row">
                <label>Platnost ve dnech<input name="dny" type="number" min="1" max="10" value="3" required></label>
                <label>Příjemci<select name="rezim" required><option value="">Vyberte příjemce</option><option value="vsichni">Všichni aktivní</option><option value="skupina">Skupina</option><option value="jednotlivec">Konkrétní uživatelé</option></select></label>
            </div>
            <div class="sd_filters" data-sdeleni-skupina hidden>
                <?php foreach (['role'=>'Role','sloty'=>'Pracovní slot','pobocky'=>'Pobočky'] as $sdKey=>$sdLabel): ?>
                    <fieldset><legend><?= h($sdLabel) ?></legend><div data-sdeleni-list="<?= h($sdKey) ?>"></div></fieldset>
                <?php endforeach; ?>
            </div>
            <div class="sd_users" data-sdeleni-jednotlivec hidden>
                <span class="sd_users_label">Uživatelé</span>
                <details class="sd_users_picker">
                    <summary data-sdeleni-users-summary>Vyberte uživatele</summary>
                    <div class="sd_users_list" data-sdeleni-users role="group" aria-label="Uživatelé"></div>
                </details>
            </div>
            <button class="sd_button" type="submit" data-sdeleni-preview-btn disabled>Zobrazit náhled a příjemce</button>
        </form>
        <p class="sd_status" data-sdeleni-status role="status"></p>
        <section class="sd_preview" data-sdeleni-preview hidden>
            <div class="sd_note"><strong data-sdeleni-preview-title></strong><p data-sdeleni-preview-body></p></div>
            <p data-sdeleni-preview-count></p>
            <ol class="sd_names" data-sdeleni-preview-names></ol>
            <button class="sd_button" type="button" data-sdeleni-publish>Zveřejnit sdělení</button>
        </section>
    </details>
    <section data-sdeleni-admin-archive>
    <h2 class="sd_title">Archiv sdělení</h2>
    <?php if ($sdArchive===[]): ?><p>Žádná sdělení.</p><?php endif; ?>
    <?php foreach ($sdArchive as $sd): ?>
        <?php // Archiv ukazuje jen stručný řádek; obsah se otevře až na žádost správce. ?>
        <details class="sd_archive_item sd_archive_fold"<?= $sdId>0?' open':'' ?>>
            <summary class="sd_archive_summary">
                <span class="sd_meta"><?= (int)$sd['id_sdeleni'] ?></span>
                <strong><?= h($sd['nadpis']) ?></strong>
                <span class="sd_archive_counts">Zobrazeno <?= (int)$sd['zobrazeno_pocet'] ?> z <?= (int)$sd['celkem'] ?> (přečteno <?= (int)$sd['precteno_pocet'] ?>)</span>
                <span class="sd_archive_toggle">Detail</span>
            </summary>
            <div class="sd_archive_detail">
            <p class="sd_text"><?= h($sd['obsah']) ?></p>
            <p class="sd_meta"><?= h($sd['autor']?:'Uživatel #'.$sd['id_user_vytvoril']) ?> · <?= h(cb_sdeleni_cas($sd['odeslano'])) ?> · Platnost do <?= h(cb_sdeleni_cas($sd['platnost_do'])) ?></p>
            <?php if ($sdId===0): ?>
                <a href="<?= h(cb_root_url('index.php?m=administrace&page=sdeleni&sd_id='.(int)$sd['id_sdeleni'])) ?>">Příjemci a časy přečtení</a>
            <?php else: $sdRecipients=cb_sdeleni_admin_prijemci(db(),$sdId); ?>
                <div class="sd_table_scroll"><table class="sd_table"><thead><tr><th>Uživatel</th><th>Zobrazeno</th><th>Přečteno</th></tr></thead><tbody>
                <?php foreach ($sdRecipients as $person): ?><tr><td><?= h($person['jmeno']?:'Uživatel #'.$person['id_user']) ?></td><td><?= h(cb_sdeleni_cas($person['zobrazeno'])) ?></td><td><?= h(cb_sdeleni_cas($person['precteno'])) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <a href="<?= h(cb_root_url('index.php?m=administrace&page=sdeleni')) ?>">Zpět na všechna sdělení</a>
            <?php endif; ?>
            </div>
        </details>
    <?php endforeach; ?>
    <?php if ($sdId===0): ?><nav class="sd_pagination" aria-label="Stránky archivu">
        <?php if ($sdPage>0): ?><a href="<?= h(cb_root_url('index.php?m=administrace&page=sdeleni&sd_strana='.($sdPage-1))) ?>">Novější</a><?php endif; ?>
        <?php if ($sdMore): ?><a href="<?= h(cb_root_url('index.php?m=administrace&page=sdeleni&sd_strana='.($sdPage+1))) ?>">Starší</a><?php endif; ?>
    </nav><?php endif; ?>
    </section>
</div>
