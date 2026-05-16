<?php
/**
 * Layer 3.2 — Request Helper
 * Semua input lewat fungsi ini, bukan akses langsung ke superglobal.
 */

/**
 * Ambil nilai dari POST atau GET (POST priority). Sudah di-trim.
 */
function input(string $key, mixed $default = null): mixed
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;

    if ($value === null) {
        return $default;
    }

    if (is_string($value)) {
        return trim($value);
    }

    return $value;
}

/**
 * Ambil nilai dan cast ke int. Untuk ID dan pagination.
 */
function input_int(string $key, int $default = 0): int
{
    $value = input($key);
    return $value !== null ? (int) $value : $default;
}

/**
 * Ambil array input (checkbox, multi-select).
 */
function input_array(string $key): array
{
    $value = $_POST[$key] ?? $_GET[$key] ?? [];
    return is_array($value) ? $value : [];
}

/**
 * HTTP method, support override via _method field (untuk PUT/DELETE dari HTML form).
 */
function method(): string
{
    $real = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($real === 'POST') {
        $override = strtoupper(trim($_POST['_method'] ?? ''));
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $override;
        }
    }

    return $real;
}

/**
 * Cek apakah request dari AJAX (via X-Requested-With header).
 */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/**
 * Ambil IP address klien.
 * Forwarded headers (CF-Connecting-IP, X-Forwarded-For) hanya dipercaya
 * kalau REMOTE_ADDR ada dalam daftar TRUSTED_PROXIES di config.
 * Tanpa itu, siapapun bisa spoof header ini untuk bypass rate limiter.
 */
function ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Bangun daftar trusted proxy dari config
    $trusted_raw = function_exists('config') ? config('TRUSTED_PROXIES', '') : '';
    $trusted = array_filter(array_map('trim', explode(',', (string) $trusted_raw)));

    if (empty($trusted) || !in_array($remote, $trusted, true)) {
        return $remote;
    }

    // Cloudflare — CF-Connecting-IP
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf_ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cf_ip, FILTER_VALIDATE_IP)) {
            return $cf_ip;
        }
    }

    // Nginx / load balancer — X-Forwarded-For, ambil entry pertama
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $first = trim($forwarded[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return $remote;
}

/**
 * Deteksi apakah request datang via HTTPS.
 * Aware terhadap Cloudflare (CF-Visitor) dan Nginx/load balancer (X-Forwarded-Proto).
 * Dipakai oleh session.php dan headers.php — definisi di satu tempat.
 */
function _is_https(): bool
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || str_contains($_SERVER['HTTP_CF_VISITOR'] ?? '', '"https"');
}
