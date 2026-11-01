<?php
declare(strict_types=1);
/* Co-Link: manifest v co_pwa zachovava puvodni identitu, start a rozsah aplikace v nadrazenem adresari. */
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode(['id'=>'../','name'=>'Co-Link','short_name'=>'Co-Link','lang'=>'cs','start_url'=>'../','scope'=>'../','display'=>'standalone',
    'background_color'=>'#f5f7f7','theme_color'=>'#123d45','description'=>'Firemní komunikace Comeback',
    'icons'=>[['src'=>'co_icon.php?size=192','sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'],['src'=>'co_icon.php?size=512','sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable']]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
