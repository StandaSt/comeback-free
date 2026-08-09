<?php
declare(strict_types=1);

if (!function_exists('cb_blok_menu_h')) {
    function cb_blok_menu_h(string $value): string
    {
        if (function_exists('h')) {
            return h($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cb_render_blok_menu_user')) {
    function cb_render_blok_menu_user(): void
    {
        $user = $_SESSION['cb_user'] ?? [];
        $userName = 'Uzivatel';
        $userRole = '-';

        if (is_array($user)) {
            $fullName = trim((string)($user['name'] ?? '') . ' ' . (string)($user['surname'] ?? ''));
            if ($fullName !== '') {
                $userName = $fullName;
            } else {
                $userName = (string)($user['jmeno'] ?? $user['email'] ?? $user['login'] ?? $userName);
            }

            $userRole = (string)($user['role'] ?? $user['nazev_role'] ?? $userRole);
        }

        $timeoutMin = (int)($_SESSION['cb_timeout_min'] ?? 720);
        if ($timeoutMin <= 0) {
            $timeoutMin = 20;
        }

        $nowTs = time();
        $startTs = (int)($_SESSION['cb_session_start_ts'] ?? $nowTs);
        $lastTs = (int)($_SESSION['cb_last_activity_ts'] ?? $nowTs);
        if ($startTs <= 0 || $startTs > $nowTs) {
            $startTs = $nowTs;
        }
        if ($lastTs <= 0 || $lastTs > $nowTs || $lastTs < $startTs) {
            $lastTs = $nowTs;
        }

        $postUrl = function_exists('cb_root_url') ? cb_root_url('index.php') : 'index.php';
        $settingsUrl = function_exists('cb_root_url') ? cb_root_url('index.php?m=provoz&page=nastaveni') : 'index.php?m=provoz&page=nastaveni';
        $themeLevel = function_exists('cb_user_setting') ? max(0, min(6, (int)cb_user_setting('dark', 0))) : 0;
        $themeModule = defined('CB_EMBEDDED_MODULE') ? (string)constant('CB_EMBEDDED_MODULE') : (string)($GLOBALS['CURRENT_MODULE'] ?? 'provoz');
        if (!in_array($themeModule, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'], true)) {
            $themeModule = 'provoz';
        }
        $themeReturn = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($themeReturn === '' || str_starts_with($themeReturn, '//') || preg_match('~^[a-z][a-z0-9+.-]*:~i', $themeReturn) === 1) {
            $themeReturn = function_exists('cb_root_url') ? cb_root_url('index.php?m=' . rawurlencode($themeModule)) : 'index.php?m=' . rawurlencode($themeModule);
        }
        ?>
        <div class="blok_menu_user"
             data-timeout-min="<?= cb_blok_menu_h((string)$timeoutMin) ?>"
             data-start-ts="<?= cb_blok_menu_h((string)$startTs) ?>"
             data-last-ts="<?= cb_blok_menu_h((string)$lastTs) ?>"
             data-logout-url="<?= cb_blok_menu_h($postUrl . '?action=logout&duvod=0') ?>"
             data-touch-url="<?= cb_blok_menu_h($postUrl) ?>">
            <div class="blok_menu_user_name"><?= cb_blok_menu_h($userName) ?></div>
            <div class="blok_menu_user_role"><?= cb_blok_menu_h($userRole) ?></div>
            <div class="blok_menu_user_settings">
                <a class="blok_menu_user_action" href="<?= cb_blok_menu_h($settingsUrl) ?>">Nastavení</a>
                <form class="blok_menu_theme_form" method="post" action="<?= cb_blok_menu_h($postUrl) ?>" data-cb-theme-form="1" data-theme-level="<?= cb_blok_menu_h((string)$themeLevel) ?>">
                    <input type="hidden" name="cb_theme_module" value="<?= cb_blok_menu_h($themeModule) ?>">
                    <input type="hidden" name="cb_theme_return" value="<?= cb_blok_menu_h($themeReturn) ?>">
                    <button class="blok_menu_theme_btn" type="submit" name="cb_theme_delta" value="-1" aria-label="Zesvětlit" data-cb-theme-delta="-1"<?= $themeLevel <= 0 ? ' disabled' : '' ?>>-</button>
                    <span class="blok_menu_theme_value" data-cb-theme-value="1"><?= cb_blok_menu_h((string)$themeLevel) ?></span>
                    <button class="blok_menu_theme_btn" type="submit" name="cb_theme_delta" value="1" aria-label="Ztmavit" data-cb-theme-delta="1"<?= $themeLevel >= 6 ? ' disabled' : '' ?>>+</button>
                </form>
            </div>
            <a class="blok_menu_user_action blok_menu_user_logout" href="<?= cb_blok_menu_h($postUrl . '?action=logout&duvod=1') ?>">Odhlásit</a>
        </div>
        <?php
    }
}

if (!function_exists('cb_render_blok_menu')) {
    function cb_render_blok_menu(array $menu): void
    {
        $title = (string)($menu['title'] ?? '');
        $ariaLabel = (string)($menu['aria_label'] ?? $title);
        $items = is_array($menu['items'] ?? null) ? $menu['items'] : [];
        ?>
        <nav class="blok_menu" aria-label="<?= cb_blok_menu_h($ariaLabel) ?>">
            <?php if ($title !== ''): ?>
                <h2 class="blok_menu_title"><?= cb_blok_menu_h($title) ?></h2>
            <?php endif; ?>

            <ul class="blok_menu_list">
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
                    $buttonClass = 'blok_menu_btn' . ($isActive ? ' is-active' : '');
                    $toggleAttr = $hasChildren
                        ? ' onclick="var i=this.closest(\'.blok_menu_item\');var m=this.closest(\'.blok_menu\');var s=i.querySelector(\'.blok_submenu\');var c=this.querySelector(\'.blok_menu_chev\');var o=s&&s.classList.contains(\'blok_submenu_open\');m.querySelectorAll(\'.blok_menu_item\').forEach(function(x){x.classList.remove(\'is-open\');var xs=x.querySelector(\'.blok_submenu\');var xc=x.querySelector(\'.blok_menu_chev\');if(xs){xs.classList.remove(\'blok_submenu_open\');}if(xc){xc.classList.remove(\'blok_menu_chev_open\');}});if(!o){i.classList.add(\'is-open\');if(s){s.classList.add(\'blok_submenu_open\');}if(c){c.classList.add(\'blok_menu_chev_open\');}}"'
                        : '';
                    ?>
                    <li class="blok_menu_item">
                        <?php if ($url !== ''): ?>
                            <a class="<?= cb_blok_menu_h($buttonClass) ?>" href="<?= cb_blok_menu_h($url) ?>">
                                <span><?= cb_blok_menu_h($label) ?></span>
                                <?php if ($hasChildren): ?>
                                    <span class="blok_menu_chev" aria-hidden="true">⌄</span>
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <button type="button" class="<?= cb_blok_menu_h($buttonClass) ?>"<?= $toggleAttr ?>>
                                <span><?= cb_blok_menu_h($label) ?></span>
                                <?php if ($hasChildren): ?>
                                    <span class="blok_menu_chev" aria-hidden="true">⌄</span>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($hasChildren): ?>
                            <ul class="blok_submenu">
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
                                            <a class="blok_submenu_btn" href="<?= cb_blok_menu_h($childUrl) ?>"><?= cb_blok_menu_h($childLabel) ?></a>
                                        <?php else: ?>
                                            <button type="button" class="blok_submenu_btn"><?= cb_blok_menu_h($childLabel) ?></button>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php cb_render_blok_menu_user(); ?>
        </nav>
        <?php
    }
}
