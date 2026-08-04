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

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'smeny';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Směny lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

cb_pobocky_bootstrap_session();

$smMenu = [
    [
        'label' => 'Přehled',
        'items' => [],
    ],
    [
        'label' => 'Požadavky',
        'items' => [],
    ],
    [
        'label' => 'Hodnocení',
        'items' => [],
    ],
    [
        'label' => 'Mé směny',
        'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2'],
    ],
    [
        'label' => 'Plánování směn',
        'items' => ['Aktuální týden', 'Týden + 1'],
    ],
    [
        'label' => 'Šablony',
        'items' => [],
    ],
    [
        'label' => 'Naplánované směny',
        'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2'],
    ],
    [
        'label' => 'Zadané požadavky',
        'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2', 'Historie'],
    ],
    [
        'label' => 'Administrace',
        'items' => [],
    ],
];

?><style>
.sm_module{
    display:grid;
    grid-template-columns:260px 1fr;
    gap:5px;
    min-height:calc(100vh - 72px);
    background:#fef3c7;
    color:#111827;
    font-family:Arial, Helvetica, sans-serif;
}

.sm_menu{
    background:#fff;
    border:1px solid #f59e0b;
    border-radius:6px;
    padding:8px;
}

.sm_menu_list{
    display:grid;
    gap:4px;
    margin:0;
    padding:0;
    list-style:none;
}

.sm_menu_btn{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    width:100%;
    min-height:32px;
    padding:6px 9px;
    border:1px solid #fed7aa;
    border-radius:5px;
    background:#fff7ed;
    color:#111827;
    font-size:14px;
    line-height:1.2;
    text-align:left;
    cursor:pointer;
}

.sm_menu_btn.is-active{
    border-color:#f97316;
    background:#fed7aa;
}

.sm_menu_chev{
    flex:0 0 auto;
    color:#9a3412;
    font-size:13px;
    line-height:1;
}

.sm_menu_item.is-open > .sm_menu_btn .sm_menu_chev{
    transform:rotate(180deg);
}

.sm_menu_item.is-open > .sm_menu_btn{
    font-weight:700;
}

.sm_submenu{
    display:none;
    gap:3px;
    margin:4px 0 2px;
    padding:0 0 0 14px;
    list-style:none;
}

.sm_menu_item.is-open > .sm_submenu{
    display:grid;
}

.sm_submenu_btn{
    display:flex;
    align-items:center;
    width:100%;
    min-height:27px;
    padding:5px 8px;
    border:1px solid #ffedd5;
    border-radius:5px;
    background:#fff;
    color:#374151;
    font-size:13px;
    line-height:1.2;
    text-align:left;
}

.sm_content{
    min-width:0;
    background:#fff;
    border:1px solid #f59e0b;
    border-radius:6px;
    padding:14px;
}

.sm_content_title{
    margin:0 0 6px;
    font-size:20px;
    line-height:1.2;
    font-weight:400;
}

.sm_content_text{
    margin:0;
    color:#4b5563;
    font-size:14px;
    line-height:1.4;
}
</style>

<section class="sm_module">
    <nav class="sm_menu" aria-label="Menu směn">
        <ul class="sm_menu_list">
            <?php foreach ($smMenu as $index => $section): ?>
                <?php
                $items = is_array($section['items']) ? $section['items'] : [];
                $isActive = $index === 0;
                ?>
                <li class="sm_menu_item">
                    <button type="button" class="sm_menu_btn<?= $isActive ? ' is-active' : '' ?>"<?= $items !== [] ? ' onclick="var i=this.closest(\'.sm_menu_item\');var o=i.classList.contains(\'is-open\');this.closest(\'.sm_menu\').querySelectorAll(\'.sm_menu_item.is-open\').forEach(function(x){x.classList.remove(\'is-open\');});if(!o){i.classList.add(\'is-open\');}"' : '' ?>>
                        <span><?= h((string)$section['label']) ?></span>
                        <?php if ($items !== []): ?>
                            <span class="sm_menu_chev" aria-hidden="true">⌄</span>
                        <?php endif; ?>
                    </button>
                    <?php if ($items !== []): ?>
                        <ul class="sm_submenu">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <button type="button" class="sm_submenu_btn">
                                        <?= h((string)$item) ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sm_content">
        <h1 class="sm_content_title">Přehled</h1>
        <p class="sm_content_text">Modul Směny je připravený pro další napojení obsahu.</p>
    </div>
</section>
