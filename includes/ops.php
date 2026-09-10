<?php
/**
 * Oyejo Gas - backups, restore, site health (Phase 24).
 *
 * Pure-PHP MySQL dumps (no mysqldump/exec dependency, cPanel-safe).
 * Dump format (generated here, replayed by ops_restore):
 *   - one SQL statement per line (PDO::quote escapes newlines, CREATE
 *     TABLE whitespace is collapsed), `--` comment lines ignored;
 *   - SET FOREIGN_KEY_CHECKS=0; first, =1; last;
 *   - DROP IF EXISTS + CREATE + chunked single-line INSERTs per table.
 * Suitable for app-scale databases (whole dump held in memory).
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/admin.php';

function ops_backup_settings() {
    $out = [
        'retention_days' => (int) setting('backup_retention_days', 14),
        'keep_min' => (int) setting('backup_keep_min', 3),
        'max_age_hours' => (int) setting('backup_max_age_hours', 24),
    ];
    $out['retention_days'] = max(1, min(365, $out['retention_days']));
    $out['keep_min'] = max(1, min(50, $out['keep_min']));
    $out['max_age_hours'] = max(1, min(720, $out['max_age_hours']));
    return $out;
}

function ops_backup_filename() {
    return 'oyejo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
}

function ops_ident($name) {
    return '`' . str_replace('`', '``', (string) $name) . '`';
}

/**
 * Run a full database backup. Returns [ok, message, id|null].
 * Failures are alerted (admin email + ops.log). Never throws.
 */
