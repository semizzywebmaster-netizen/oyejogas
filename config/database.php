<?php
/**
 * Oyejo Gas - database connection (PDO, MySQL).
 * Phase 3 creates the schema; Phase 4 wires the installer.
 */
if (!defined('OYEJO_BOOT')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Shared PDO instance (lazy - no connection until first use).
 */
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    try {
        // Align MySQL NOW()/CURDATE() with PHP wall time so SQL and PHP
        // timestamps are comparable (Phase 22 fix: retry/backoff windows,
        // rate limits, campaign windows). Offset form needs no tz tables.
        $tz = new DateTimeZone(date_default_timezone_get());
        $off = $tz->getOffset(new DateTime('now', $tz));
        $pdo->exec(sprintf(
            "SET time_zone = '%s%02d:%02d'",
            $off < 0 ? '-' : '+',
            abs($off) / 3600,
            (abs($off) % 3600) / 60
        ));
    } catch (Throwable $t) {
        // Non-fatal: the server time zone stands in.
    }
    return $pdo;
}

/** True when a query round-trip succeeds (used by health checks). */
function db_available() {
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $t) {
        return false;
    }
}
