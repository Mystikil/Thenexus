<?php

declare(strict_types=1);

function nx_redirect(string $url, int $code = 302): void
{
    if (!headers_sent()) {
        header("Location: $url", true, $code);
        exit;
    }

    echo '<script>location.replace(' . json_encode($url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></noscript>';
    exit;
}
