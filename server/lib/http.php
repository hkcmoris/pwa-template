<?php
/**
 * HTTP helper utilities for consistent caching headers.
 */
function disable_response_cache(): void
{
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function enable_micro_cache(): void
{
    header('Cache-Control: s-maxage=5, stale-while-revalidate=60');
}

