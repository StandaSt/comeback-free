<?php
/* Co-Link: vstup denniho cronu pro uklid; bez argumentu voli server, vypise souhrn a vrati stav behu. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// Chybne argumenty nesmeji omylem spustit uklid serveru; napoveda neotevira databazi.
$coEnvironment = 'server';
$coEnvironmentGiven = false;
$coHelp = false;
$coInvalid = false;
// getopt neznamy prepinac muze preskocit; zde kazdy argument vyslovne overujeme pred bootstrapem.
for ($coIndex = 1; $coIndex < count($argv); $coIndex++) {
    $coArgument = $argv[$coIndex];
    if ($coArgument === '--help') {
        $coHelp = true;
    } elseif ($coArgument === '--prostredi' || str_starts_with($coArgument, '--prostredi=')) {
        if ($coEnvironmentGiven) { $coInvalid = true; break; }
        $coEnvironmentGiven = true;
        $coEnvironment = $coArgument === '--prostredi' ? ($argv[++$coIndex] ?? '') : substr($coArgument, strlen('--prostredi='));
    } else {
        $coInvalid = true;
        break;
    }
}
if ($coInvalid || !in_array($coEnvironment, ['server', 'local'], true)) {
    fwrite(STDERR, "Pouziti: php co_cron/co_uklid.php [--prostredi=server|local] [--help]\n");
    exit(2);
}
if ($coHelp) {
    echo "Denni uklid Co-Linku. Bez argumentu SERVER a skutecny uklid.\n",
        "Pouziti: php co_cron/co_uklid.php [--prostredi=server|local] [--help]\n";
    exit(0);
}

// Bootstrap i konfigurace se nacitaji globalne pred prvnim databazovym dotazem.
require_once __DIR__ . '/../co_lib/co_lib.php';
require_once __DIR__ . '/../co_lib/co_uklid.php';
$PROSTREDI = $coEnvironment === 'local' ? 'LOCAL' : 'SERVER';

try {
    if (!co_pripraveno()) {
        fwrite(STDERR, "Co-Link neni pripraven; uklid nebyl proveden.\n");
        exit(2);
    }
    $coSummary = co_uklid_provest();
    echo json_encode(['prostredi' => $PROSTREDI] + $coSummary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Uklid Co-Linku selhal: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
exit(0);
