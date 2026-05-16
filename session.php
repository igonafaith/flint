<?php
/**
 * Layer 1.4 — Session Bootstrap
 * Konfigurasi sesi yang aman. Panggil session_boot() sekali di bootstrap.
 */

/**
 * Inisialisasi sesi dengan setting keamanan yang ketat.
 */
function session_boot(): void
{
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_lifetime', '0');

    if (_is_https()) {
        ini_set('session.cookie_secure', '1');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Regenerasi session ID. Panggil setiap auth state change (login, logout, privilege escalation).
 */
function session_regen(): void
{
    session_regenerate_id(true);
}

/**
 * Ikat session ke browser fingerprint.
 * Cek dijalankan setiap request setelah session_boot().
 *
 * Fingerprint terdiri dari:
 *  - User-Agent (diisi browser; bisa di-spoof, tapi tetap menaikkan bar)
 *  - Accept-Language (locale header; cukup stabil per-user)
 *  - /24 subnet IPv4 atau /48 prefix IPv6 (toleransi NAT/mobile handoff)
 *
 * PERINGATAN: Semua faktor ini bisa di-spoof oleh attacker yang sudah punya
 * akses ke request asli (misalnya via MITM). Ini bukan pengganti full
 * token rotation / re-authentication. Anggap sebagai lapisan defence-in-depth.
 *
 * @param string $salt      Secret salt spesifik aplikasi.
 * @param string $redirect  URL redirect kalau fingerprint mismatch.
 */
function session_fingerprint(string $salt, string $redirect = '/'): void
{
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    // Ambil subnet dari IP: /24 untuk IPv4, /48 untuk IPv6.
    // Full IPv6 address terlalu strict karena privacy extensions / mobile handoff sering ganti alamat.
    $ip_raw = function_exists('ip') ? ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip_raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts  = explode('.', $ip_raw);
        $subnet = $parts[0] . '.' . $parts[1] . '.' . $parts[2]; // /24
    } elseif (filter_var($ip_raw, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // Gunakan binary representation agar hasil selalu 32 hex chars (normalized),
        // terlepas dari compressed form (::1, 2001:db8::1, dst).
        // str_split ke 4 chars = 8 groups of 16-bit. 3 pertama = /48 prefix.
        $bin = inet_pton($ip_raw);
        if ($bin !== false) {
            $groups = str_split(bin2hex($bin), 4);
            $subnet = $groups[0] . ':' . $groups[1] . ':' . $groups[2];
        } else {
            $subnet = '';
        }
    } else {
        $subnet = ''; // format tidak dikenal, jangan masukkan ke fingerprint
    }

    $fingerprint = hash('sha256', $ua . '|' . $lang . '|' . $subnet . '|' . $salt);

    if (!isset($_SESSION['_fingerprint'])) {
        $_SESSION['_fingerprint'] = $fingerprint;
        return;
    }

    if (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
        session_destroy();
        header('Location: ' . $redirect);
        exit;
    }
}
