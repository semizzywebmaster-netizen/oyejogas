<?php
/**
 * Oyejo Gas - pickup reminder queueing (Phase 22, NT-07).
 * CLI only. Suggested cron: 0 8 * * * php /path/to/cron/pickup-reminders.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';

$n = notify_pickup_reminders();
echo date('Y-m-d H:i:s') . " pickup reminders queued: $n" . PHP_EOL;
