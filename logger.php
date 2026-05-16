<?php
/**
 * Layer 2.3 — Logger
 * Interface PSR-3 compatible, implementasi sendiri.
 * Format: [2026-05-13T14:30:00+07:00] [ERROR] [req:abc123] Message {context_json}
 */

const LOG_LEVELS = [
    'debug'     => 0,
    'info'      => 1,
    'notice'    => 2,
    'warning'   => 3,
    'error'     => 4,
    'critical'  => 5,
    'alert'     => 6,
    'emergency' => 7,
];

function _log_dir(): string
{
    return defined('LOG_PATH') ? LOG_PATH : __DIR__ . '/_storage/logs';
}

function _log_threshold(): int
{
    $level = defined('LOG_LEVEL') ? LOG_LEVEL : (config('log_level', 'warning'));
    return LOG_LEVELS[strtolower($level)] ?? LOG_LEVELS['warning'];
}

/**
 * Tulis log entry ke file harian.
 */
function log_write(string $level, string $message, array $context = []): void
{
    $level_int = LOG_LEVELS[strtolower($level)] ?? LOG_LEVELS['error'];

    if ($level_int < _log_threshold()) {
        return;
    }

    $dir = _log_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $date     = date('Y-m-d');
    $log_file = $dir . '/app-' . $date . '.log';

    $req_id    = function_exists('_request_id') ? _request_id() : '-';
    $timestamp = date('c');
    $ctx_json  = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $line      = '[' . $timestamp . '] [' . strtoupper($level) . '] [req:' . $req_id . '] '
               . $message . $ctx_json . PHP_EOL;

    file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX);

    // Daftarkan cleanup sekali saja di shutdown, bukan di hot path setiap write.
    static $cleanup_registered = false;
    if (!$cleanup_registered) {
        $cleanup_registered = true;
        $log_dir = $dir; // capture by value untuk closure
        register_shutdown_function(function () use ($log_dir): void {
            _log_cleanup($log_dir);
        });
    }
}

/**
 * Hapus log file yang melebihi retention period.
 * Lazy-check: hanya jalan kalau file flag sudah lebih dari 1 jam.
 */
function _log_cleanup(string $dir): void
{
    $flag = $dir . '/.last_cleanup';
    if (file_exists($flag) && (time() - filemtime($flag)) < 3600) {
        return;
    }

    touch($flag);
    $retention = defined('LOG_RETENTION_DAYS')
        ? (int) LOG_RETENTION_DAYS
        : (int) config('log_retention_days', 30);

    $cutoff = time() - ($retention * 86400);

    foreach (glob($dir . '/app-*.log') as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

// PSR-3 compatible shorthand functions

function log_emergency(string $message, array $context = []): void  { log_write('emergency', $message, $context); }
function log_alert(string $message, array $context = []): void      { log_write('alert', $message, $context); }
function log_critical(string $message, array $context = []): void   { log_write('critical', $message, $context); }
function log_error(string $message, array $context = []): void      { log_write('error', $message, $context); }
function log_warning(string $message, array $context = []): void    { log_write('warning', $message, $context); }
function log_notice(string $message, array $context = []): void     { log_write('notice', $message, $context); }
function log_info(string $message, array $context = []): void       { log_write('info', $message, $context); }
function log_debug(string $message, array $context = []): void      { log_write('debug', $message, $context); }
