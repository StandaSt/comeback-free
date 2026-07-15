<?php
declare(strict_types=1);

$menuItems = [
    ['page' => 'dashboard', 'label' => 'Přehled', 'icon' => '⌂'],
    ['page' => 'nabor', 'label' => 'Nábor', 'icon' => '＋'],
    ['page' => 'zamestnanci', 'label' => 'Zaměstnanci', 'icon' => '👥'],
    ['page' => 'pracovni_pomery', 'label' => 'Pracovní poměry', 'icon' => '▣'],
    ['page' => 'dokumenty', 'label' => 'Dokumenty', 'icon' => '▤'],
    ['page' => 'skoleni', 'label' => 'Školení', 'icon' => '◇'],
    ['page' => 'prohlidky', 'label' => 'Lékařské prohlídky', 'icon' => '♡'],
    ['page' => 'dovolene', 'label' => 'Dovolené', 'icon' => '▦'],
    ['page' => 'reporty', 'label' => 'Reporty', 'icon' => '▥'],
];
?>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-mark">C</div>
        <div>
            <strong>COMEBACK</strong>
            <span>HR</span>
        </div>
    </div>

    <nav class="nav" aria-label="Hlavní menu">
        <?php foreach ($menuItems as $item): ?>
            <a
                class="nav-link<?= $page === $item['page'] ? ' active' : '' ?>"
                href="?page=<?= h($item['page']) ?>"
            >
                <span class="nav-icon" aria-hidden="true"><?= h($item['icon']) ?></span>
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-section">
        <a class="nav-link<?= $page === 'nastaveni' ? ' active' : '' ?>" href="?page=nastaveni">
            <span class="nav-icon" aria-hidden="true">⚙</span>
            <span>Nastavení</span>
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-label">MODULY</div>
        <a class="nav-link" href="<?= h(cb_module_url('is')) ?>">
            <span class="nav-icon" aria-hidden="true">▦</span>
            <span>IS</span>
        </a>
        <a class="nav-link" href="<?= h(cb_module_url('smeny')) ?>">
            <span class="nav-icon" aria-hidden="true">▧</span>
            <span>Směny</span>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="avatar"><?= h(mb_strtoupper(mb_substr($userName, 0, 1))) ?></div>
        <div class="sidebar-user-text">
            <strong><?= h($userName) ?></strong>
            <span><?= h($userRole) ?></span>
        </div>
    </div>
</aside>
