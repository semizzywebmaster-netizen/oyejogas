<?php
/**
 * Oyejo Gas - central error handling, logging, rate limits (Phase 25).
 *
 * 8 failure domains: auth, payments, orders, wallet, notifications,
 * database, files, system. Every report gets a user-facing reference
 * number; internals go to protected logs only (storage/ is denied
 * over HTTP). Logging must never break the app: every writer is
 * guarded and fails silent to error_log.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/admin.php'; // adm_audit for resolve/rotate actions

function err_domains() {
    return ['auth', 'payments', 'orders', 'wallet', 'notifications', 'database', 'files', 'system'];
}

function err_levels() {
    return ['debug', 'info', 'warning', 'error', 'critical'];
}

/** Unique user-facing reference, e.g. ERR-20260911-120001-a1b2. */
function err_ref() {
    return 'ERR-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2));
}

/**
 * Append a JSON line to storage/logs/app.log. Never throws.
 * $ctx must be scalars/arrays only (no passwords, tokens or hashes).
 */
function log_event($domain, $level, $message, array $ctx = []) {
    try {
        if (!in_array($domain, err_domains(), true)) {
            $domain = 'system';
        }
        if (!in_array($level, err_levels(), true)) {
            $level = 'info';
        }
        if (!is_dir(LOG_PATH)) {
            @mkdir(LOG_PATH, 0755, true);
        }
        $row = [
            'ts' => date('Y-m-d H:i:s'),
            'domain' => $domain,
            'level' => $level,
            'msg' => substr((string) $message, 0, 2000),
            'ctx' => $ctx,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user' => function_exists('current_user') ? ((($u = @current_user()) && isset($u['id'])) ? (int) $u['id'] : null) : null,
        ];
        @file_put_contents(LOG_PATH . '/app.log', json_encode($row) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $t) {
        error_log('[oyejo] log_event failed: ' . $t->getMessage());
    }
}

/**
 * Report an error: file log + server log + deduped error_reports row.
 * $e is a Throwable or a plain message string. Returns the $ref.
 * First occurrence of a critical signature alerts the admin.
 * Never throws.
 */
function report_error($domain, $level, $e, array $ctx = []) {
    $ref = err_ref();
    try {
        if (!in_array($domain, err_domains(), true)) {
            $domain = 'system';
        }
        if (!in_array($level, err_levels(), true)) {
            $level = 'error';
        }
        if ($e instanceof Throwable) {
            $class = get_class($e);
            $msg = $e->getMessage() !== '' ? $e->getMessage() : $class;
            $file = $e->getFile();
            $line = (int) $e->getLine();
        } else {
            $class = 'Error';
            $msg = (string) $e;
            $file = (string) ($ctx['file'] ?? '');
            $line = (int) ($ctx['line'] ?? 0);
            unset($ctx['file'], $ctx['line']);
        }
        error_log('[oyejo] [' . $ref . '] [' . $domain . '/' . $level . '] ' . $class . ': ' . $msg);
        log_event($domain, $level, $class . ': ' . $msg, $ctx + ['ref' => $ref, 'file' => $file, 'line' => $line]);
        try {
            $sig = hash('sha256', $domain . '|' . $class . '|' . substr($msg, 0, 200) . '|' . $file . '|' . $line);
            $stmt = db()->prepare(
                'INSERT INTO `error_reports` (`signature`, `domain`, `level`, `message`, `file`, `line`)'
                . ' VALUES (?, ?, ?, ?, ?, ?)'
                . ' ON DUPLICATE KEY UPDATE `occurrences` = `occurrences` + 1, `last_seen` = NOW()'
            );
            $stmt->execute([$sig, $domain, $level, substr($class . ': ' . $msg, 0, 500),
                $file !== '' ? substr($file, 0, 255) : null, $line ?: null]);
            if ($stmt->rowCount() === 1 && in_array($level, ['critical'], true) && function_exists('ops_alert')) {
                ops_alert('Oyejo Gas critical error [' . $domain . ']',
                    $ref . ' ' . substr($class . ': ' . $msg, 0, 400));
            }
        } catch (Throwable $t) {
            // Table missing (installer) or DB down: file logs already hold it.
        }
    } catch (Throwable $t) {
        error_log('[oyejo] report_error failed: ' . $t->getMessage());
    }
    return $ref;
}

/** Guess a failure domain for an uncaught Throwable. */
function err_domain_for($e) {
    if ($e instanceof PDOException) {
        return 'database';
    }
    return 'system';
}

/**
 * Render a safe error page and exit. $code in 403/404/419/429/500/503.
 * CLI callers get plain text on stderr instead of HTML.
 */
function show_error($code, $safe_message = null, $ref = null) {
    $code = (int) $code;
    $defaults = [
        403 => 'You do not have permission to view this page.',
        404 => 'We could not find that page.',
        419 => 'This link or session has expired.',
        429 => 'Too many requests. Please slow down and try again.',
        500 => 'Something went wrong on our side.',
        503 => 'Scheduled maintenance is in progress.',
    ];
    if (!isset($defaults[$code])) {
        $code = 500;
    }
    $ref = $ref ?: err_ref();
    $msg = $safe_message !== null && $safe_message !== '' ? (string) $safe_message : $defaults[$code];
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "[$ref] $code $msg\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code($code);
    }
    $p = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/errors/' . $code . '.php';
    if (is_readable($p)) {
        include $p;
    } else {
        echo '<h1>' . $code . '</h1><p>' . htmlspecialchars($msg) . '</p><p>Reference: '
            . htmlspecialchars($ref) . '</p>';
    }
    exit;
}