function ops_backup_run($actor_id, $source = 'manual') {
    $t0 = microtime(true);
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }
    $fail = function ($msg) use ($actor_id, $source) {
        try {
            db()->prepare(
                'INSERT INTO `backups` (`filename`, `source`, `status`, `error`, `created_by`) VALUES (?, ?, ?, ?, ?)'
            )->execute(['failed-' . date('Ymd-His'), $source, 'failed', substr($msg, 0, 500), $actor_id ?: null]);
        } catch (Throwable $t) {
            // Recording failed too; the alert below still goes out.
        }
        ops_alert('Oyejo Gas backup FAILED (' . $source . ')', $msg);
        return [false, $msg, null];
    };
    try {
        if (!in_array($source, ['manual', 'scheduled', 'pre-restore'], true)) {
            $source = 'manual';
        }
        if (!is_dir(BACKUP_PATH) && !@mkdir(BACKUP_PATH, 0755, true)) {
            return $fail('Backup directory is not writable.');
        }
        if (!is_writable(BACKUP_PATH)) {
            return $fail('Backup directory is not writable.');
        }
        $pdo = db();
        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        if (!$tables) {
            return $fail('No tables found to back up.');
        }
        $file = ops_backup_filename();
        $tmp = BACKUP_PATH . '/' . $file . '.part';
        $fh = @fopen($tmp, 'wb');
        if (!$fh) {
            return $fail('Could not write to the backup directory.');
        }
        $w = function ($line) use ($fh) {
            if (@fwrite($fh, $line . "\n") === false) {
                throw new Exception('Write failed (disk full?).');
            }
        };
        try {
            $w('-- Oyejo Gas database backup');
            $w('-- created: ' . date('Y-m-d H:i:s') . ' db: ' . DB_NAME . ' app: ' . OYEJO_VERSION);
            $w('SET FOREIGN_KEY_CHECKS=0;');
            $nt = 0;
            $nr = 0;
            foreach ($tables as $t) {
                $name = (string) $t[0];
                $esc = ops_ident($name);
                $c = $pdo->query('SHOW CREATE TABLE ' . $esc)->fetch();
                if (!$c || empty($c['Create Table'])) {
                    throw new Exception('Could not read schema for ' . $name . '.');
                }
                $w('DROP TABLE IF EXISTS ' . $esc . ';');
                $w(preg_replace('/\s+/', ' ', (string) $c['Create Table']) . ';');
                $nt++;
                $cols = null;
                $off = 0;
                while (true) {
                    $rows = $pdo->query('SELECT * FROM ' . $esc . ' LIMIT 1000 OFFSET ' . $off)->fetchAll(PDO::FETCH_NUM);
                    if (!$rows) {
                        break;
                    }
                    if ($cols === null) {
                        $cols = [];
                        $cs = $pdo->query('SELECT * FROM ' . $esc . ' LIMIT 0');
                        for ($i = 0; $i < $cs->columnCount(); $i++) {
                            $m = $cs->getColumnMeta($i);
                            $cols[] = ops_ident($m['name']);
                        }
                    }
                    foreach (array_chunk($rows, 250) as $chunk) {
                        $vals = [];
                        foreach ($chunk as $r) {
                            $cells = [];
                            foreach ($r as $v) {
                                $cells[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                            }
                            $vals[] = '(' . implode(',', $cells) . ')';
                        }
                        $w('INSERT INTO ' . $esc . ' (' . implode(',', $cols) . ') VALUES ' . implode(',', $vals) . ';');
                    }
                    $nr += count($rows);
                    $off += 1000;
                }
            }
            $w('SET FOREIGN_KEY_CHECKS=1;');
        } finally {
            @fclose($fh);
        }
        $final = $file;
        if (function_exists('gzencode')) {
            $raw = @file_get_contents($tmp);
            if ($raw === false) {
                throw new Exception('Could not read the temporary dump.');
            }
            $final = $file . '.gz';
            if (@file_put_contents(BACKUP_PATH . '/' . $final, gzencode($raw, 6)) === false) {
                throw new Exception('Could not write the compressed dump.');
            }
            @unlink($tmp);
        } else {
            @rename($tmp, BACKUP_PATH . '/' . $final);
        }
        $bytes = (int) @filesize(BACKUP_PATH . '/' . $final);
        $sha = hash_file('sha256', BACKUP_PATH . '/' . $final);
        if ($sha === false) {
            throw new Exception('Could not checksum the dump.');
        }
        $secs = round(microtime(true) - $t0, 2);
        db()->prepare(
            'INSERT INTO `backups` (`filename`, `bytes`, `tables`, `rows`, `seconds`, `source`, `status`, `sha256`, `created_by`)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$final, $bytes, $nt, $nr, $secs, $source, 'ok', $sha, $actor_id ?: null]);
        $id = (int) db()->lastInsertId();
        adm_audit('ops.backup', $actor_id, $id, null, ['file' => $final, 'tables' => $nt, 'rows' => $nr, 'source' => $source]);
        return [true, 'Backup ' . $final . ' (' . $nt . ' tables, ' . number_format($nr) . ' rows).', $id];
    } catch (Throwable $t) {
        return $fail($t->getMessage());
    }
}

function ops_backups_list($limit = 50) {
    $stmt = db()->prepare(
        'SELECT b.*, u.`name` AS actor FROM `backups` b LEFT JOIN `users` u ON u.`id` = b.`created_by`'
        . ' ORDER BY b.`id` DESC LIMIT ' . max(1, min(1000, (int) $limit))
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function ops_backup_get($id) {
    $stmt = db()->prepare('SELECT * FROM `backups` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    return $stmt->fetch() ?: null;
}

/** Validated absolute path for a backup row, or null (traversal-proof). */
function ops_backup_path($row) {
    $fn = (string) ($row['filename'] ?? '');
    if (!preg_match('/^oyejo-\d{8}-\d{6}-[0-9a-f]{8}\.sql(\.gz)?$/', $fn)) {
        return null;
    }
    $base = realpath(BACKUP_PATH);
    $p = realpath(BACKUP_PATH . '/' . $fn);
    if ($base === false || $p === false || strpos($p, $base) !== 0) {
        return null;
    }
    return $p;
}

/** Stream a backup file to the browser. Caller must check backups.restore. */
function ops_backup_download($id) {
    $b = ops_backup_get($id);
    $path = $b ? ops_backup_path($b) : null;
    if (!$b || !$path) {
        http_response_code(404);
        exit('Backup not found.');
    }
    $is_gz = substr($path, -3) === '.gz';
    header('Content-Type: ' . ($is_gz ? 'application/gzip' : 'application/sql'));
    header('Content-Disposition: attachment; filename="' . $b['filename'] . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

function ops_backup_delete($id, $actor_id) {
    $b = ops_backup_get($id);
    if (!$b) {
        return [false, 'Backup not found.'];
    }
    $path = ops_backup_path($b);
    if ($path) {
        @unlink($path);
    }
    db()->prepare('DELETE FROM `backups` WHERE `id` = ?')->execute([(int) $id]);
    adm_audit('ops.backup_delete', $actor_id, (int) $id, ['file' => $b['filename']], null);
    return [true, 'Backup ' . $b['filename'] . ' deleted.'];
}

/**
 * Restore a backup. $confirm must equal the filename exactly.
 * Takes a pre-restore snapshot first, locks, enables maintenance
 * during the replay, verifies checksum. Returns [ok, message].
 */
function ops_restore($id, $actor_id, $confirm) {
    $b = ops_backup_get($id);
    if (!$b || $b['status'] !== 'ok') {
        return [false, 'Backup not found.'];
    }
    if (!hash_equals((string) $b['filename'], (string) $confirm)) {
        return [false, 'Confirmation does not match the backup filename.'];
    }
    $path = ops_backup_path($b);
    if (!$path) {
        return [false, 'Backup file is missing from disk.'];
    }
    if (!hash_equals((string) $b['sha256'], hash_file('sha256', $path))) {
        return [false, 'Checksum mismatch — the file may have been tampered with.'];
    }
    $is_gz = substr($path, -3) === '.gz';
    if ($is_gz && !function_exists('gzdecode')) {
        return [false, 'zlib is unavailable; cannot read compressed backups.'];
    }
    $lock = BACKUP_PATH . '/restore.lock';
    if (is_file($lock) && (int) @filemtime($lock) > time() - 3600) {
        return [false, 'Another restore is already running.'];
    }
    @file_put_contents($lock, (string) ($actor_id ?: 0));
    try {
        [$sok, $smsg, $ssid] = ops_backup_run($actor_id, 'pre-restore');
        if (!$sok) {
            return [false, 'Pre-restore snapshot failed: ' . $smsg];
        }
        // Capture snapshot metadata: the replay below rebuilds the
        // backups table from the dump, wiping this row (file survives).
        $snap = $ssid ? ops_backup_get($ssid) : null;
        $was_maint = oyejo_feature('maintenance_mode');
        if (!$was_maint) {
            adm_toggle_set('maintenance_mode', true, $actor_id);
        }
        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }
            $data = @file_get_contents($path);
            if ($data === false) {
                throw new Exception('Could not read the backup file.');
            }
            if ($is_gz) {
                $data = @gzdecode($data);
                if ($data === false) {
                    throw new Exception('Backup file is corrupt (gzip decode failed).');
                }
            }
            $n = 0;
            foreach (explode("\n", $data) as $line) {
                $line = trim($line);
                if ($line === '' || strncmp($line, '--', 2) === 0) {
                    continue;
                }
                $n++;
                try {
                    db()->exec($line);
                } catch (PDOException $e) {
                    throw new Exception('Restore stopped at statement ' . $n . ': ' . $e->getMessage());
                }
            }
        } finally {
            if (!$was_maint) {
                adm_toggle_set('maintenance_mode', false, $actor_id);
            }
        }
        // Re-register the restored backup itself: the dump predates its
        // own row, so the replay wipes it (file survives on disk).
        $have = db()->prepare('SELECT 1 FROM `backups` WHERE `filename` = ?');
        $have->execute([$b['filename']]);
        if (!$have->fetchColumn()) {
            db()->prepare(
                'INSERT INTO `backups` (`filename`, `bytes`, `tables`, `rows`, `seconds`, `source`, `status`, `sha256`, `created_by`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $b['filename'], $b['bytes'], $b['tables'], $b['rows'], $b['seconds'],
                $b['source'], 'ok', $b['sha256'], $b['created_by'],
            ]);
        }
        if ($snap) {
            db()->prepare(
                'INSERT INTO `backups` (`filename`, `bytes`, `tables`, `rows`, `seconds`, `source`, `status`, `sha256`, `created_by`)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $snap['filename'], $snap['bytes'], $snap['tables'], $snap['rows'], $snap['seconds'],
                'pre-restore', 'ok', $snap['sha256'], $snap['created_by'],
            ]);
        }
        adm_audit('ops.restore', $actor_id, (int) $id, null, ['file' => $b['filename'], 'statements' => $n]);
        return [true, 'Restored ' . $b['filename'] . ' (' . number_format($n) . ' statements). A pre-restore snapshot was kept.'];
    } catch (Throwable $t) {
        ops_alert('Oyejo Gas RESTORE failed', $t->getMessage());
        return [false, $t->getMessage()];
    } finally {
        @unlink($lock);
    }
}

/**
 * Delete backups older than retention, always keeping keep_min newest.
 * Returns [deleted, kept].
 */
function ops_cleanup($actor_id) {
    $s = ops_backup_settings();
    $rows = ops_backups_list(1000);
    $keep = [];
    foreach (array_slice($rows, 0, $s['keep_min']) as $r) {
        $keep[(int) $r['id']] = true;
    }
    $cutoff = date('Y-m-d H:i:s', time() - $s['retention_days'] * 86400);
    $del = 0;
    foreach ($rows as $r) {
        if (isset($keep[(int) $r['id']])) {
            continue;
        }
        if ($r['created_at'] >= $cutoff) {
            continue;
        }
        $p = ops_backup_path($r);
        if ($p) {
            @unlink($p);
        }
        db()->prepare('DELETE FROM `backups` WHERE `id` = ?')->execute([(int) $r['id']]);
        $del++;
    }
    // Orphan files: on-disk dumps with no row (e.g. rows rolled back by
    // a restore). Old ones are swept; fresh ones are left alone.
    $known = [];
    foreach (db()->query('SELECT `filename` FROM `backups`')->fetchAll() as $r) {
        $known[$r['filename']] = true;
    }
    $orph = 0;
    foreach ((array) glob(BACKUP_PATH . '/oyejo-*.sql*') as $f) {
        $fn = basename((string) $f);
        if (isset($known[$fn])) {
            continue;
        }
        if (!preg_match('/^oyejo-\d{8}-\d{6}-[0-9a-f]{8}\.sql(\.gz)?$/', $fn)) {
            continue;
        }
        if ((int) @filemtime($f) < time() - $s['retention_days'] * 86400) {
            @unlink($f);
            $orph++;
        }
    }
    adm_audit('ops.cleanup', $actor_id, null, null, ['deleted' => $del, 'orphans' => $orph]);
    return [$del + $orph, count($rows) - $del];
}

/** Failure alert: admin email + ops.log. Never throws. */
function ops_alert($subject, $body) {
    try {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $subject . ' | '
            . str_replace(["\r", "\n"], ' ', (string) $body) . "\n";
        if (function_exists('mailer_log')) {
            mailer_log('ops.log', $line);
        } else {
            error_log('[oyejo] ' . $line);
        }
        $to = function_exists('setting') ? (string) setting('admin_alert_email', '') : '';
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL) && function_exists('send_mail')) {
            send_mail($to, $subject, $body);
        }
    } catch (Throwable $t) {
        error_log('[oyejo] ops_alert failed: ' . $t->getMessage());
    }
}

