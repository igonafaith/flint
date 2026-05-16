<?php
/**
 * Layer 1.7 — Security Headers
 * Panggil apply_security_headers() sekali di bootstrap.
 */

/**
 * Set semua security headers sekaligus.
 * CSP bisa di-override per-route untuk kasus yang butuh inline script.
 *
 * @param array $csp_override  Jika diisi, override default CSP directives.
 *                             Contoh: ['script-src' => "'self' https://cdn.example.com"]
 */
function apply_security_headers(array $csp_override = []): void
{
    // Cegah double-send: header duplikat bisa menimpa kebijakan keamanan yang sudah di-set.
    static $applied = false;
    if (!$applied) {
        $applied = true;
    } else {
        return;
    }

    // Content-Security-Policy
    $csp_directives = array_merge([
        'default-src' => "'self'",
        'script-src'  => "'self'",
        'style-src'   => "'self'",
        'img-src'     => "'self' data:",
        'font-src'    => "'self'",
        'connect-src' => "'self'",
        'frame-ancestors' => "'none'",
        'base-uri'    => "'self'",
        'form-action' => "'self'",
    ], $csp_override);

    $csp_parts = [];
    foreach ($csp_directives as $directive => $value) {
        $csp_parts[] = $directive . ' ' . $value;
    }
    header('Content-Security-Policy: ' . implode('; ', $csp_parts));

    // Standard security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // HSTS hanya kalau HTTPS — aware terhadap Cloudflare dan Nginx proxy
    if (_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
