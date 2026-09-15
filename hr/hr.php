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
$mzdovyFirmaNazev = '';
$cbHrIdUser = is_array($cbUser) ? (int)($cbUser['id_user'] ?? 0) : 0;
if ($page === 'mzdovy_prehled') {
    require_once __DIR__ . '/hr_lib/hr_mzdovy_prehled_data.php';
    $mzdovyFirmaNazev = hr_mzdovy_prehled_nazev_firmy($db, $cbHrIdUser);
}
if (in_array($page, ['zamestnanci', 'zamestnanec'], true) && !cb_pravo_ma(306)) {
    http_response_code(403);
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}
if ($page === 'novy_zamestnanec' && !cb_pravo_ma(305)) {
    http_response_code(403);
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}
if ($page === 'mzdovy_prehled' && !cb_hr_mzdovy_prehled_ma_pravo()) {
    http_response_code(403);
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}
if ($page === 'nastaveni' && !cb_hr_nastaveni_ma_pravo()) {
    http_response_code(403);
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}
if ($page === 'mzdovy_prehled') {
    try {
        $mzdovyPrehled = hr_mzdovy_prehled_data($db, $_GET, $cbHrIdUser);
        $mzdovyError = '';
    } catch (Throwable $error) {
        $mzdovyPrehled = [
            'period' => '', 'period_year' => 0, 'period_month' => 0, 'period_years' => [], 'period_months' => [], 'period_label' => '',
            'branches' => [], 'position_options' => [], 'workload_options' => [],
            'filters' => ['id' => '', 'jmeno' => '', 'prijmeni' => '', 'pobocka' => '', 'pozice' => '', 'uvazek' => ''],
            'active_filters' => [], 'sort' => 'prijmeni', 'dir' => 'asc', 'per_options' => [20, 50, 100, 500],
            'per_page' => 100, 'page_num' => 1, 'total_rows' => 0, 'total_pages' => 1, 'first_row' => 0,
            'last_row' => 0, 'rows' => [], 'total_hours' => 0.0,
        ];
        $mzdovyError = 'Mzdový přehled nyní nelze načíst.';
    }
}
if ($page === 'zamestnanec' && (int)($_GET['id'] ?? 0) > 0 && !cb_firemni_pristup_muze_osobu($db, $cbHrIdUser, (int)$_GET['id'])) {
    http_response_code(403);
    require __DIR__ . '/hr_includes/pripravujeme.php';
    exit;
}
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

