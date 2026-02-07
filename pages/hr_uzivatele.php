<?php
// pages/admin_infoblok.php V2 – počet řádků:  ninety-something – 7.2.2026
declare(strict_types=1);

/*
 * Přehled informací – TECH / SERVER / KLIENT
 * - pouze obsah stránky
 * - žádná hlavička, žádná patička
 * - žádné globální include
 * - vše otevřené se zde i zavře
 */

$server = [
    'PHP verze'        => PHP_VERSION,
    'Server software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'Server name'     => $_SERVER['SERVER_NAME'] ?? '',
    'Server IP'       => $_SERVER['SERVER_ADDR'] ?? '',
    'Dokument root'   => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'Čas serveru'     => date('d.m.Y H:i:s'),
];

$db = [];
if (function_exists('db')) {
    try {
        $conn = db();
        $db = [
            'DB host'     => $conn->host_info ?? '',
            'DB charset'  => $conn->character_set_name() ?? '',
            'DB server'   => $conn->server_info ?? '',
            'DB klient'   => $conn->client_info ?? '',
        ];
    } catch (Throwable $e) {
        $db['DB stav'] = 'chyba připojení';
    }
}
?>

<section class="card">

    <h3>Server / PHP</h3>
    <table class="table table-fixed" style="width:auto">
        <tbody>
        <?php foreach ($server as $k => $v): ?>
            <tr>
                <td style="white-space:nowrap"><strong><?= h($k) ?></strong></td>
                <td><?= h($v) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <br>

    <h3>Databáze</h3>
    <table class="table table-fixed" style="width:auto">
        <tbody>
        <?php if ($db): ?>
            <?php foreach ($db as $k => $v): ?>
                <tr>
                    <td style="white-space:nowrap"><strong><?= h($k) ?></strong></td>
                    <td><?= h($v) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2">DB není dostupná</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <br>

    <h3>Klient (prohlížeč)</h3>
    <table class="table table-fixed" style="width:auto">
        <tbody>
            <tr><td><strong>User-Agent</strong></td><td id="ua">–</td></tr>
            <tr><td><strong>Platforma</strong></td><td id="platform">–</td></tr>
            <tr><td><strong>Jazyk</strong></td><td id="lang">–</td></tr>
            <tr><td><strong>Rozlišení</strong></td><td id="res">–</td></tr>
        </tbody>
    </table>

</section>

<script>
(function () {
    const s = (id, v) => {
        const e = document.getElementById(id);
        if (e) e.textContent = v;
    };

    s('ua', navigator.userAgent.substring(0, 120));
    s('platform', navigator.platform || '');
    s('lang', navigator.language || '');
    s('res', window.screen.width + ' × ' + window.screen.height);
})();
</script>

<?php
/* pages/admin_infoblok.php V2 – konec souboru */