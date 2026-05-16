<?php
/**
 * Layer 3.3 — Response Helper
 */

/**
 * Kirim response dengan body, status code, dan custom headers.
 * Otomatis memanggil apply_security_headers().
 *
 * CATATAN: Panggil fungsi response (respond/json/view/redirect) tepat SEKALI per request.
 * Double-call akan menghasilkan output ganda; PHP tidak mencegah hal ini.
 */
function respond(string $body, int $code = 200, array $headers = []): void
{
    http_response_code($code);
    apply_security_headers();

    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }

    echo $body;
}

/**
 * Kirim JSON response.
 */
function json(mixed $data, int $code = 200): void
{
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    respond($body, $code, ['Content-Type' => 'application/json; charset=utf-8']);
}

/**
 * Redirect ke URL lain.
 * Secara default menolak redirect ke URL eksternal (anti open redirect).
 *
 * @param string $url           URL tujuan
 * @param int    $code          HTTP status (301, 302, dst)
 * @param bool   $allow_external Izinkan redirect ke URL eksternal (default: false)
 */
function redirect(string $url, int $code = 302, bool $allow_external = false): void
{
    if (!$allow_external && _is_external_url($url)) {
        http_response_code(400);
        exit('Invalid redirect target.');
    }

    http_response_code($code);
    header('Location: ' . $url);
    exit;
}

/**
 * Render PHP template dan kirim sebagai response.
 *
 * @param string $template  Nama template relatif ke _views/, tanpa ekstensi .php
 * @param array  $data      Variabel yang di-extract ke dalam template
 */
function view(string $template, array $data = []): void
{
    $template_file = __DIR__ . '/_views/' . $template . '.php';

    if (!file_exists($template_file)) {
        http_response_code(500);
        exit('View not found: ' . e($template));
    }

    extract($data, EXTR_SKIP);

    ob_start();
    include $template_file;
    $body = ob_get_clean();

    respond($body);
}

/**
 * Cek apakah URL adalah URL eksternal (bukan path relatif atau domain yang sama).
 */
function _is_external_url(string $url): bool
{
    // Path relatif aman — tapi // adalah protocol-relative URL dan harus ditolak
    if ($url === '' || ($url[0] === '/' && ($url[1] ?? '') !== '/')) {
        return false;
    }

    $parsed = parse_url($url);

    // Protocol-relative URL (//host/path) tidak punya scheme tapi punya host — external
    if (!isset($parsed['scheme'])) {
        return isset($parsed['host']);
    }

    $host         = strtolower($parsed['host'] ?? '');
    // Strip port dari keduanya: HTTP_HOST bisa berisi 'example.com:8080'
    // sementara parse_url() hanya mengembalikan hostname tanpa port.
    $current_host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);

    return $host !== $current_host;
}
