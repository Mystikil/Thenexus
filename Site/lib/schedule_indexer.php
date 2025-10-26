<?php

declare(strict_types=1);

require_once __DIR__ . '/server_paths.php';

function nx_index_schedule(PDO $pdo): array
{
    $paths = nx_server_paths();
    $now = new DateTimeImmutable('now');
    $entries = [];
    $counts = ['globalevents' => 0, 'raids' => 0];
    $root = isset($paths['server_root']) ? (string) $paths['server_root'] : '';

    if (isset($paths['globalevents']) && is_file($paths['globalevents'])) {
        $globaleventEntries = nx_schedule_parse_globalevents((string) $paths['globalevents'], $now, $root);
        $entries = array_merge($entries, $globaleventEntries);
        $counts['globalevents'] = count($globaleventEntries);
    }

    if (isset($paths['raids_dir']) && is_dir($paths['raids_dir'])) {
        $raidEntries = nx_schedule_parse_raids((string) $paths['raids_dir'], $now, $root);
        $entries = array_merge($entries, $raidEntries);
        $counts['raids'] = count($raidEntries);
    }

    $seenKeys = [];
    $inserted = 0;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO server_schedule (source, name, file_path, cron, params, next_run) VALUES (:source, :name, :file_path, :cron, :params, :next_run)
            ON DUPLICATE KEY UPDATE name = VALUES(name), cron = VALUES(cron), params = VALUES(params), next_run = VALUES(next_run)');

        foreach ($entries as $entry) {
            $seenKey = $entry['source'] . '::' . $entry['file_path'];
            $seenKeys[$seenKey] = true;

            $paramsJson = json_encode($entry['params'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($paramsJson === false) {
                $paramsJson = json_encode(['error' => 'json_encode_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $stmt->execute([
                'source' => $entry['source'],
                'name' => $entry['name'],
                'file_path' => $entry['file_path'],
                'cron' => $entry['cron'],
                'params' => $paramsJson,
                'next_run' => $entry['next_run'],
            ]);
            $inserted++;
        }

        $existingStmt = $pdo->query('SELECT id, source, file_path FROM server_schedule');
        $toDelete = [];
        foreach ($existingStmt as $row) {
            $key = $row['source'] . '::' . $row['file_path'];
            if (!isset($seenKeys[$key])) {
                $toDelete[] = (int) $row['id'];
            }
        }

        if ($toDelete !== []) {
            $deleteStmt = $pdo->prepare('DELETE FROM server_schedule WHERE id = :id');
            foreach ($toDelete as $id) {
                $deleteStmt->execute(['id' => $id]);
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'globalevents' => $counts['globalevents'],
        'raids' => $counts['raids'],
        'total' => $inserted,
    ];
}

function nx_schedule_parse_globalevents(string $file, DateTimeImmutable $now, string $root): array
{
    $entries = [];
    $xml = nx_schedule_load_xml($file);

    if ($xml === null) {
        return $entries;
    }

    foreach ($xml->globalevent as $event) {
        $attributes = nx_schedule_attributes($event);
        $name = $attributes['name'] ?? basename($file);
        $identifier = nx_schedule_relative_path($file, $root) . '#' . $name;
        $next = nx_schedule_next_from_globalevent($attributes, $now);
        $entries[] = [
            'source' => 'globalevents',
            'name' => $name,
            'file_path' => $identifier,
            'cron' => nx_schedule_describe($attributes),
            'params' => ['attributes' => $attributes],
            'next_run' => $next?->format('Y-m-d H:i:s'),
        ];
    }

    return $entries;
}

function nx_schedule_parse_raids(string $directory, DateTimeImmutable $now, string $root): array
{
    $entries = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'xml') {
            continue;
        }

        $xml = nx_schedule_load_xml($file->getPathname());
        if ($xml === null) {
            continue;
        }

        foreach ($xml->raid as $raid) {
            $attributes = nx_schedule_attributes($raid);
            $name = $attributes['name'] ?? basename($file->getBasename('.xml'));
            $starts = [];
            foreach ($raid->start as $startNode) {
                $starts[] = nx_schedule_attributes($startNode);
            }

            $params = [
                'attributes' => $attributes,
                'starts' => $starts,
            ];

            $identifier = nx_schedule_relative_path($file->getPathname(), $root) . '#' . $name;
            $next = nx_schedule_next_from_raid($attributes, $starts, $now);

            $entries[] = [
                'source' => 'raids',
                'name' => $name,
                'file_path' => $identifier,
                'cron' => nx_schedule_describe($attributes),
                'params' => $params,
                'next_run' => $next?->format('Y-m-d H:i:s'),
            ];
        }
    }

    return $entries;
}

function nx_schedule_load_xml(string $file): ?SimpleXMLElement
{
    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_file($file);
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        error_log('schedule_indexer: failed to parse XML file ' . $file);
        return null;
    }

    return $xml;
}

function nx_schedule_attributes(SimpleXMLElement $element): array
{
    $attributes = [];
    foreach ($element->attributes() as $key => $value) {
        $attributes[$key] = (string) $value;
    }

    return $attributes;
}

function nx_schedule_describe(array $attributes): ?string
{
    $parts = [];
    foreach (['type', 'interval', 'time', 'hour', 'minute', 'chance'] as $key) {
        if (isset($attributes[$key]) && $attributes[$key] !== '') {
            $parts[] = $key . '=' . $attributes[$key];
        }
    }

    if ($parts === []) {
        return null;
    }

    $description = implode(' ', $parts);

    return substr($description, 0, 120);
}

function nx_schedule_next_from_globalevent(array $attributes, DateTimeImmutable $now): ?DateTimeImmutable
{
    if (isset($attributes['interval']) && ctype_digit((string) $attributes['interval'])) {
        $seconds = max(1, (int) $attributes['interval']);
        return $now->add(DateInterval::createFromDateString($seconds . ' seconds'));
    }

    if (isset($attributes['time'])) {
        $times = preg_split('/[;,\s]+/', (string) $attributes['time'], -1, PREG_SPLIT_NO_EMPTY);
        return nx_schedule_pick_time($now, $times ?: []);
    }

    $hour = isset($attributes['hour']) ? (int) $attributes['hour'] : null;
    $minute = isset($attributes['minute']) ? (int) $attributes['minute'] : null;

    if ($hour !== null && $hour >= 0 && $hour <= 23) {
        $minute = $minute !== null ? max(0, min(59, $minute)) : 0;
        $candidate = $now->setTime($hour, $minute, 0);
        if ($candidate <= $now) {
            $candidate = $candidate->modify('+1 day');
        }
        return $candidate;
    }

    return null;
}

function nx_schedule_next_from_raid(array $attributes, array $starts, DateTimeImmutable $now): ?DateTimeImmutable
{
    $times = [];
    foreach ($starts as $start) {
        if (isset($start['time'])) {
            $times[] = $start['time'];
        }
    }

    if ($times !== []) {
        $next = nx_schedule_pick_time($now, $times);
        if ($next instanceof DateTimeImmutable) {
            return $next;
        }
    }

    if (isset($attributes['interval']) && ctype_digit((string) $attributes['interval'])) {
        $minutes = max(1, (int) $attributes['interval']);
        return $now->add(DateInterval::createFromDateString($minutes . ' minutes'));
    }

    return null;
}

function nx_schedule_pick_time(DateTimeImmutable $now, array $times): ?DateTimeImmutable
{
    $candidates = [];
    foreach ($times as $time) {
        $candidate = nx_schedule_time_after($now, (string) $time);
        if ($candidate instanceof DateTimeImmutable) {
            $candidates[] = $candidate;
        }
    }

    if ($candidates === []) {
        return null;
    }

    usort($candidates, static function (DateTimeImmutable $a, DateTimeImmutable $b): int {
        return $a <=> $b;
    });

    return $candidates[0];
}

function nx_schedule_time_after(DateTimeImmutable $now, string $timeString): ?DateTimeImmutable
{
    $timeString = trim($timeString);
    if ($timeString === '') {
        return null;
    }

    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeString, $matches)) {
        return null;
    }

    $hour = min(23, (int) $matches[1]);
    $minute = min(59, (int) $matches[2]);
    $second = isset($matches[3]) ? min(59, (int) $matches[3]) : 0;

    $candidate = $now->setTime($hour, $minute, $second);
    if ($candidate <= $now) {
        $candidate = $candidate->modify('+1 day');
    }

    return $candidate;
}

function nx_schedule_relative_path(string $path, string $root): string
{
    $normalizedPath = nx_normalize_path($path);
    $normalizedRoot = nx_normalize_path($root);

    if ($normalizedRoot !== '') {
        $prefix = $normalizedRoot . '/';
        if (strncmp($normalizedPath, $prefix, strlen($prefix)) === 0) {
            return substr($normalizedPath, strlen($prefix));
        }
    }

    return $normalizedPath;
}
