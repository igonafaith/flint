<?php
/**
 * Layer 4 — Security Test Harness
 * Jalankan: php tests/test_runner.php
 * 28 test, fokus security primitives. Output pass/fail per test.
 */

$root = dirname(__DIR__);

// Mulai session sebelum output apapun untuk menghindari "headers already sent"
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $root . '/escape.php';
require_once $root . '/csrf.php';
require_once $root . '/db.php';
require_once $root . '/session.php';
require_once $root . '/password.php';
require_once $root . '/rate_guard.php';
require_once $root . '/headers.php';
require_once $root . '/response.php';
require_once $root . '/config.php';

// -------------------------------------------------------------------------
// Test framework minimal
// -------------------------------------------------------------------------

$pass = 0;
$fail = 0;

function assert_equals(string $test_name, mixed $expected, mixed $actual): void
{
    global $pass, $fail;
    if ($expected === $actual) {
        echo "[PASS] $test_name\n";
        $pass++;
    } else {
        echo "[FAIL] $test_name\n";
        echo "       Expected: " . var_export($expected, true) . "\n";
        echo "       Got:      " . var_export($actual, true) . "\n";
        $fail++;
    }
}

function assert_true(string $test_name, bool $condition): void
{
    assert_equals($test_name, true, $condition);
}

function assert_false(string $test_name, bool $condition): void
{
    assert_equals($test_name, false, $condition);
}

function assert_contains(string $test_name, string $needle, string $haystack): void
{
    global $pass, $fail;
    if (str_contains($haystack, $needle)) {
        echo "[PASS] $test_name\n";
        $pass++;
    } else {
        echo "[FAIL] $test_name\n";
        echo "       Expected to contain: $needle\n";
        echo "       In: $haystack\n";
        $fail++;
    }
}

// -------------------------------------------------------------------------
// Layer 1: escape.php
// -------------------------------------------------------------------------

assert_equals(
    'e() escapes <script> tag',
    '&lt;script&gt;',
    e('<script>')
);

assert_equals(
    'e() escapes double quote',
    '&quot;',
    e('"')
);

assert_equals(
    'attr() escapes single quote',
    '&#039;',
    attr("'")
);

assert_contains(
    'je() hex-encodes < untuk cegah breakout dari script block',
    '\u003C',
    je('<div>')
);

assert_contains(
    'je() hex-encodes & untuk cegah HTML injection',
    '\u0026',
    je('a&b')
);

// -------------------------------------------------------------------------
// Layer 1: csrf.php
// -------------------------------------------------------------------------

// Session sudah dimulai di awal file — cukup reset state

// Reset state
unset($_SESSION['_csrf_token']);
unset($_POST['_token']);

assert_false(
    'csrf_verify() gagal tanpa token di session atau POST',
    csrf_verify()
);

// Generate token
$token = csrf_token();
assert_true(
    'csrf_token() menghasilkan string non-empty',
    strlen($token) > 0
);

$_POST['_token'] = $token;
assert_true(
    'csrf_verify() sukses dengan token yang benar',
    csrf_verify()
);

$_POST['_token'] = 'token-salah-' . $token;
assert_false(
    'csrf_verify() gagal dengan token yang salah',
    csrf_verify()
);

// Cleanup
unset($_POST['_token']);

// -------------------------------------------------------------------------
// Layer 1: db.php
// -------------------------------------------------------------------------

// Gunakan SQLite in-memory untuk tes — pass DSN langsung ke db()
db('sqlite::memory:');

try {
    db_query('CREATE TABLE test_user (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');

    $id = db_insert('INSERT INTO test_user (name) VALUES (?)', ['Alice']);
    assert_true('db_insert() return last insert ID', $id > 0);

    $row = db_one('SELECT name FROM test_user WHERE id = ?', [$id]);
    assert_equals('db_one() ambil row yang benar', 'Alice', $row['name'] ?? null);

    $all = db_all('SELECT name FROM test_user');
    assert_equals('db_all() return semua rows', 1, count($all));

    echo "[PASS] db_query() dengan binding berjalan\n";
    global $pass; $pass++;
} catch (\Throwable $e) {
    echo "[FAIL] db_query() error: " . $e->getMessage() . "\n";
    global $fail; $fail++;
}

// -------------------------------------------------------------------------
// Layer 1: password.php
// -------------------------------------------------------------------------

$hash = pw_hash('secret123');

assert_true(
    'pw_verify() match dengan password yang benar',
    pw_verify('secret123', $hash)
);

assert_false(
    'pw_verify() mismatch dengan password yang salah',
    pw_verify('wrong_password', $hash)
);

$bcrypt_hash = password_hash('oldpassword', PASSWORD_BCRYPT);
assert_true(
    'pw_needs_rehash() return true kalau hash pakai algorithm lama (bcrypt) dan Argon2id tersedia',
    defined('PASSWORD_ARGON2ID') ? pw_needs_rehash($bcrypt_hash) : true
);

// -------------------------------------------------------------------------
// Layer 1: rate_guard.php
// -------------------------------------------------------------------------

// Pakai key unik untuk tes supaya tidak conflict dengan state lain
$rate_key = 'test:' . uniqid();

assert_true('rate_check() allow request pertama', rate_check($rate_key, 3, 60));
assert_true('rate_check() allow request kedua', rate_check($rate_key, 3, 60));
assert_true('rate_check() allow request ketiga', rate_check($rate_key, 3, 60));
assert_false('rate_check() block setelah limit tercapai', rate_check($rate_key, 3, 60));

// -------------------------------------------------------------------------
// Layer 1: headers.php
// -------------------------------------------------------------------------

// Tangkap headers yang akan di-set (hanya bisa di-check kalau headers belum dikirim)
if (!headers_sent()) {
    apply_security_headers();
    $headers_list = headers_list();
    $headers_str  = implode("\n", $headers_list);

    assert_contains(
        'apply_security_headers() set X-Content-Type-Options',
        'X-Content-Type-Options: nosniff',
        $headers_str
    );

    assert_contains(
        'apply_security_headers() set X-Frame-Options',
        'X-Frame-Options: DENY',
        $headers_str
    );

    assert_contains(
        'apply_security_headers() set Referrer-Policy',
        'Referrer-Policy: strict-origin-when-cross-origin',
        $headers_str
    );

    assert_contains(
        'apply_security_headers() set Content-Security-Policy',
        'Content-Security-Policy:',
        $headers_str
    );
} else {
    echo "[SKIP] Header tests — headers sudah dikirim\n";
}

// -------------------------------------------------------------------------
// Layer 3: response.php — redirect URL validation
// -------------------------------------------------------------------------

assert_false(
    'redirect() _is_external_url() deteksi URL eksternal',
    _is_external_url('/')
);

assert_false(
    '_is_external_url() terima path relatif',
    _is_external_url('/dashboard')
);

assert_true(
    '_is_external_url() tolak URL eksternal',
    _is_external_url('https://babi.com/phishing')
);

assert_true(
    '_is_external_url() tolak protocol-relative URL (//babi.com)',
    _is_external_url('//babi.com/phishing')
);

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------

echo "\n";
echo "Results: {$pass} passed, {$fail} failed\n";

if ($fail > 0) {
    exit(1);
}
