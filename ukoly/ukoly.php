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

?>
<section class="module_shell">
    <?php require __DIR__ . '/uk_includes/uk_menu.php'; ?>

    <section class="module_content">
        <div class="module_placeholder">
            <h1>Úkoly-požadavky</h1>
            <p>Modul je připravený pro další doplnění obsahu.</p>
        </div>
    </section>
</section>
