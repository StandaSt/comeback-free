<?php
// HTML náhledu i e-mailu s jednotnými pevnými sloupci všech poboček a escapovanými poznámkami.
declare(strict_types=1);
$money=static fn(?int $value): string => $value===null ? '—' : cb_format('p2',cb_kt_decimal($value));
$period=cb_format('d',$data['from']).' – '.cb_format('d',$data['to']);
// Stejné rozměry pro každou pobočku; inline styly a atributy šířek fungují i bez CSS aplikace.
$columns=[['Údaje',150],['Odevzdané',110],['Spočítané',110],['Rozdíl',110],['Poznámka',380]];
?>
<div style="font-family:Arial,sans-serif;font-size:13px;color:#202b39">
<h2 style="font-size:16px">Součet tržby a stravenek</h2>
<p>Období: <strong><?= h($period) ?></strong></p>
<p>Spočítaná tržba: <strong><?= h($money($data['trzba'])) ?></strong><br>Spočítané stravenky: <strong><?= h($money($data['stravenky'])) ?></strong></p>
<p>Za pobočky: <?= h(implode(', ',array_column($data['controls'],'name'))) ?></p>
<?php foreach ($data['controls'] as $control): ?>
<h3 style="font-size:14px;margin-top:18px"><?= h($control['name']) ?> · kontrola #<?= $control['id'] ?></h3>
<table width="860" style="border-collapse:collapse;table-layout:fixed;font-size:13px;width:860px;min-width:860px">
<colgroup><?php foreach ($columns as [$label,$width]): ?><col width="<?= $width ?>" style="width:<?= $width ?>px"><?php endforeach; ?></colgroup>
<thead><tr><?php foreach ($columns as [$label,$width]): ?><th width="<?= $width ?>" style="box-sizing:border-box;width:<?= $width ?>px;border:1px solid #cad3df;padding:4px 6px;text-align:left;background:#e9eef5"><?= h($label) ?></th><?php endforeach; ?></tr></thead>
<tbody><?php foreach (cb_kt_polozky() as $key=>[$label,$manual]): $row=$control['rows'][$key]; ?>
<tr<?= in_array($key,['trzba','rozdil'],true) ? ' style="font-weight:bold;border-top:2px solid #72839a"' : '' ?>><th width="150" style="box-sizing:border-box;width:150px;border:1px solid #cad3df;padding:4px 6px;text-align:left;white-space:nowrap"><?= h($label) ?></th>
<?php foreach (['left','right','diff'] as $field): ?><td width="110" style="box-sizing:border-box;width:110px;border:1px solid #cad3df;padding:4px 6px;text-align:right;white-space:nowrap;<?= $field==='diff' && $row[$field]!==null ? ($row[$field]<0 ? 'background:#ffe0e0;color:#9d1515' : 'background:#e3f2df;color:#285a24') : '' ?>"><?= h($money($row[$field])) ?></td><?php endforeach; ?>
<td width="380" style="box-sizing:border-box;width:380px;border:1px solid #cad3df;padding:4px 6px;white-space:normal;word-wrap:break-word;overflow-wrap:anywhere"><?= nl2br(h($row['note'])) ?></td></tr>
<?php endforeach; ?></tbody></table>
<?php endforeach; ?>
<p style="margin-top:18px">Přehled za období <?= h($period) ?> za všechny pobočky vygeneroval IS Comeback dne <?= h($generated) ?>.</p>
</div>
