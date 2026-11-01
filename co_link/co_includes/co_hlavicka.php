<?php
/* Co-Link: metadata, titulek, ikony a manifest komunikatoru. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#123d45"><meta name="apple-mobile-web-app-capable" content="yes">
<title>Co-Link · Comeback</title>
<link rel="manifest" href="co_pwa/co_manifest.php"><link rel="icon" href="co_pwa/co_icon.php?size=192"><link rel="apple-touch-icon" href="co_pwa/co_icon.php?size=192">
