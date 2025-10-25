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
