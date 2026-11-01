<?php
/* Co-Link: zjisti HTTPS vcetne puvodni podpory reverzni proxy. */
declare(strict_types=1);

/** Sdili overeni HTTPS s nastavenim cookie za reverzni proxy. */
function co_https(): bool { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'; }
