<?php
require __DIR__ . '/../../config/secrets.php';
$cfg = $SECRETS['db']['local'];
$c = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($c->connect_error) { fwrite(STDERR, $c->connect_error . PHP_EOL); exit(1); }
$sqls = [
"SELECT COUNT(*) AS total FROM objednavky_restia",
"SELECT COUNT(DISTINCT restia_id_obj) AS distinct_restia_ids FROM objednavky_restia",
"SELECT restia_id_obj, COUNT(*) AS cnt FROM objednavky_restia GROUP BY restia_id_obj HAVING cnt > 1 ORDER BY cnt DESC LIMIT 20",
"SELECT id_pob, COUNT(*) AS cnt FROM objednavky_restia GROUP BY id_pob ORDER BY id_pob"
];
foreach ($sqls as $sql) {
  echo "---\n$sql\n";
  $r = $c->query($sql);
  if (!$r) { fwrite(STDERR, $c->error . PHP_EOL); exit(1); }
  while ($row = $r->fetch_assoc()) { echo json_encode($row, JSON_UNESCAPED_UNICODE), PHP_EOL; }
}
