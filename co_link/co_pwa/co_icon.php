<?php
declare(strict_types=1);
/* Co-Link: lokalne vykreslena jednoducha ikona spojeni, bez externich sluzeb a fontu. */
$size=(int)($_GET['size']??192)===512?512:192;
if(!function_exists('imagecreatetruecolor')) { http_response_code(503); exit; }
$im=imagecreatetruecolor($size,$size);
$bg=imagecolorallocate($im,18,61,69); $fg=imagecolorallocate($im,217,240,219);
imagefill($im,0,0,$bg);
imagesetthickness($im,max(6,(int)($size*.065)));
imagearc($im,(int)($size*.41),(int)($size*.44),(int)($size*.34),(int)($size*.34),45,320,$fg);
imagearc($im,(int)($size*.59),(int)($size*.56),(int)($size*.34),(int)($size*.34),225,140,$fg);
imageline($im,(int)($size*.43),(int)($size*.57),(int)($size*.57),(int)($size*.43),$fg);
header('Content-Type: image/png'); header('Cache-Control: public,max-age=86400'); imagepng($im); imagedestroy($im);