/** 419 for expired single-use links/sessions. */
function err_419($safe_message = null) {
    $ref = err_ref();
    log_event('auth', 'warning', '419: ' . ($safe_message ?: 'expired link'), ['ref' => $ref]);
    show_error(419, $safe_message, $ref);
}

/** 429 with Retry-After. */
function err_429($retry_after = 60) {
    $retry_after = max(1, min(3600, (int) $retry_after));
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        header('Retry-After: ' . $retry_after);
    }
    $ref = err_ref();
    log_event('system', 'warning', '429 rate limited', ['ref' => $ref, 'retry_after' => $retry_after]);
    show_error(429, null, $ref);
}

/**
 * Fixed-window rate limiter (DB-backed, no raw IPs/emails stored).
 * Returns [allowed, retry_after_seconds]. Fails open when DB is down.
 */
function rate_limit($scope, $key, $max, $window_seconds) {
    $scope = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $scope));
    $max = max(1, (int) $max);
    $window = max(10, (int) $window_seconds);
    try {
        $h = hash('sha256', $scope . '|' . (string) $key);
        $stmt = db()->prepare('SELECT `hits`, `window_start` FROM `rate_limits` WHERE `scope` = ? AND `key_hash` = ?');
        $stmt->execute([$scope, $h]);
        $r = $stmt->fetch();
        $now = time();
        if (!$r || strtotime((string) $r['window_start']) + $window <= $now) {
            db()->prepare(
                'INSERT INTO `rate_limits` (`scope`, `key_hash`, `hits`, `window_start`) VALUES (?, ?, 1, NOW())'
                . ' ON DUPLICATE KEY UPDATE `hits` = 1, `window_start` = NOW()'
            )->execute([$scope, $h]);
            // Opportunistic purge of ancient windows (cheap, indexed).
            if (random_int(1, 100) === 1) {
                db()->prepare('DELETE FROM `rate_limits` WHERE `window_start` < NOW() - INTERVAL 1 DAY')->execute();
            }
            return [true, 0];
        }
        if ((int) $r['hits'] >= $max) {
            return [false, max(1, strtotime((string) $r['window_start']) + $window - $now)];
        }
        db()->prepare('UPDATE `rate_limits` SET `hits` = `hits` + 1 WHERE `scope` = ? AND `key_hash` = ?')
            ->execute([$scope, $h]);
        return [true, 0];
    } catch (Throwable $t) {
        error_log('[oyejo] rate_limit failed open: ' . $t->getMessage());
        return [true, 0];
    }
}

