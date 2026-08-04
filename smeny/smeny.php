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

<section class="module_shell">
    <nav class="module_menu" aria-label="Menu směn">
        <h2 class="module_menu_title">Směny</h2>
        <ul class="module_menu_list">
            <?php foreach ($smMenu as $index => $section): ?>
                <?php
                $items = is_array($section['items']) ? $section['items'] : [];
                $isActive = $index === 0;
                ?>
                <li class="module_menu_item">
                    <button type="button" class="module_menu_btn<?= $isActive ? ' is-active' : '' ?>"<?= $items !== [] ? ' onclick="var i=this.closest(\'.module_menu_item\');var o=i.classList.contains(\'is-open\');this.closest(\'.module_menu\').querySelectorAll(\'.module_menu_item.is-open\').forEach(function(x){x.classList.remove(\'is-open\');});if(!o){i.classList.add(\'is-open\');}"' : '' ?>>
                        <span><?= h((string)$section['label']) ?></span>
                        <?php if ($items !== []): ?>
                            <span class="module_menu_chev" aria-hidden="true">⌄</span>
                        <?php endif; ?>
                    </button>
                    <?php if ($items !== []): ?>
                        <ul class="module_submenu">
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <button type="button" class="module_submenu_btn">
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
