<?php

declare(strict_types=1);

require_once __DIR__ . '/server_paths.php';

function nx_index_schedule(PDO $pdo): array
{
    $paths = nx_server_paths();
    $dataRoot = $paths['data'] ?? '';
    $serverRoot = $paths['server_root'] ?? '';

    $candidates = [];
    if ($dataRoot !== '') {
        $candidates[] = $dataRoot . '/events/events.xml';
        $candidates[] = $dataRoot . '/globalevents/globalevents.xml';
        $candidates[] = $dataRoot . '/event/schedule.xml';
    }
    if ($serverRoot !== '') {
        $candidates[] = $serverRoot . '/data/events/events.xml';
        $candidates[] = $serverRoot . '/data/globalevents/globalevents.xml';
    }

    $scheduleFile = '';
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_file($candidate)) {
            $scheduleFile = $candidate;
            break;
        }
    }

    $logStmt = $pdo->prepare('INSERT INTO index_scan_log (kind, status, message) VALUES (:kind, :status, :message)');

    if ($scheduleFile === '') {
        $message = 'Schedule file not found in expected locations.';
        $logStmt->execute(['kind' => 'schedule', 'status' => 'error', 'message' => $message]);
        throw new RuntimeException($message);
    }

    libxml_use_internal_errors(true);
    $document = new DOMDocument();

    if (!$document->load($scheduleFile)) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $message = sprintf('Failed to parse %s (%d errors)', $scheduleFile, count($errors));
        $logStmt->execute(['kind' => 'schedule', 'status' => 'error', 'message' => $message]);
        throw new RuntimeException($message);
    }

    libxml_clear_errors();

    $root = $document->documentElement;
    if (!$root instanceof DOMElement) {
        $message = sprintf('Schedule file %s does not contain a valid XML root.', $scheduleFile);
        $logStmt->execute(['kind' => 'schedule', 'status' => 'error', 'message' => $message]);
        throw new RuntimeException($message);
    }

    $eventNodes = [];
    if ($root->tagName === 'events' || $root->tagName === 'globalevents') {
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $eventNodes[] = $child;
            }
        }
    } elseif ($root->tagName === 'event' || $root->tagName === 'globalevent') {
        $eventNodes[] = $root;
    } else {
        foreach ($root->getElementsByTagName('event') as $event) {
            if ($event instanceof DOMElement) {
                $eventNodes[] = $event;
            }
        }
        foreach ($root->getElementsByTagName('globalevent') as $event) {
            if ($event instanceof DOMElement) {
                $eventNodes[] = $event;
            }
        }
    }

    if ($eventNodes === []) {
        if (!function_exists('nx_table_exists') || nx_table_exists($pdo, 'event_schedule')) {
            try {
                $pdo->exec('DELETE FROM event_schedule');
            } catch (Throwable $exception) {
                // ignore cleanup errors when table is missing
            }
        }
        $logStmt->execute(['kind' => 'schedule', 'status' => 'ok', 'message' => 'No schedule entries found. Existing schedule cleared.']);
        return ['events' => 0, 'source' => $scheduleFile];
    }

    if (function_exists('nx_table_exists') && !nx_table_exists($pdo, 'event_schedule')) {
        $pdo->exec('CREATE TABLE IF NOT EXISTS event_schedule (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(128) NOT NULL,
            event_type VARCHAR(64) DEFAULT NULL,
            trigger_type VARCHAR(32) DEFAULT NULL,
            interval_seconds INT UNSIGNED DEFAULT NULL,
            time_of_day VARCHAR(32) DEFAULT NULL,
            script VARCHAR(255) DEFAULT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            raw_attributes TEXT NULL,
            UNIQUE KEY uniq_event_name (event_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    $insert = $pdo->prepare(
        'INSERT INTO event_schedule (
            event_name, event_type, trigger_type, interval_seconds, time_of_day, script, is_enabled, raw_attributes
        ) VALUES (
            :event_name, :event_type, :trigger_type, :interval_seconds, :time_of_day, :script, :is_enabled, :raw_attributes
        ) ON DUPLICATE KEY UPDATE
            event_type = VALUES(event_type),
            trigger_type = VALUES(trigger_type),
            interval_seconds = VALUES(interval_seconds),
            time_of_day = VALUES(time_of_day),
            script = VALUES(script),
            is_enabled = VALUES(is_enabled),
            raw_attributes = VALUES(raw_attributes)'
    );

    $processed = 0;
    $errors = [];
    $seen = [];

    foreach ($eventNodes as $node) {
        $attributes = [];
        foreach ($node->attributes as $attr) {
            if ($attr instanceof DOMAttr) {
                $attributes[$attr->name] = (string) $attr->value;
            }
        }

        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            $errors[] = sprintf('Skipping unnamed event in %s', $scheduleFile);
            continue;
        }

        $type = $attributes['type'] ?? null;
        $script = $attributes['script'] ?? ($attributes['action'] ?? null);
        $timeOfDay = $attributes['time'] ?? ($attributes['start'] ?? null);

        $intervalSeconds = null;
        if (isset($attributes['interval'])) {
            $intervalValue = trim((string) $attributes['interval']);
            if (ctype_digit($intervalValue)) {
                $intervalSeconds = (int) $intervalValue;
            } elseif (is_numeric($intervalValue)) {
                $intervalSeconds = (int) round((float) $intervalValue);
            }
        }

        $triggerType = null;
        if ($intervalSeconds !== null) {
            $triggerType = 'interval';
        } elseif ($timeOfDay !== null) {
            $triggerType = 'time';
        }

        $isEnabled = 1;
        if (isset($attributes['enabled'])) {
            $value = strtolower(trim((string) $attributes['enabled']));
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                $isEnabled = 0;
            }
        }

        unset($attributes['name'], $attributes['type'], $attributes['script'], $attributes['action'], $attributes['time'], $attributes['start'], $attributes['interval'], $attributes['enabled']);

        $children = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $childData = [];
            foreach ($child->attributes as $childAttr) {
                if ($childAttr instanceof DOMAttr) {
                    $childData[$childAttr->name] = (string) $childAttr->value;
                }
            }
            $text = trim($child->textContent);
            if ($text !== '') {
                $childData['value'] = $text;
            }
            $children[$child->tagName][] = $childData;
        }

        if ($children !== []) {
            $attributes['children'] = $children;
        }

        $rawAttributes = $attributes === [] ? null : json_encode($attributes, JSON_UNESCAPED_UNICODE);

        try {
            $insert->execute([
                'event_name' => $name,
                'event_type' => $type !== '' ? $type : null,
                'trigger_type' => $triggerType,
                'interval_seconds' => $intervalSeconds,
                'time_of_day' => $timeOfDay !== '' ? $timeOfDay : null,
                'script' => $script !== '' ? $script : null,
                'is_enabled' => $isEnabled,
                'raw_attributes' => $rawAttributes,
            ]);
            $processed++;
            $seen[] = $name;
        } catch (Throwable $exception) {
            $errors[] = sprintf('Failed to store schedule entry %s: %s', $name, $exception->getMessage());
        }
    }

    if ($seen !== []) {
        $placeholders = implode(', ', array_fill(0, count($seen), '?'));
        $delete = $pdo->prepare('DELETE FROM event_schedule WHERE event_name NOT IN (' . $placeholders . ')');
        $delete->execute($seen);
    } else {
        try {
            $pdo->exec('DELETE FROM event_schedule');
        } catch (Throwable $exception) {
            // ignore cleanup errors when table is missing
        }
    }

    $status = $errors === [] ? 'ok' : 'error';
    $messageParts = [sprintf('Indexed %d events from %s', $processed, $scheduleFile)];
    if ($errors !== []) {
        $messageParts[] = 'Errors: ' . implode('; ', array_slice($errors, 0, 5));
        if (count($errors) > 5) {
            $messageParts[] = sprintf('(+%d more)', count($errors) - 5);
        }
    }

    $logStmt->execute([
        'kind' => 'schedule',
        'status' => $status,
        'message' => implode(' ', $messageParts),
    ]);

    if ($errors !== []) {
        throw new RuntimeException($messageParts[0]);
    }

    return [
        'events' => $processed,
        'source' => $scheduleFile,
    ];
}
