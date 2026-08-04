<?php
// includes/hlavicka/head_user.php * Verze: V3 * Aktualizace: 07.03.2026
declare(strict_types=1);

$cbUserInitials = '';
$cbInitialSource = trim((string)($cbUserName ?? ''));
if ($cbInitialSource !== '') {
    $cbInitialParts = preg_split('~\s+~u', $cbInitialSource) ?: [];
    foreach ($cbInitialParts as $cbInitialPart) {
        $cbInitialPart = trim((string)$cbInitialPart);
        if ($cbInitialPart === '') {
            continue;
        }
        $cbUserInitials .= mb_strtoupper(mb_substr($cbInitialPart, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($cbUserInitials, 'UTF-8') >= 2) {
            break;
        }
    }
}
if ($cbUserInitials === '') {
    $cbUserInitials = 'U';
}
?>
<div class="head_user"
     data-timeout-min="<?= h((string)$cbTimeoutMin) ?>"
     data-start-ts="<?= h((string)$cbStartTs) ?>"
     data-last-ts="<?= h((string)$cbLastTs) ?>"
     data-logout-url="<?= h($cbProvozPostUrl . '?action=logout&duvod=0') ?>"
     data-touch-url="<?= h($cbProvozPostUrl) ?>">

  <div class="head_user_avatar" aria-hidden="true"><?= h($cbUserInitials) ?></div>
  <div class="head_user_main">
    <strong class="head_user_name"><?= h($cbUserName) ?></strong>
    <span class="head_user_role"><?= h($cbUserRoleLabel) ?></span>
  </div>

  <a class="head_user_exit" href="<?= h($cbProvozPostUrl . '?action=logout&duvod=1') ?>" aria-label="Odhlásit">
    <svg class="head_user_exit_ico displ_block" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
  </a>
</div>
