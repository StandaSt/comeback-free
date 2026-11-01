<?php
/* Co-Link: nacte styly v puvodnim poradi se zachovanim verzovani souboru. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<!-- Poradi komponent zachovava zaklad, obsah a nakonec kontext vlozeni do IS. -->
<link rel="stylesheet" href="co_style/co_zaklad.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_zaklad.css') ?>">
<link rel="stylesheet" href="co_style/co_rozlozeni.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_rozlozeni.css') ?>">
<link rel="stylesheet" href="co_style/co_navigace.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_navigace.css') ?>">
<link rel="stylesheet" href="co_style/co_zpravy.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_zpravy.css') ?>">
<link rel="stylesheet" href="co_style/co_psani.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_psani.css') ?>">
<link rel="stylesheet" href="co_style/co_uvitani.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_uvitani.css') ?>">
<link rel="stylesheet" href="co_style/co_dialogy.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_dialogy.css') ?>">
<link rel="stylesheet" href="co_style/co_adresar.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_adresar.css') ?>">
<link rel="stylesheet" href="co_style/co_is.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_is.css') ?>">
<link rel="stylesheet" href="co_style/co_editor.css?v=<?= filemtime(dirname(__DIR__).'/co_style/co_editor.css') ?>">
<link rel="stylesheet" href="../common/style/tooltip.css?v=<?= filemtime(dirname(__DIR__).'/../common/style/tooltip.css') ?>">
