<?php
declare(strict_types=1);

/*
 * Modulovy vstup HR.
 * Sem nepatri SQL dotazy, HTML bloky, AJAX handlery ani pomocne funkce.
 * Soubor ma pouze pripravit modul, predat akce dispatcheru a vybrat zpusob vykresleni stranky.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/form_post.php';
require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
require_once __DIR__ . '/../common/lib/handle_set_period.php';
require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';
require_once __DIR__ . '/hr_includes/hr_data.php';
require_once __DIR__ . '/hr_lib/hr_pages.php';
require_once __DIR__ . '/hr_lib/hr_request_dispatch.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'hr';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul HR lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

$cbUser = $_SESSION['cb_user'] ?? [];
if (!cb_pravo_ma(300)) {
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}

cb_pobocky_bootstrap_session();

$currentPage = cb_hr_current_page();
$page = $currentPage['key'];
$pageTitle = $currentPage['title'];

$cbProfile = $_SESSION['cb_user_profile'] ?? [];
$userName = '';
$userRole = '';

if (is_array($cbUser)) {
    $userName = trim((string)($cbUser['name'] ?? '') . ' ' . (string)($cbUser['surname'] ?? ''));
    if ($userName === '') {
        $userName = trim((string)($cbUser['email'] ?? ''));
    }
    if ($userName === '' && (int)($cbUser['id_user'] ?? 0) > 0) {
        $userName = 'Uživatel #' . (string)(int)$cbUser['id_user'];
    }

    $userRole = trim((string)($cbUser['role'] ?? ''));
}

if ($userRole === '' && is_array($cbProfile)) {
    $roles = $cbProfile['roles'] ?? [];
    if (is_array($roles) && isset($roles[0]) && is_array($roles[0])) {
        $userRole = trim((string)($roles[0]['name'] ?? ''));
    }
}

if ($userName === '') {
    $userName = 'Uživatel';
}
if ($userRole === '') {
    $userRole = 'Uživatel';
}
$db = db();
$hrEmployeeHeader = null;
if ($page === 'zamestnanec' && (int)($_GET['id'] ?? 0) > 0) {
    $hrEmployeeHeader = hr_fetch_employee($db, (int)$_GET['id']);
}
$isNaborDetail = $page === 'nabor' && (int)($_GET['id_vd'] ?? 0) > 0;
if ($isNaborDetail) {
    $vdHeaderDetail = hr_nacti_vd_detail($db, (int)$_GET['id_vd']);
    if (is_array($vdHeaderDetail)) {
        $pageTitle = 'Náborový proces: ' . (string)$vdHeaderDetail['cele_jmeno'];
    }
}
cb_hr_request_dispatch($db, $page, $cbUser);

$formResult = $_SESSION['cb_form_result'] ?? null;
unset($_SESSION['cb_form_result']);
$flash = is_array($formResult)
    ? ['type' => !empty($formResult['success']) ? 'hr_success' : 'hr_error', 'text' => (string)($formResult['message'] ?? '')]
    : ($_SESSION['hr_flash'] ?? null);
unset($_SESSION['hr_flash']);

$cbHrPageDefinition = is_array($currentPage['definition'] ?? null) ? $currentPage['definition'] : [];
$cbHrUsesPpRenderer = is_array($cbHrPageDefinition['blocks'] ?? null) && $cbHrPageDefinition['blocks'] !== [];

?>
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/hr_includes/hr_menu.php'; ?>
<?php endif; ?>

<?php if ($page === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php elseif ($cbHrUsesPpRenderer): ?>
    <?php
    require_once __DIR__ . '/../common/includes/pp_renderer.php';
    require_once __DIR__ . '/hr_lib/hr_page_context.php';

    $cbHrPpPage = $cbHrPageDefinition;
    $cbHrPpPage['module'] = 'hr';
    $cbHrPpPage['key'] = $page;
    $cbHrPpPage['title'] = $pageTitle;

    $cbHrPpContext = hr_page_context($cbHrPageDefinition, $db);
    $cbHrPpFlash = hr_page_flash(is_array($flash) ? $flash : null);
    if ($cbHrPpFlash !== null) {
        $cbHrPpContext['flash'] = $cbHrPpFlash;
    }

    cb_render_pp($cbHrPpPage, $cbHrPpContext);
    ?>
<?php else: ?>
<section class="pp hr_pp" data-module="hr" data-page="<?= h($page) ?>">
    <header class="pp_header">
        <?php if ($page === 'zamestnanec'): ?>
            <a class="hr_panel_link" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanci')) ?>">← Zpět na seznam zaměstnanců</a>
            <?php if (is_array($hrEmployeeHeader)): ?>
                <div class="hr_employee_profile_actions">
                    <?php if (isset($_GET['upravit']) && (string)$_GET['upravit'] === '1'): ?>
                        <a class="hr_secondary_button hr_panel_button_secondary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$hrEmployeeHeader['id_person']))) ?>">Zrušit úpravy</a>
                    <?php else: ?>
                        <a class="hr_primary_button hr_panel_button_primary" href="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$hrEmployeeHeader['id_person']) . '&upravit=1')) ?>">Upravit zaměstnance</a>
                    <?php endif; ?>
                    <?php if ((int)($hrEmployeeHeader['overen'] ?? 0) === 0): ?>
                        <form class="hr_row_action_form" method="post" action="<?= h(cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$hrEmployeeHeader['id_person']))) ?>">
                            <input type="hidden" name="cb_action" value="hr_zamestnanec_overit">
                            <input type="hidden" name="id_person" value="<?= h((string)$hrEmployeeHeader['id_person']) ?>">
                            <button class="hr_secondary_button hr_panel_button_secondary hr_danger_button" type="submit">Ověřit údaje zaměstnance</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <h1><?= h($pageTitle) ?></h1>
        <?php endif; ?>
        <?php if ($isNaborDetail && isset($vdHeaderDetail) && is_array($vdHeaderDetail)): ?>
            <div class="pp_header_control hr_vd_header_actions">
                <span class="hr_muted">VD č. <?= h((string)$vdHeaderDetail['id_vd']) ?> - <strong class="hr_vd_header_status"><?= h((string)$vdHeaderDetail['stav_nazev']) ?></strong></span>
                <a class="hr_vd_close_detail" href="<?= h(cb_root_url('index.php?m=hr&page=nabor')) ?>" aria-label="Zavřít detail" title="Zavřít detail">×</a>
            </div>
        <?php endif; ?>
    </header>
    <main class="hr_content">
        <?php if (is_array($flash) && isset($flash['text'])): ?>
            <div class="hr_notice <?= h((string)($flash['type'] ?? 'hr_info')) ?>"><?= h((string)$flash['text']) ?></div>
        <?php endif; ?>
        <?php require $currentPage['file']; ?>
    </main>
</section>
<?php endif; ?>
