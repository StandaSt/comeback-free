<?php
/* Co-Link: vraci puvodni neveřejne uloziste priloh mimo www. */
declare(strict_types=1);

/** Souborove uloziste lezi mimo verejny web, nikdy nepouziva uzivatelsky nazev jako cestu. */
function co_uloziste(): string { return dirname(__DIR__,3) . '/data/co_link/prilohy'; }
