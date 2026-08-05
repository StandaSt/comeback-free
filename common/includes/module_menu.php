<?php
declare(strict_types=1);

if (!function_exists('cb_module_menu_h')) {
    function cb_module_menu_h(string $value): string
    {
        if (function_exists('h')) {
            return h($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cb_render_module_menu')) {
    function cb_render_module_menu(array $menu): void
    {
        $title = (string)($menu['title'] ?? '');
        $ariaLabel = (string)($menu['aria_label'] ?? $title);
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        ?>
        <nav class="module_menu" aria-label="<?= cb_module_menu_h($ariaLabel) ?>">
            <?php if ($title !== ''): ?>
                <h2 class="module_menu_title"><?= cb_module_menu_h($title) ?></h2>
            <?php endif; ?>

            <ul class="module_menu_list">
                <?php foreach ($items as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }
                    $label = (string)($item['label'] ?? '');
                    if ($label === '') {
                        continue;
                    }
                    $url = trim((string)($item['url'] ?? ''));
                    $children = is_array($item['items'] ?? null) ? $item['items'] : [];
                    $isActive = !empty($item['active']);
                    $hasChildren = $children !== [];
                    $buttonClass = 'module_menu_btn' . ($isActive ? ' is-active' : '');
                    $toggleAttr = $hasChildren
                        ? ' onclick="var i=this.closest(\'.module_menu_item\');var o=i.classList.contains(\'is-open\');this.closest(\'.module_menu\').querySelectorAll(\'.module_menu_item.is-open\').forEach(function(x){x.classList.remove(\'is-open\');});if(!o){i.classList.add(\'is-open\');}"'
                        : '';
                    ?>
                    <li class="module_menu_item">
                        <?php if ($url !== ''): ?>
                            <a class="<?= cb_module_menu_h($buttonClass) ?>" href="<?= cb_module_menu_h($url) ?>">
                                <span><?= cb_module_menu_h($label) ?></span>
                                <?php if ($hasChildren): ?>
                                    <span class="module_menu_chev" aria-hidden="true">⌄</span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="<?= cb_module_menu_h($buttonClass) ?>"<?= $toggleAttr ?>>
                                <span><?= cb_module_menu_h($label) ?></span>
                                <?php if ($hasChildren): ?>
                                    <span class="module_menu_chev" aria-hidden="true">⌄</span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($hasChildren): ?>
                            <ul class="module_submenu">
                                <?php foreach ($children as $child): ?>
                                    <?php
                                    $childLabel = is_array($child) ? (string)($child['label'] ?? '') : (string)$child;
                                    $childUrl = is_array($child) ? trim((string)($child['url'] ?? '')) : '';
                                    if ($childLabel === '') {
                                        continue;
                                    }
                                    ?>
                                    <li>
                                        <?php if ($childUrl !== ''): ?>
                                            <a class="module_submenu_btn" href="<?= cb_module_menu_h($childUrl) ?>"><?= cb_module_menu_h($childLabel) ?></a>
                                        <?php else: ?>
                                            <button type="button" class="module_submenu_btn"><?= cb_module_menu_h($childLabel) ?></button>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php
    }
}
