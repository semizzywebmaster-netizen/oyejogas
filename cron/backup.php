<?php
/**
 * Oyejo Gas - scheduled database backup + cleanup (Phase 24).
 * CLI only. Suggested cron: 0 2 * * * php /path/to/cron/backup.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/../includes/bootstrap.php';
require_once BASE_PATH . '/includes/ops.php';

[$ok, $msg] = ops_backup_run(0, 'scheduled');
[$del] = ops_cleanup(0);
echo date('Y-m-d H:i:s') . ' backup: ' . ($ok ? 'OK' : 'FAILED') . ' — ' . $msg
    . ' Cleanup deleted ' . $del . '.' . PHP_EOL;
exit($ok ? 0 : 1);
