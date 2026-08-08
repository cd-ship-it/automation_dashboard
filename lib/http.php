<?php

declare(strict_types=1);

/**
 * Prevent SiteGround / browser / CDN from serving stale log responses.
 */
function send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