/** Client IP for rate-limit keys (proxy-aware, single value). */
function rate_ip() {
    $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') {
        $parts = explode(',', $fwd);
        $fwd = trim($parts[0]);
        if (filter_var($fwd, FILTER_VALIDATE_IP)) {
            return $fwd;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/* ---------------- log rotation + viewer helpers ---------------- */

function logs_rotate_settings() {
    $mb = function_exists('setting') ? (int) setting('log_max_mb', 5) : 5;
    $keep = function_exists('setting') ? (int) setting('log_keep_files', 5) : 5;
    return [max(1, min(100, $mb)) * 1048576, max(1, min(20, $keep))];
}

function logs_bases() {
    return ['app', 'mail', 'sms', 'whatsapp', 'ops'];
}

/**
 * Size-based rotation: base.log over the limit shifts to .1..N.
 * Returns [files_rotated]. Never throws. $actor null = cron/CLI.
 */
function logs_rotate($actor_id = null) {
    $rotated = 0;
    try {
        [$max_bytes, $keep] = logs_rotate_settings();
        foreach (logs_bases() as $base) {
            $file = LOG_PATH . '/' . $base . '.log';
            if (!is_file($file) || (int) @filesize($file) < $max_bytes) {
                continue;
            }
            for ($i = $keep; $i >= 1; $i--) {
                $src = $i === 1 ? $file : $file . '.' . ($i - 1);
                $dst = $file . '.' . $i;
                if ($i === $keep && is_file($dst)) {
                    @unlink($dst);
                }
                if (is_file($src)) {
                    @rename($src, $dst);
                }
            }
            $rotated++;
        }
        if ($actor_id && function_exists('adm_audit')) {
            adm_audit('ops.log_rotate', (int) $actor_id, null, null, ['rotated' => $rotated]);
        }
    } catch (Throwable $t) {
        error_log('[oyejo] logs_rotate failed: ' . $t->getMessage());
    }
    return [$rotated];
}

/** Whitelisted log files with size/mtime. Never throws. */
function logs_list() {
    $out = [];
    try {
        [$max_bytes, $keep] = logs_rotate_settings();
        foreach (logs_bases() as $base) {
            $names = [$base . '.log'];
            for ($i = 1; $i <= $keep + 2; $i++) {
                $names[] = $base . '.log.' . $i;
            }
            foreach ($names as $n) {
                $f = LOG_PATH . '/' . $n;
                if (is_file($f)) {
                    $out[] = ['name' => $n, 'bytes' => (int) @filesize($f), 'mtime' => (int) @filemtime($f)];
                }
            }
        }
    } catch (Throwable $t) {
        // Return what we have.
    }
    return $out;
}

/** Last $lines lines of a whitelisted log. Returns [lines] or [false, error]. */
function log_tail($name, $lines = 200) {
    $name = (string) $name;
    if (!preg_match('/^(app|mail|sms|whatsapp|ops)\.log(\.[0-9]+)?$/', $name)) {
        return [false, 'Unknown log file.'];
    }
    $f = LOG_PATH . '/' . $name;
    if (!is_file($f)) {
        return [false, 'Log file not found.'];
    }
    $lines = max(10, min(1000, (int) $lines));
    $all = @file($f, FILE_IGNORE_NEW_LINES);
    if ($all === false) {
        return [false, 'Could not read the log file.'];
    }
    return [array_slice($all, -$lines), null];
}

/* ---------------- error report desk helpers ---------------- */

function err_reports($status = 'open', $limit = 100) {
    $sql = 'SELECT r.*, u.`name` AS resolver FROM `error_reports` r LEFT JOIN `users` u ON u.`id` = r.`resolved_by`';
    $args = [];
    if (in_array($status, ['open', 'resolved'], true)) {
        $sql .= ' WHERE r.`status` = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY r.`last_seen` DESC LIMIT ' . max(1, min(500, (int) $limit));
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function err_resolve($id, $actor_id) {
    $stmt = db()->prepare('SELECT `status`, `message` FROM `error_reports` WHERE `id` = ?');
    $stmt->execute([(int) $id]);
    $r = $stmt->fetch();
    if (!$r) {
        return [false, 'Report not found.'];
    }
    if ($r['status'] === 'resolved') {
        return [false, 'Already resolved.'];
    }
    db()->prepare('UPDATE `error_reports` SET `status` = ?, `resolved_by` = ?, `resolved_at` = NOW() WHERE `id` = ?')
        ->execute(['resolved', $actor_id ?: null, (int) $id]);
    if (function_exists('adm_audit')) {
        adm_audit('ops.error_resolve', (int) $actor_id, (int) $id, ['status' => 'open'], ['status' => 'resolved']);
    }
    return [true, 'Marked resolved.'];
}
