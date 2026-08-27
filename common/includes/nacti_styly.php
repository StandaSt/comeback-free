<?php
/*
 * Ucel souboru: Vypise CSS odkazy do head spolecne HTML kostry.
 * Nacita globalni vzhled i styly jednotlivych modulu ve stejnem poradi jako dosud.
 */
declare(strict_types=1);
?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($cbTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= h($cbFavicon) ?>">
  <?php // Globalni styl aplikace. ?>
  <link rel="stylesheet" href="<?= h(cb_asset_url('style/global.css')) ?>">
  <?php // Globalni styl modalnich oken. ?>
  <link rel="stylesheet" href="<?= h(cb_asset_url('style/modal_alert.css')) ?>">
  <?php // Styl modulu Provoz. ?>
  <link rel="stylesheet" href="<?= h(cb_asset_url('style/provoz.css')) ?>">
  <?php // Styly modulu HR. ?>
  <link rel="stylesheet" href="<?= h($cbHrCssUrl) ?>">
  <?php // Styly modulu Smeny. ?>
  <link rel="stylesheet" href="<?= h($cbSmenyCssUrl) ?>">
  <?php // Styly modulu Ukoly. ?>
  <link rel="stylesheet" href="<?= h($cbUkolyCssUrl) ?>">
  <?php // Styly modulu Helpdesk. ?>
  <link rel="stylesheet" href="<?= h($cbHelpdeskCssUrl) ?>">
  <?php // Styly modulu Administrace. ?>
  <link rel="stylesheet" href="<?= h($cbAdministraceCssUrl) ?>">
  <?php // Styl spolecneho loaderu. ?>
  <link rel="stylesheet" href="<?= h($cbLoaderCssUrl) ?>">
</head>
