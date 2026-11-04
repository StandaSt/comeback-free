<?php
/* Co-Link: preda pocatecni udaje a vypinatelnou diagnostiku, pak nacte JavaScript v puvodnim poradi. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<script id="co-boot" type="application/json"><?= json_encode($boot,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<?php require_once __DIR__.'/../../common/lib/diagnostika_vykonu.php'; ?>
<script src="../common/js/diagnostika_vykonu.js?v=<?= filemtime(__DIR__.'/../../common/js/diagnostika_vykonu.js') ?>" data-until="<?= cb_diag_boot((int)($boot['identita']['id']??0)) ?>" defer></script>
<script src="../common/js/qrcode.min.js" defer></script>
<script src="co_js/co_message_format.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_message_format.js') ?>" defer></script>
<script src="co_js/co_emoji.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_emoji.js') ?>" defer></script>
<script src="co_js/co_editor.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_editor.js') ?>" defer></script>
<script src="co_js/co_installation.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_installation.js') ?>" defer></script>
<script src="co_js/co_push.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_push.js') ?>" defer></script>
<script src="co_js/co_auth_restore.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_auth_restore.js') ?>" defer></script>
<script src="../common/js/tooltip.js?v=<?= filemtime(dirname(__DIR__).'/../common/js/tooltip.js') ?>" defer></script>
<script src="co_js/co_app.js?v=<?= filemtime(dirname(__DIR__).'/co_js/co_app.js') ?>" defer></script>
