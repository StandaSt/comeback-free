<?php
require __DIR__ . '/../../config/secrets.php';
$cfg = $SECRETS['db']['local'];
$c = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($c->connect_error) { fwrite(STDERR, $c->connect_error . PHP_EOL); exit(1); }
$r = $c->query('SHOW CREATE TABLE objednavky_restia');
if (!$r) { fwrite(STDERR, $c->error . PHP_EOL); exit(1); }
$row = $r->fetch_assoc();
echo ($row['Create Table'] ?? ''), PHP_EOL;
