<?php
/**
 * Layer 2.1 — Config Loader
 * Dot-notation getter untuk konfigurasi dari _config/.env
 */

/**
 * Parse file .env ke associative array.
 */
function _config_parse(string $path): array
{
    $result = [];

    if (!file_exists($path)) {
        return $result;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip komentar
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $eq_pos = strpos($line, '=');
        if ($eq_pos === false) {
            continue;
        }

        $key   = trim(substr($line, 0, $eq_pos));
        $value = trim(substr($line, $eq_pos + 1));

        // Strip quote dulu — quoted hanya valid kalau karakter opening & closing SAMA.
        // Kasus malformed seperti `"hello" world"` atau `'val"` diperlakukan sebagai unquoted.
        $is_quoted = false;
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[-1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                // Pastikan tidak ada quote penutup yang sama di tengah (unescaped)
                // sebelum posisi terakhir — kalau ada, ini malformed → treat as unquoted
                $inner = substr($value, 1, -1);
                if (strpos($inner, $first) === false) {
                    $value    = $inner;
                    $is_quoted = true;
                }
            }
        }

        if (!$is_quoted) {
            // Hanya strip inline comment untuk unquoted values
            if (($hash_pos = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $hash_pos));
            }
        }

        $result[strtoupper($key)] = $value;
    }

    return $result;
}

/**
 * Load dan cache config dari .env.
 */
function _config_load(): array
{
    static $cache = null;

    if ($cache === null) {
        $env_path = defined('CONFIG_PATH')
            ? CONFIG_PATH
            : dirname(__DIR__) . '/_config/.env';

        // Fallback ke direktori yang sama
        if (!file_exists($env_path)) {
            $env_path = __DIR__ . '/_config/.env';
        }

        $cache = _config_parse($env_path);
    }

    return $cache;
}

/**
 * Ambil config dengan dot-notation. Contoh: config('db.path')
 * Untuk .env flat key, gunakan underscore: config('DB_DSN') atau config('db_dsn')
 *
 * Override seam untuk testing: panggil config('', null, ['KEY' => 'value']) sekali
 * untuk mengganti config, config('', null, []) untuk reset ke .env.
 * Bisa juga gunakan named argument: config(override: ['KEY' => 'val']).
 *
 * CATATAN: config_require() menggunakan _config_load() langsung dan TIDAK melihat
 * test_overrides. Test suite sebaiknya tidak memanggil config_require().
 */
function config(string $key = '', mixed $default = null, ?array $override = null): mixed
{
    static $test_overrides = null;

    // Set atau reset test override (dipanggil hanya dari test harness)
    if ($override !== null) {
        $test_overrides = $override;
    }

    if ($key === '') {
        return null;
    }

    $upper_key = strtoupper(str_replace('.', '_', $key));

    if ($test_overrides !== null && array_key_exists($upper_key, $test_overrides)) {
        return $test_overrides[$upper_key];
    }

    $cfg = _config_load();
    return $cfg[$upper_key] ?? $default;
}

/**
 * Validasi key wajib ada. Panggil di bootstrap.
 * Kalau ada key yang missing, fatal error dengan pesan jelas.
 *
 * CATATAN: Fungsi ini memanggil _config_load() langsung, bukan config().
 * Test overrides yang di-set via config(override: [...]) TIDAK terlihat di sini.
 * Bootstrap yang memanggil config_require() tidak boleh dijalankan dalam test
 * tanpa .env yang valid.
 */
function config_require(array $keys): void
{
    $cfg = _config_load();
    $missing = [];

    foreach ($keys as $key) {
        $upper_key = strtoupper(str_replace('.', '_', $key));
        if (!isset($cfg[$upper_key]) || $cfg[$upper_key] === '') {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        $list = implode(', ', $missing);
        http_response_code(500);
        exit('Missing required config: ' . $list);
    }
}
