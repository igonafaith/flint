<?php
/**
 * Layer 2.5 — Health Check Endpoint
 * Route: /_health
 * Return JSON status setiap komponen. HTTP 200 kalau semua pass, 503 kalau ada yang fail.
 * Tidak expose detail sistem (PHP version, path, dsb).
 */

function health_check(): void
{
    // Rate limit: max 10 request per 60 detik per IP
    if (function_exists('rate_check') && function_exists('ip') && !rate_check('health:' . ip(), 10, 60)) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Too Many Requests'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $checks = [
        'db'    => _health_check_db(),
        'disk'  => _health_check_disk(),
        'cache' => _health_check_cache(),
    ];

    $all_ok = !in_array(false, $checks, true);
    $code   = $all_ok ? 200 : 503;

    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode([
        'status'    => $all_ok ? 'ok' : 'degraded',
        'timestamp' => date('c'),
        'checks'    => $checks,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function _health_check_db(): bool
{
    if (!function_exists('db_query')) {
        return false;
    }
    try {
        db_query('SELECT 1');
        return true;
    } catch (\Throwable $e) {
        if (function_exists('log_write')) {
            log_write('warning', 'Health check DB failed: ' . $e->getMessage());
        }
        return false;
    }
}

function _health_check_disk(): bool
{
    $dir = defined('STORAGE_PATH') ? STORAGE_PATH : __DIR__ . '/_storage';
    return is_dir($dir) && is_writable($dir);
}

function _health_check_cache(): bool
{
    if (!function_exists('apcu_enabled')) {
        return true; // APCu tidak dipakai, skip check
    }
    return apcu_enabled();
}
