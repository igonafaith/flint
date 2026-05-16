<?php
/**
 * Layer 3.4 — Middleware Pipeline
 * Tiga hook: before, handle, after.
 * Kalau before() return false, pipeline berhenti (response sudah dikirim).
 *
 * Definisi middleware groups ada di project, bukan di sini.
 * Contoh penggunaan di index.php / bootstrap:
 *
 *   $groups = [
 *       'web'  => ['session_boot', 'csrf_check_post'],
 *       'api'  => ['api_auth', 'api_rate_check'],
 *       'open' => [],
 *   ];
 *   middleware_run('web', fn() => route_dispatch($routes), $groups);
 */

/**
 * Jalankan middleware pipeline untuk satu group.
 *
 * @param string   $group    Nama group ('web', 'api', 'open', ...)
 * @param callable $handler  Closure yang berisi route dispatch / controller call
 * @param array    $groups   Definisi group → array of callable names. Dibawa dari luar.
 */
function middleware_run(string $group, callable $handler, array $groups = []): void
{
    $middlewares = $groups[$group] ?? [];

    // before: jalankan semua middleware, hentikan kalau salah satu return false
    foreach ($middlewares as $mw) {
        if (function_exists($mw)) {
            $result = $mw();
            if ($result === false) {
                return;
            }
        }
    }

    // handle
    $handler();

    // after: logging, cleanup
    _middleware_after();
}

/**
 * Hook after: logging request dan cleanup.
 */
function _middleware_after(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri    = rawurldecode($_SERVER['REQUEST_URI'] ?? '/');
    $status = http_response_code();
    $ip     = function_exists('ip') ? ip() : ($_SERVER['REMOTE_ADDR'] ?? '-');

    if (function_exists('log_write')) {
        // Static asset requests di-log di debug level agar tidak membanjiri log production.
        static $static_prefixes = ['/assets/', '/favicon.ico', '/robots.txt'];
        $is_static = false;
        foreach ($static_prefixes as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix)) {
                $is_static = true;
                break;
            }
        }
        $log_level = $is_static ? 'debug' : 'info';
        log_write($log_level, $method . ' ' . $uri . ' ' . $status, ['ip' => $ip]);
    }

    // Flush response ke klien, lanjutkan background task kalau ada
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
