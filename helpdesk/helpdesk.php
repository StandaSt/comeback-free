<?php
declare(strict_types=1);

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../provoz/lib/asset_url.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'helpdesk';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul HelpDesk lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    exit;
}

?>
<?php require __DIR__ . '/hl_includes/hl_main.php'; ?>
