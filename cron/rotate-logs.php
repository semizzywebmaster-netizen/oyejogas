<?php
/**
 * Oyejo Gas - log rotation (Phase 25).
 * CLI only. Suggested cron: 0 3 * * * php /path/to/cron/rotate-logs.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';

[$n] = logs_rotate(null);
echo date('Y-m-d H:i:s') . " log rotation: $n file(s) rotated." . PHP_EOL;
