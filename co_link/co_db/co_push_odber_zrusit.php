<?php
/* Co-Link: odstrani neplatny odber pouze pri shode puvodniho endpointu. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_odber_zrusit(int $device, string $endpoint): void
{
    co_sql('UPDATE co_zarizeni SET endpoint=NULL,klic_public=NULL,klic_auth=NULL WHERE id=? AND endpoint=?', [$device, $endpoint])->close();
}
