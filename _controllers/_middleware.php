<?php
/**
 * Application-specific middleware functions.
 * Di-include oleh bootstrap.php sebelum route dispatch.
 *
 * Untuk menambah middleware baru, tambah fungsi di sini dan
 * daftarkan di $middleware_groups di bootstrap.php.
 */

/**
 * Verifikasi CSRF token untuk request POST/PUT/PATCH/DELETE.
 * Return false (dan kirim 403) kalau token tidak valid.
 */
function csrf_check_post(): bool
{
    // Gunakan method() helper agar _method override (form spoofing) tidak bypass CSRF check.
    $method = method();

    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return true;
    }

    if (!csrf_verify()) {
        http_response_code(403);
        echo e('403 Forbidden — CSRF token mismatch.');
        return false;
    }

    return true;
}

/**
 * API authentication via Bearer token.
 * Ganti implementasi sesuai kebutuhan project (DB lookup, JWT, dsb).
 * Return false (dan kirim 401) kalau token tidak valid.
 */
function api_auth(): bool
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($auth, 'Bearer ') !== 0) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
        return false;
    }
    return true;
}

/**
 * Rate limiting untuk API endpoint.
 * Return false (dan kirim 429) kalau limit tercapai.
 */
function api_rate_check(): bool
{
    $key = 'api:' . ip();

    if (!rate_check($key, 60, 60)) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Too Many Requests'], JSON_UNESCAPED_UNICODE);
        return false;
    }

    return true;
}
