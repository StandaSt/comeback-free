<?php
declare(strict_types=1);

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'ukoly';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Úkoly lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

cb_pobocky_bootstrap_session();

$ukolyMenu = [
    'Nové zadání',
    'Přehled úkolů',
    'Úkoly pro mě',
    'Mé požadavky',
];

?>
<section class="module_shell">
    <nav class="module_menu" aria-label="Menu úkolů">
        <h2 class="module_menu_title">Úkoly-požadavky</h2>
        <div class="module_menu_list">
            <?php foreach ($ukolyMenu as $index => $item): ?>
                <button type="button" class="module_menu_btn<?= $index === 0 ? ' is-active' : '' ?>">
                    <span><?= h($item) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="module_content">
        <div class="module_placeholder">
            <h1>Úkoly-požadavky</h1>
            <p>Modul je připravený pro další doplnění obsahu.</p>
        </div>
    </div>
</section>

