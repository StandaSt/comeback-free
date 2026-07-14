<?php
require __DIR__ . '/../../config/secrets.php';
$cfg = $SECRETS['db']['local'];
$c = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($c->connect_error) {
    fwrite(STDERR, $c->connect_error . PHP_EOL);
    exit(1);
}
foreach (['helpdesk','helpdesk_zprava','helpdesk_snapshot','helpdesk_notifikace','helpdesk_sledujici'] as $t) {
    $r = $c->query("SHOW CREATE TABLE `{$t}`");
    if (!$r) {
        fwrite(STDERR, $c->error . PHP_EOL);
        exit(1);
    }
    $row = $r->fetch_assoc();
    echo '---' . $t . PHP_EOL;
    echo $row['Create Table'] . PHP_EOL;
}
?>
