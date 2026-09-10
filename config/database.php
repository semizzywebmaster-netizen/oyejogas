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
