<?php
/**
 * Oyejo Gas - notification queue worker (Phase 22).
 * CLI only. Suggested cron: * * * * * php /path/to/cron/send-notifications.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';

[$proc, $sent, $failed, $note] = notify_process_queue(100);
echo date('Y-m-d H:i:s') . " worker: $proc processed, $sent sent, $failed failed"
    . ($note !== '' ? " ($note)" : '') . PHP_EOL;