/**
 * Site health: 17 checks. Returns [overall_ok, checks[]].
 * Each check: ['key','label','ok','detail']. Never throws.
 */
function ops_health() {
    $checks = [];
    $add = function ($key, $label, $ok, $detail = '') use (&$checks) {
        $checks[] = ['key' => $key, 'label' => $label, 'ok' => (bool) $ok, 'detail' => (string) $detail];
    };
    try {
        $add('php_version', 'PHP version', PHP_VERSION_ID >= 80100, PHP_VERSION . ' (need 8.1+)');
        $add('ext_pdo_mysql', 'PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'missing');
        $add('ext_curl', 'cURL', extension_loaded('curl'), extension_loaded('curl') ? 'loaded' : 'missing');
        $add('ext_mbstring', 'mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'loaded' : 'missing');
        $add('ext_json', 'JSON', extension_loaded('json'), extension_loaded('json') ? 'loaded' : 'missing');
        $db_ok = false;
        try {
            $db_ok = db_available();
        } catch (Throwable $t) {
            $db_ok = false;
        }
        $add('db_connect', 'Database reachable', $db_ok, $db_ok ? DB_NAME : 'connection failed');
        $tables_ok = false;
        $tables_detail = 'skipped (no DB)';
        if ($db_ok) {
            try {
                foreach (['users', 'settings', 'feature_toggles'] as $t) {
                    db()->query('SELECT 1 FROM ' . ops_ident($t) . ' LIMIT 1');
                }
                $tables_ok = true;
                $tables_detail = 'core tables present';
            } catch (Throwable $t) {
                $tables_detail = $t->getMessage();
            }
        }
        $add('db_tables', 'Core tables', $tables_ok, $tables_detail);
        $add('install_locked', 'Installer locked', is_file(STORAGE_PATH . '/install.lock'),
            is_file(STORAGE_PATH . '/install.lock') ? 'install.lock present' : 'install.lock MISSING');
        $key_ok = defined('APP_KEY') && (string) APP_KEY !== '';
        $add('env_key', 'APP_KEY set', $key_ok, $key_ok ? 'set' : 'empty — sessions/CSRF at risk');
        $add('debug_off', 'Debug mode off', !APP_DEBUG, APP_DEBUG ? 'APP_DEBUG is ON' : 'off');
        $writable = is_writable(BACKUP_PATH) && is_writable(LOG_PATH) && is_writable(STORAGE_PATH . '/cache');
        $add('storage_writable', 'Storage writable', $writable, $writable ? 'backups/logs/cache' : 'check permissions');
        $hta = (string) @file_get_contents(STORAGE_PATH . '/.htaccess');
        $add('storage_blocked', 'Storage blocked over HTTP', strpos($hta, 'Require all denied') !== false,
            strpos($hta, 'Require all denied') !== false ? '.htaccess denies' : 'storage may be web-readable!');
        $cron_ok = function_exists('env') && (string) env('CRON_TOKEN', '') !== '';
        $add('cron_token', 'CRON_TOKEN set', $cron_ok, $cron_ok ? 'set' : 'missing');
        $free = @disk_free_space(BACKUP_PATH);
        $add('disk_space', 'Disk space', $free !== false && $free > 100 * 1048576,
            $free === false ? 'unknown' : round($free / 1048576) . ' MB free (need 100+)');
        $fresh_ok = false;
        $fresh_detail = 'no successful backups yet';
        if ($db_ok && $tables_ok) {
            try {
                $s = ops_backup_settings();
                $last = db()->query("SELECT `created_at` FROM `backups` WHERE `status` = 'ok' ORDER BY `id` DESC LIMIT 1")->fetchColumn();
                if ($last) {
                    $age_h = (time() - strtotime((string) $last)) / 3600;
                    $fresh_ok = $age_h <= $s['max_age_hours'];
                    $fresh_detail = $fresh_ok ? 'last backup ' . $last : 'last backup ' . $last . ' (stale)';
                }
            } catch (Throwable $t) {
                $fresh_detail = $t->getMessage();
            }
        }
        $add('backup_fresh', 'Recent backup', $fresh_ok, $fresh_detail);
        $qd_ok = false;
        $qd_detail = 'unknown';
        try {
            $qd = (int) db()->query("SELECT COUNT(*) FROM `notifications` WHERE `status` = 'queued'")->fetchColumn();
            $qd_ok = $qd < 500;
            $qd_detail = $qd . ' queued (limit 500)';
        } catch (Throwable $t) {
            $qd_detail = $t->getMessage();
        }
        $add('queue_depth', 'Notification queue', $qd_ok, $qd_detail);
        $nf_ok = false;
        $nf_detail = 'unknown';
        try {
            $nf = (int) db()->query("SELECT COUNT(*) FROM `notifications` WHERE `status` = 'failed' AND `created_at` >= NOW() - INTERVAL 1 DAY")->fetchColumn();
            $nf_ok = $nf < 50;
            $nf_detail = $nf . ' failed in 24h (limit 50)';
        } catch (Throwable $t) {
            $nf_detail = $t->getMessage();
        }
        $add('notify_failures', 'Notification failures', $nf_ok, $nf_detail);
    } catch (Throwable $t) {
        $add('health_error', 'Health runner', false, $t->getMessage());
    }
    $overall = true;
    foreach ($checks as $c) {
        if (!$c['ok']) {
            $overall = false;
            break;
        }
    }
    return [$overall, $checks];
}
