<?php

declare(strict_types=1);

// SECURITY NOTES
// - Recovery keys are displayed to the player exactly once at creation time.
// - Only SHA-256 hashes of recovery keys are stored in the database.
// - Plaintext recovery keys must never be logged or persisted on the server.

if (!defined('RECOVERY_KEY_LENGTH')) {
    define('RECOVERY_KEY_LENGTH', 32);
}

if (!defined('RECOVERY_ATTEMPT_LIMIT')) {
    define('RECOVERY_ATTEMPT_LIMIT', 10);
}

if (!defined('RECOVERY_WINDOW_SECONDS')) {
    define('RECOVERY_WINDOW_SECONDS', 900);
}

if (!function_exists('nx_generate_recovery_key')) {
    function nx_generate_recovery_key(): string
    {
        $length = (int) RECOVERY_KEY_LENGTH;

        if ($length < 28) {
            $length = 28;
        } elseif ($length > 64) {
            $length = 64;
        }

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Crockford-style Base32 without confusing chars.
        $alphabetLength = strlen($alphabet);
        $key = '';

        for ($i = 0; $i < $length; $i++) {
            try {
                $index = random_int(0, $alphabetLength - 1);
            } catch (Throwable $exception) {
                $fallbackByte = null;

                try {
                    $fallbackByte = random_bytes(1);
                } catch (Throwable $inner) {
                    $fallbackByte = openssl_random_pseudo_bytes(1);
                }

                if (!is_string($fallbackByte) || $fallbackByte === '') {
                    $fallbackByte = chr(mt_rand(0, 255));
                }

                $index = ord($fallbackByte[0]) % $alphabetLength;
            }

            $key .= $alphabet[$index];
        }

        return $key;
    }
}

if (!function_exists('nx_hash_recovery_key')) {
    function nx_hash_recovery_key(string $key): string
    {
        return hash('sha256', $key, true);
    }
}

if (!function_exists('nx_account_has_recovery_key')) {
    function nx_account_has_recovery_key(PDO $pdo, int $accountId): bool
    {
        if ($accountId <= 0) {
            return false;
        }

        $sql = sprintf(
            'SELECT recovery_key_hash FROM %s WHERE %s = :account_id LIMIT 1',
            TFS_ACCOUNTS_TABLE,
            TFS_ACCOUNT_ID_COL
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['account_id' => $accountId]);
        $hash = $stmt->fetchColumn();

        if ($hash === false || $hash === null) {
            return false;
        }

        return $hash !== '';
    }
}

if (!function_exists('nx_set_recovery_key')) {
    function nx_set_recovery_key(PDO $pdo, int $accountId, string $plainKey): bool
    {
        if ($accountId <= 0 || $plainKey === '') {
            return false;
        }

        $hash = nx_hash_recovery_key($plainKey);
        $createdAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $sql = sprintf(
            'UPDATE %s SET recovery_key_hash = :hash, recovery_key_created_at = :created_at WHERE %s = :account_id',
            TFS_ACCOUNTS_TABLE,
            TFS_ACCOUNT_ID_COL
        );

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            'hash' => $hash,
            'created_at' => $createdAt,
            'account_id' => $accountId,
        ]);
    }
}

if (!function_exists('nx_verify_recovery_key')) {
    /**
     * Verify a plaintext recovery key for a given account name.
     *
     * @return array{account_id:int,account_name:string}|null
     */
    function nx_verify_recovery_key(PDO $pdo, string $accountName, string $plainKey): ?array
    {
        $normalized = strtolower(trim($accountName));
        $plainKey = trim($plainKey);

        if ($normalized === '' || $plainKey === '') {
            return null;
        }

        $sql = sprintf(
            'SELECT %1$s AS account_id, %2$s AS account_name, recovery_key_hash FROM %3$s WHERE LOWER(%2$s) = :name LIMIT 1',
            TFS_ACCOUNT_ID_COL,
            TFS_NAME_COL,
            TFS_ACCOUNTS_TABLE
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['name' => $normalized]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $storedHash = $row['recovery_key_hash'] ?? null;

        if (!is_string($storedHash) || $storedHash === '') {
            return null;
        }

        $candidate = nx_hash_recovery_key($plainKey);

        if (!hash_equals($storedHash, $candidate)) {
            return null;
        }

        return [
            'account_id' => (int) $row['account_id'],
            'account_name' => (string) $row['account_name'],
        ];
    }
}

if (!function_exists('nx_record_recovery_attempt')) {
    function nx_record_recovery_attempt(PDO $pdo, string $accountName, ?string $ip): void
    {
        $normalized = strtolower(trim($accountName));
        $packedIp = nx_recovery_pack_ip($ip);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO recovery_attempts (account_name, ip, ts) VALUES (:account_name, :ip, NOW())'
            );
            $stmt->execute([
                'account_name' => $normalized,
                'ip' => $packedIp,
            ]);
        } catch (Throwable $exception) {
            // Ignore logging failures to avoid interrupting recovery attempts.
        }
    }
}

if (!function_exists('nx_recovery_too_many_attempts')) {
    function nx_recovery_too_many_attempts(
        PDO $pdo,
        string $accountName,
        ?string $ip,
        int $windowSeconds = RECOVERY_WINDOW_SECONDS,
        int $limit = RECOVERY_ATTEMPT_LIMIT
    ): bool {
        if ($limit <= 0) {
            return false;
        }

        $normalized = strtolower(trim($accountName));
        $packedIp = nx_recovery_pack_ip($ip);
        $cutoff = (new DateTimeImmutable(sprintf('-%d seconds', max($windowSeconds, 1)), new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM recovery_attempts WHERE account_name = :account_name AND ip = :ip AND ts >= :cutoff'
            );
            $stmt->execute([
                'account_name' => $normalized,
                'ip' => $packedIp,
                'cutoff' => $cutoff,
            ]);
            $count = (int) $stmt->fetchColumn();
        } catch (Throwable $exception) {
            return false;
        }

        return $count >= $limit;
    }
}

if (!function_exists('nx_recovery_pack_ip')) {
    function nx_recovery_pack_ip(?string $ip): string
    {
        if (!is_string($ip) || $ip === '') {
            return str_repeat("\0", 16);
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return str_repeat("\0", 16);
        }

        return $packed;
    }
}
