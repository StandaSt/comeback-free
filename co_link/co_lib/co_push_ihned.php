<?php
/* Co-Link: spusti push po ulozeni zpravy a izoluje chybu odesilani od odpovedi API. */
declare(strict_types=1);
require_once __DIR__ . '/co_push_priprava.php';
require_once __DIR__ . '/co_push_zprava.php';

/** Volani po commitu neposkodi ulozenou zpravu ani uspesnou odpoved API pri chybe transportu. */
function co_push_ihned(int $message): void
{
    try {
        if (!co_push_priprava()) return;
        co_push_zprava('https://comebacks.cz/co_link/', $message);
    } catch (Throwable $error) {
        error_log('Co-Link push zprava '.$message.': '.get_class($error).' / '.$error->getCode());
    }
}