$hrEmployeeFromPayroll = $page === 'zamestnanec' && (string)($_GET['from'] ?? '') === 'mzdovy_prehled';
$hrEmployeeBackLabel = 'Zpět na seznam zaměstnanců';
$hrEmployeeBackUrl = cb_root_url('index.php?m=hr&page=zamestnanci');
if ($hrEmployeeFromPayroll) {
    $hrEmployeeBackLabel = 'Zpět na mzdový přehled';
    $hrEmployeeBackParams = ['m' => 'hr', 'page' => 'mzdovy_prehled'];
    $hrEmployeePayrollFilters = [];
    if (is_array($_GET['mzd_f'] ?? null)) {
        foreach (['id', 'jmeno', 'prijmeni', 'pobocka', 'pozice', 'uvazek'] as $filterKey) {
            $filterValue = $_GET['mzd_f'][$filterKey] ?? '';
            if (!is_scalar($filterValue)) {
                continue;
            }
            $filterValue = mb_substr(trim((string)$filterValue), 0, 100);
            if ($filterValue !== '') {
                $hrEmployeePayrollFilters[$filterKey] = $filterValue;
            }
        }
    }
    if ($hrEmployeePayrollFilters !== []) {
        $hrEmployeeBackParams['mzd_f'] = $hrEmployeePayrollFilters;
    }
    $hrEmployeePayrollKeys = ['mzd_mesic', 'mzd_rok', 'mzd_obdobi', 'mzd_id', 'mzd_jmeno', 'mzd_prijmeni', 'mzd_pobocka', 'mzd_pozice', 'mzd_uvazek', 'mzd_sort', 'mzd_dir', 'mzd_per', 'mzd_p'];
    foreach ($hrEmployeePayrollKeys as $key) {
        if (!isset($_GET[$key]) || !is_scalar($_GET[$key])) {
            continue;
        }
        $value = mb_substr(trim((string)$_GET[$key]), 0, 100);
        if ($value !== '') {
            $hrEmployeeBackParams[$key] = $value;
        }
    }
    $hrEmployeeBackUrl = cb_root_url('index.php?' . http_build_query($hrEmployeeBackParams));
}

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
            <a class="hr_panel_link" href="<?= h($hrEmployeeBackUrl) ?>">← <?= h($hrEmployeeBackLabel) ?></a>
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
            <?php if ($page === 'mzdovy_prehled'): ?>
                <?php $mzdovyMesice = [1 => 'leden', 2 => 'únor', 3 => 'březen', 4 => 'duben', 5 => 'květen', 6 => 'červen', 7 => 'červenec', 8 => 'srpen', 9 => 'září', 10 => 'říjen', 11 => 'listopad', 12 => 'prosinec']; ?>
                <div class="hr_mzdovy_header_title">
                    <h1><?= h($pageTitle) ?></h1>
                    <div class="hr_mzdovy_controls">
                        <label class="hr_mzdovy_period hr_mzdovy_period--month" for="mzd_mesic">Měsíc <select id="mzd_mesic" class="filter-input" form="hr-mzdovy-filter-form" name="mzd_mesic" data-cb-filter-refresh-options="1"><?php foreach ($mzdovyPrehled['period_months'] as $monthNumber): ?><option value="<?= h(sprintf('%02d', $monthNumber)) ?>"<?= (int)$mzdovyPrehled['period_month'] === $monthNumber ? ' selected' : '' ?>><?= h($mzdovyMesice[$monthNumber]) ?></option><?php endforeach; ?></select></label>
                        <label class="hr_mzdovy_period hr_mzdovy_period--year" for="mzd_rok">Rok <select id="mzd_rok" class="filter-input" form="hr-mzdovy-filter-form" name="mzd_rok"><?php foreach ($mzdovyPrehled['period_years'] as $year): ?><option value="<?= h((string)$year) ?>"<?= (int)$mzdovyPrehled['period_year'] === (int)$year ? ' selected' : '' ?>><?= h((string)$year) ?></option><?php endforeach; ?></select></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--id" for="mzd_id">ID <input id="mzd_id" class="filter-input" form="hr-mzdovy-filter-form" type="search" name="mzd_f[id]" value="<?= h((string)$mzdovyPrehled['filters']['id']) ?>"></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--name" for="mzd_jmeno">Jméno <input id="mzd_jmeno" class="filter-input" form="hr-mzdovy-filter-form" type="search" name="mzd_f[jmeno]" value="<?= h((string)$mzdovyPrehled['filters']['jmeno']) ?>"></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--surname" for="mzd_prijmeni">Příjmení <input id="mzd_prijmeni" class="filter-input" form="hr-mzdovy-filter-form" type="search" name="mzd_f[prijmeni]" value="<?= h((string)$mzdovyPrehled['filters']['prijmeni']) ?>"></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--branch" for="mzd_pobocka">Pobočka <select id="mzd_pobocka" class="filter-input" form="hr-mzdovy-filter-form" name="mzd_f[pobocka]"><option value="">Vše</option><?php foreach ($mzdovyPrehled['branches'] as $branch): ?><option value="<?= h((string)$branch['id_pob']) ?>"<?= $mzdovyPrehled['filters']['pobocka'] === (string)$branch['id_pob'] ? ' selected' : '' ?>><?= h((string)$branch['name']) ?></option><?php endforeach; ?></select></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--position" for="mzd_pozice">Pozice <select id="mzd_pozice" class="filter-input" form="hr-mzdovy-filter-form" name="mzd_f[pozice]"><option value="">Vše</option><?php foreach ($mzdovyPrehled['position_options'] as $option): ?><option value="<?= h($option) ?>"<?= $mzdovyPrehled['filters']['pozice'] === $option ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
                        <label class="hr_mzdovy_filter hr_mzdovy_filter--workload" for="mzd_uvazek">Úvazek <select id="mzd_uvazek" class="filter-input" form="hr-mzdovy-filter-form" name="mzd_f[uvazek]"><option value="">Vše</option><?php foreach ($mzdovyPrehled['workload_options'] as $option): ?><option value="<?= h($option) ?>"<?= $mzdovyPrehled['filters']['uvazek'] === $option ? ' selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
                    </div>
                </div>
            <?php else: ?>
                <h1><?= h($pageTitle) ?></h1>
            <?php endif; ?>
            <?php if ($mzdovyFirmaNazev !== ''): ?>
                <div class="pp_header_control"><span class="hr_muted hr_mzdovy_company"><?= h($mzdovyFirmaNazev) ?></span></div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($isNaborDetail && isset($vdHeaderDetail) && is_array($vdHeaderDetail)): ?>
            <div class="pp_header_control hr_vd_header_actions">
                <span class="hr_muted">VD č. <?= h((string)$vdHeaderDetail['id_vd']) ?> - <strong class="hr_vd_header_status"><?= h((string)$vdHeaderDetail['stav_nazev']) ?><?php if ((int)$vdHeaderDetail['id_vd_stav'] === HR_VD_STAV_POHOVOR_DOMLUVEN && trim((string)($vdHeaderDetail['pohovor_termin'] ?? '')) !== ''): ?>, <?= h(date('j. n. Y H:i', strtotime((string)$vdHeaderDetail['pohovor_termin']))) ?><?php endif; ?></strong></span>
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
