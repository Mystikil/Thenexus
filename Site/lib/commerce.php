<?php

declare(strict_types=1);

function nx_add_coins(PDO $pdo, int $accountId, int $coins): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO web_accounts (account_id, email, created, points)
         VALUES (?, \'\', UNIX_TIMESTAMP(), ?)
         ON DUPLICATE KEY UPDATE points = points + VALUES(points)'
    );
    $stmt->execute([$accountId, $coins]);
}

function nx_add_premium_days(PDO $pdo, int $accountId, int $days): void
{
    $stmt = $pdo->prepare('SELECT premium_ends_at FROM accounts WHERE id = ?');
    $stmt->execute([$accountId]);
    $currentEnd = (int) ($stmt->fetchColumn() ?: 0);

    $start = max($currentEnd, time());
    $newEnd = $start + ($days * 86400);

    $update = $pdo->prepare('UPDATE accounts SET premium_ends_at = ? WHERE id = ?');
    $update->execute([$newEnd, $accountId]);
}
