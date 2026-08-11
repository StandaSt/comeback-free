<?php
declare(strict_types=1);

/*
 * Modulový vstup HelpDesk.
 * Sem nepatří SQL dotazy, HTML bloky, AJAX handlery ani pomocné funkce.
 * Soubor má pouze připravit modul, předat akce dispatcheru, vybrat stránku/pohled a načíst modulový layout.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../provoz/lib/asset_url.php';
require_once __DIR__ . '/hl_lib/hl_request_dispatch.php';

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

cb_helpdesk_request_dispatch();

?>
<?php require __DIR__ . '/hl_includes/hl_main.php'; ?>
