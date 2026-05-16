<?php
/**
 * Layer 1.2 — CSRF Token Helper
 * Token per-session (bukan per-request) untuk kompatibilitas multi-tab.
 */

/**
 * Pastikan session sudah dimulai sebelum memanggil fungsi ini.
 */
function _csrf_ensure_token(): void
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Return hidden input field berisi CSRF token.
 */
function csrf_field(): string
{
    _csrf_ensure_token();
    $token = e($_SESSION['_csrf_token']);
    return '<input type="hidden" name="_token" value="' . $token . '">';
}

/**
 * Return raw token string, untuk AJAX yang perlu set header manual.
 */
function csrf_token(): string
{
    _csrf_ensure_token();
    return $_SESSION['_csrf_token'];
}

/**
 * Verifikasi CSRF token dari POST field atau header X-CSRF-Token.
 * Menggunakan hash_equals() untuk timing-safe comparison.
 * Return false kalau tidak match — caller yang decide response (403/redirect).
 */
function csrf_verify(): bool
{
    _csrf_ensure_token();

    $submitted = $_POST['_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!is_string($submitted) || $submitted === '') {
        return false;
    }

    return hash_equals($_SESSION['_csrf_token'], $submitted);
}
