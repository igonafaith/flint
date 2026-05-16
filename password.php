<?php
/**
 * Layer 1.5 — Password Hashing
 * Menggunakan Argon2id sebagai algoritma utama, bcrypt sebagai fallback.
 */

/**
 * Hash password menggunakan Argon2id (fallback bcrypt jika tidak tersedia).
 */
function pw_hash(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    error_log('[WARNING] Argon2id tidak tersedia, menggunakan bcrypt sebagai fallback.');
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verifikasi password terhadap hash yang tersimpan.
 */
function pw_verify(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Cek apakah hash perlu di-rehash (misal karena algorithm berubah).
 * Panggil setiap login sukses. Kalau true, re-hash dan update DB.
 */
function pw_needs_rehash(string $hash): bool
{
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    return password_needs_rehash($hash, $algorithm);
}
