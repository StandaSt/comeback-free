<?php
declare(strict_types=1);

$menuItems = [
    ['page' => 'dashboard', 'label' => 'Přehled', 'icon' => '⌂'],
    ['page' => 'nabor', 'label' => 'Nábor', 'icon' => '＋'],
    ['page' => 'zamestnanci', 'label' => 'Zaměstnanci', 'icon' => '👥'],
    ['page' => 'pozadavky', 'label' => 'Požadavky', 'icon' => '▤'],
    ['page' => 'pracovni_pomery', 'label' => 'Pracovní poměry', 'icon' => '▣'],
    ['page' => 'dokumenty', 'label' => 'Dokumenty', 'icon' => '▤'],
    ['page' => 'skoleni', 'label' => 'Školení', 'icon' => '◇'],
    ['page' => 'prohlidky', 'label' => 'Lékařské prohlídky', 'icon' => '♡'],
    ['page' => 'dovolene', 'label' => 'Dovolené', 'icon' => '▦'],
    ['page' => 'reporty', 'label' => 'Reporty', 'icon' => '▥'],
];
?>
<aside class="module_menu" aria-label="Menu personalistiky">
    <h2 class="module_menu_title">Personalistika</h2>

    <nav class="module_menu_list" aria-label="Hlavní menu">
        <?php foreach ($menuItems as $item): ?>
            <a
                class="module_menu_btn<?= $page === $item['page'] ? ' is-active' : '' ?>"
                href="<?= h(cb_root_url('index.php?m=hr&page=' . rawurlencode((string)$item['page']))) ?>"
            >
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="module_menu_list">
        <a class="module_menu_btn<?= $page === 'nastaveni' ? ' is-active' : '' ?>" href="<?= h(cb_root_url('index.php?m=hr&page=nastaveni')) ?>">
            <span>Nastavení</span>
        </a>
    </div>
</aside>
