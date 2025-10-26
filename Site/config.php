<?php

const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'nexus';
const DB_USER = 'root';
const DB_PASS = '';

const SITE_TITLE = 'Nexus AAC';
const WEBHOOK_SECRET = 'replace-with-webhook-secret';
const BRIDGE_SECRET = 'replace-with-bridge-secret';

// Password + authentication configuration
const PASSWORD_MODE = 'tfs_sha1'; // 'tfs_sha1' | 'tfs_md5' | 'tfs_plain' | 'dual'
const PASS_WITH_SALT = false;
const SALT_COL = 'salt';
const ALLOW_FALLBACKS = false;


// Absolute path to your TFS server root (folder that has config.lua and /data)
define('SERVER_PATH', 'C:/xampp/htdocs'); // adjust later in UI


define('RECOVERY_KEY_LENGTH', 32);
define('RECOVERY_ATTEMPT_LIMIT', 10);     // attempts per 15 min per account+IP
define('RECOVERY_WINDOW_SECONDS', 900);   // 15 minutes

define('REQUIRE_GAME_ACCOUNT_ON_REGISTER', true); // must create accounts row
define('ALLOW_AUTO_PROVISION_WEBSITE_USER', true); // when logging in with a game account that lacks a website user

/**
 * Fetch a value from the settings table.
 */
function get_setting(string $key): ?string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!function_exists('db')) {
        $cache[$key] = null;
        return null;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = $value === false ? null : (string) $value;
        return $cache[$key];
    } catch (Throwable $exception) {
        error_log('get_setting error: ' . $exception->getMessage());
        $cache[$key] = null;
        return null;
    }
}
