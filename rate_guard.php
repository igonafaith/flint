<?php
/**
 * Layer 1.6 — Rate Limiter
 * APCu sebagai primary storage, file-based sebagai fallback.
 * Key convention: "login:{$ip}", "api:{$token}", "csrf_fail:{$ip}"
 */

define('RATE_GUARD_FILE_DIR',
    (function_exists('config') ? config('RATE_GUARD_FILE_DIR', '') : '')
    ?: sys_get_temp_dir() . '/rate_guard'
);

/**
 * Cek apakah request masih dalam batas rate limit.
 *
 * @param string $key           Identifier unik, misal "login:192.168.1.1"
 * @param int    $max           Maksimum request yang diizinkan dalam window
 * @param int    $window_seconds Durasi window dalam detik
 * @return bool  true kalau masih dalam limit, false kalau sudah melebihi
 */
function rate_check(string $key, int $max, int $window_seconds): bool
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        return _rate_check_apcu($key, $max, $window_seconds);
    }
    return _rate_check_file($key, $max, $window_seconds);
}

function _rate_check_apcu(string $key, int $max, int $window_seconds): bool
{
    // apcu_add returns true only on first set — atomic check-and-init
    if (apcu_add($key, 1, $window_seconds)) {
        return true; // request pertama dalam window
    }
    // apcu_inc returns new value after increment — still not perfectly atomic
    // tapi jauh lebih baik dari fetch→check→inc terpisah
    $new_count = apcu_inc($key);
    return $new_count !== false && $new_count <= $max;
}

function _rate_check_file(string $key, int $max, int $window_seconds): bool
{
    if (!is_dir(RATE_GUARD_FILE_DIR)) {
        mkdir(RATE_GUARD_FILE_DIR, 0700, true);
    }

    $safe_key = hash('sha256', $key);
    $file = RATE_GUARD_FILE_DIR . '/' . $safe_key . '.json';
    $now  = time();

    $fp = fopen($file, 'c+');
    if ($fp === false) {
        return true; // fail open: jangan block request kalau storage error
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return true; // fail open
    }

    $raw = stream_get_contents($fp);
    $data = ['count' => 0, 'expires' => $now + $window_seconds];

    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $decoded['expires'] > $now) {
            $data = $decoded;
        }
    }

    $allowed = $data['count'] < $max;

    if ($allowed) {
        $data['count']++;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}
