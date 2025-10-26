<?php

declare(strict_types=1);

function tfs_password_hash(string $plain, string $type): string
{
    $t = strtolower(trim($type));

    if ($t === 'sha1') {
        return sha1($plain);
    }

    if ($t === 'md5') {
        return md5($plain);
    }

    if ($t === 'plain') {
        return $plain;
    }

    return sha1($plain);
}

function tfs_password_type(): string
{
    if (defined('TFS_PASSWORD_TYPE')) {
        return strtolower((string) TFS_PASSWORD_TYPE);
    }

    if (defined('PASSWORD_MODE')) {
        $mode = strtolower((string) PASSWORD_MODE);

        if ($mode === 'dual') {
            return 'sha1';
        }

        if (strpos($mode, 'tfs_') === 0) {
            return substr($mode, 4);
        }
    }

    return 'sha1';
}

function tfs_password_verify(string $plain, string $storedHash, ?string $type = null): bool
{
    $type = $type !== null ? strtolower(trim($type)) : tfs_password_type();

    if ($type === 'plain') {
        return hash_equals($storedHash, $plain);
    }

    $candidate = tfs_password_hash($plain, $type);

    return hash_equals($storedHash, $candidate);
}
