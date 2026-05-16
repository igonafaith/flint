<?php
/**
 * bootstrap.php — Entry point. Include sekali di index.php.
 *
 * Urutan include penting:
 *   1. config.php  — harus paling awal, semua modul lain bisa pakai config()
 *   2. logger.php  — sebelum error.php karena error handler memanggil log_write()
 *   3. error.php   — daftarkan handler sebelum kode apapun berjalan
 *   4. escape.php  — sebelum response/view karena view pakai e()
 *   5. db.php      — sebelum session karena session bisa butuh DB
 *   6. session.*   — sebelum middleware karena middleware jalankan session_boot()
 *   7. sisanya     — urutan tidak kritikal setelah 1–6 terpenuhi
 */

// -------------------------------------------------------------------------
// 1. Config — harus paling awal
// -------------------------------------------------------------------------
require_once __DIR__ . '/config.php';

config_require(['APP_KEY', 'DB_DSN', 'SESSION_SALT']);

// -------------------------------------------------------------------------
// 2. Logger — sebelum error handler
// -------------------------------------------------------------------------
require_once __DIR__ . '/logger.php';

// -------------------------------------------------------------------------
// 3. Error handler — segera setelah logger siap
// -------------------------------------------------------------------------
require_once __DIR__ . '/error.php';

error_boot(config('APP_DEBUG') === 'true');

// -------------------------------------------------------------------------
// 4. Security primitives
// -------------------------------------------------------------------------
require_once __DIR__ . '/escape.php';
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/password.php';
require_once __DIR__ . '/rate_guard.php';

// -------------------------------------------------------------------------
// 5. Database
// -------------------------------------------------------------------------
require_once __DIR__ . '/db.php';

// -------------------------------------------------------------------------
// 6. Session & CSRF
// -------------------------------------------------------------------------
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

// -------------------------------------------------------------------------
// 7. HTTP layer
// -------------------------------------------------------------------------
// PENTING: request.php wajib di-load SEBELUM session_boot() atau
// apply_security_headers() dipanggil (keduanya depend pada _is_https() dari request.php).
require_once __DIR__ . '/request.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/middleware.php';

// -------------------------------------------------------------------------
// 8. Application middleware (project-specific)
// -------------------------------------------------------------------------
require_once __DIR__ . '/_controllers/_middleware.php';

// -------------------------------------------------------------------------
// 9. Utilities
// -------------------------------------------------------------------------
require_once __DIR__ . '/deprecation.php';

// -------------------------------------------------------------------------
// Middleware group definitions — definisi ada di sini, bukan di middleware.php
// -------------------------------------------------------------------------
$middleware_groups = [
    'web'  => ['session_boot', 'csrf_check_post'],
    'api'  => ['api_auth', 'api_rate_check'],
    'open' => [],  // no middleware (health check, public assets)
];
