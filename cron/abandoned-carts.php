<?php
/**
 * Oyejo Gas - abandoned-cart recovery notifications.
 * CLI only. Suggested cron: every hour.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/growth.php';

$n = abandoned_queue_reminders();
echo date('Y-m-d H:i:s') . " abandoned-cart reminders queued: $n" . PHP_EOL;
