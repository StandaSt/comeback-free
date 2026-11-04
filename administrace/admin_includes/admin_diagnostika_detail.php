<?php
/* Detail vybrane akce: srozumitelny souhrn, sparovane pozadavky, faze serveru a oddelene technicke identifikatory. */
declare(strict_types=1);
if(!isset($row,$diagDetailId) || !cb_diag_admin_allowed())return;
$detailRequests=$row['related_requests']??[];
$detailSpans=$row['server']['spans']??$row['spans']??[];
uasort($detailSpans,static fn($a,$b)=>($b['ms']??0)<=>($a['ms']??0));
?>
<section class="admin_diag_detail" aria-label="Detail <?= h(cb_admin_diag_label($row)) ?>">
    <header><h3><?= h(cb_admin_diag_label($row)) ?></h3><button type="button" data-diag-close="<?= h($diagDetailId) ?>">Zavřít detail</button></header>
    <dl class="admin_diag_metrics">
        <div><dt>Celkové čekání</dt><dd><?= h(cb_admin_diag_ms($row['total_ms']??$row['response_ms']??$row['php_ms']??null)) ?></dd></div>
        <div><dt><?= ($row['kind']??'')==='action'?'Nejdelší požadavek v PHP':'PHP do odpovědi' ?></dt><dd><?= h(cb_admin_diag_ms(cb_admin_diag_php($row))) ?></dd></div>
        <div><dt>Přenesená data</dt><dd><?= h(cb_admin_diag_bytes(isset($row['transfer_bytes'])?(float)$row['transfer_bytes']:null)) ?></dd></div>
        <div><dt>Výsledek</dt><dd><?= h(cb_admin_diag_status($row['status']??null)) ?></dd></div>
    </dl>
    <?php if(!empty($row['unknown_sizes']) || !empty($row['unknown_decoded_sizes'])): ?><p>Součet velikostí je neúplný; některé dílčí velikosti prohlížeč neposkytl.</p><?php endif; ?>
    <?php if(($row['kind']??'')==='action'): ?>
        <h4>Dílčí požadavky</h4>
        <p>Zobrazeno <?= count($detailRequests) ?><?= isset($row['requests'])?' z '.(int)$row['requests']:'' ?> klientských požadavků. Jejich časy se při souběhu nesčítají.</p>
    <?php endif; ?>
    <?php $detailHttpRows=($row['kind']??'')==='request'?[$row]:$detailRequests; if($detailHttpRows): ?>
        <div class="admin_diag_subtable"><table><thead><tr><th>Požadavek</th><th>Výsledek</th><th>Celkem</th><th>Do odpovědi</th><th>Stahování</th><th>PHP</th><th>Přenos</th><th>Po kompresi</th><th>Rozbaleno</th></tr></thead><tbody>
        <?php foreach($detailHttpRows as $request): ?><tr>
            <td><?= h(cb_admin_diag_label($request)) ?></td><td><?= h(cb_admin_diag_status($request['status']??null)) ?><?= isset($request['http_status'])?' · HTTP '.(int)$request['http_status']:'' ?></td>
            <td><?= h(cb_admin_diag_ms($request['total_ms']??null)) ?></td><td><?= h(cb_admin_diag_ms($request['wait_ms']??null)) ?></td><td><?= h(cb_admin_diag_ms($request['download_ms']??null)) ?></td><td><?= h(cb_admin_diag_ms(cb_admin_diag_php($request))) ?></td>
            <?php foreach(['transfer_bytes','encoded_bytes','decoded_bytes'] as $sizeKey): ?><td><?= h(cb_admin_diag_bytes(isset($request[$sizeKey])?(float)$request[$sizeKey]:null)) ?></td><?php endforeach; ?>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    <?php elseif(($row['kind']??'')==='action'): ?><p>Podrobné klientské požadavky v dostupné části logu chybí. Z neúplného záznamu nelze určit celý průběh.</p><?php endif; ?>
    <?php if($detailSpans): ?>
        <h4>Měřené operace serveru<?= ($row['kind']??'')==='action'?' · maximum za jednotlivý požadavek':'' ?></h4>
        <div class="admin_diag_subtable"><table><thead><tr><th>Operace</th><th>Čas</th><th>Počet měření</th></tr></thead><tbody>
        <?php foreach($detailSpans as $phase=>$span): ?><tr><td><?= h(cb_admin_diag_phase_label((string)$phase)) ?></td><td><?= h(cb_admin_diag_ms($span['ms']??null)) ?></td><td><?= isset($span['count'])?(int)$span['count']:'—' ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p class="admin_diag_note">Operace mohou být vnořené a jejich součet není rozkladem celkového čekání. U celé akce může maximum každé fáze pocházet z jiného požadavku.</p>
    <?php endif; ?>
    <details class="admin_diag_technical"><summary>Technické identifikátory</summary><dl>
        <?php foreach(['user'=>'ID uživatele','login'=>'ID přihlášení','action_id'=>'ID celé akce','request_id'=>'ID požadavku','browser'=>'Prohlížeč'] as $key=>$label): if(!isset($row[$key]) || $row[$key]==='')continue; ?><div><dt><?= h($label) ?></dt><dd><?= h((string)$row[$key]) ?></dd></div><?php endforeach; ?>
        <?php foreach($detailRequests as $request): ?><div><dt><?= h(cb_admin_diag_label($request)) ?> · ID požadavku</dt><dd><?= h($request['request_id']??'') ?></dd></div><?php endforeach; ?>
    </dl></details>
</section>
