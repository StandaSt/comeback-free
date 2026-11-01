<?php
/* Co-Link: overi pristup a slozi stranku nejvyse 80 zprav s presnou informaci o starsich zpravach. */
declare(strict_types=1);
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_zpravy_filtr.php';
require_once __DIR__ . '/co_zpravy_prilohy.php';
require_once __DIR__ . '/co_zpravy_reakce.php';
require_once __DIR__ . '/../co_db/co_zpravy_nacist.php';
require_once __DIR__ . '/../co_db/co_zpravy_prilohy_nacist.php';
require_once __DIR__ . '/../co_db/co_zpravy_reakce_nacist.php';

/** Zachovava poradi, metadata i pravidla smazanych zprav; dalsi stranka musi mit skutecny zaznam. */
function co_zpravy(int $user, int $conversation, int $before = 0, string $search = '', int $around = 0, int $thread = 0): array
{
    $channel = co_konverzace($user, $conversation);
    $filter = co_zpravy_filtr($conversation, $before, $search, $around, $thread);
    $messages = co_zpravy_nacist($user, $filter['where'], $filter['params']);
    // Z nejvyse 81 radku ponechame nejnovejsich 80. Posledni slouzi jen jako dukaz dalsi stranky.
    $more = count($messages) > 80;
    $messages = array_reverse(array_slice($messages, 0, 80));
    $ids = array_map('intval', array_column($messages, 'id'));
    $files = co_zpravy_prilohy(co_zpravy_prilohy_nacist($ids));
    $reactions = co_zpravy_reakce(co_zpravy_reakce_nacist($user, $ids));
    foreach ($messages as &$message) {
        // Smazana zprava nesmi vydat puvodni text, prilohy ani reakce.
        $message['prilohy'] = $message['smazano'] === null ? ($files[(int)$message['id']] ?? []) : [];
        $message['reakce'] = $message['smazano'] === null ? ($reactions[(int)$message['id']] ?? []) : [];
        if ($message['smazano'] !== null) $message['text'] = '';
        $message['lze_menit'] = (int)$message['autor'] === $user && $message['prvni_precteni'] === null && $message['smazano'] === null;
    }
    unset($message);
    return ['kanal' => $channel, 'zpravy' => $messages, 'dalsi' => $more];
}
