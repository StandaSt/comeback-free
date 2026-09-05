<?php
declare(strict_types=1);

/* Samostatná URL pouze pro dlouhý NDJSON stream AI analytika. */
$_SERVER['HTTP_X_COMEBACK_AI_ANALYTIK'] = '1';
require __DIR__ . '/index.php';
