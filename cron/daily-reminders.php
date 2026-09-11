<?php
/**
 * Oyejo Gas - streak-at-risk reminders for daily check-in.
 * CLI only. Suggested cron: 0 10 * * * php /path/to/cron/daily-reminders.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/daily.php';

$n = daily_queue_reminders();
echo date('Y-m-d H:i:s') . " daily streak reminders queued: $n" . PHP_EOL;
