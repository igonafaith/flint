# Flint

Framework PHP native minimal berlapis 4, dibangun tanpa dependency eksternal. Setiap lapisan punya tanggung jawab yang jelas dan bisa di-audit baris per baris.

**Current status:** Experimental Only

---

## Filosofi

- **Zero dependency** — tidak ada Composer package, tidak ada framework. Semua bisa di-grep.
- **Explicit over magic** — tidak ada annotation, tidak ada auto-wiring, tidak ada konvensi tersembunyi.
- **Security by default** — output selalu di-escape, query selalu di-bind, session selalu aman.
- **Grep-able** — setiap keputusan ada di satu tempat yang bisa ditemukan dengan `grep`.

---

## Struktur Direktori

```
.
├── _config/                 # Konfigurasi — DI LUAR document root
│   └── .env.example
├── _controllers/            # Handler per resource
│   └── _middleware.php      # Middleware fungsi app-specific
├── _storage/
│   ├── logs/                # Log harian (app-YYYY-MM-DD.log)
│   └── migrations/          # File SQL (001_xxx.sql, dst)
├── _views/                  # Template PHP plain
├── ADR/                     # Architecture Decision Records
├── tests/
│   └── test_runner.php      # Security test harness
├── escape.php               # Layer 1: Output escaping
├── csrf.php                 # Layer 1: CSRF token
├── db.php                   # Layer 1: PDO wrapper
├── session.php              # Layer 1: Session bootstrap
├── password.php             # Layer 1: Password hashing
├── rate_guard.php           # Layer 1: Rate limiter
├── headers.php              # Layer 1: Security headers
├── config.php               # Layer 2: Config loader
├── error.php                # Layer 2: Error handler
├── logger.php               # Layer 2: Logger
├── migrate.php              # Layer 2: Migration runner
├── health.php               # Layer 2: Health check
├── router.php               # Layer 3: Router
├── request.php              # Layer 3: Request helper
├── response.php             # Layer 3: Response helper
├── middleware.php           # Layer 3: Middleware pipeline
├── bootstrap.php            # Entry point — include di index.php
├── deprecation.php          # Layer 4: Deprecation helper
├── CONVENTIONS.md           # Layer 4: Panduan tim
├── SECURITY.md              # Layer 4: Panduan keamanan
├── CHANGELOG.md
└── VERSION
```

---

## Lapisan 1 — Security Primitives

Fondasi keamanan. Semua lapisan di atasnya bergantung ke sini.

### `escape.php` — Output Escape

Tiga fungsi, masing-masing untuk konteks yang berbeda. **Tidak boleh ada `echo $var` tanpa salah satu dari ini.**

```php
echo e($user_input);          // HTML body
echo '<input value="' . attr($value) . '">';  // HTML attribute
echo '<script>var x = ' . je($data) . '</script>';  // JS block
```

| Fungsi | Konteks | Mekanisme |
|--------|---------|-----------|
| `e($str)` | HTML body | `htmlspecialchars` dengan `ENT_QUOTES` |
| `attr($str)` | HTML attribute | Sama dengan `e()`, dipisah supaya intent jelas |
| `je($data)` | `<script>` block | `json_encode` dengan flag `JSON_HEX_*` |

### `csrf.php` — CSRF Token

Token di-generate per-session (bukan per-request) untuk kompatibilitas multi-tab. Pakai `hash_equals()` untuk timing-safe comparison.

```php
// Di form HTML
echo csrf_field();  // <input type="hidden" name="_token" value="...">

// Di AJAX
fetch('/api', { headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' } });

// Di handler POST
if (!csrf_verify()) { http_response_code(403); exit; }
```

### `db.php` — PDO Wrapper

**Tidak ada raw query.** Semua query wajib lewat binding parameter.

```php
$user  = db_one('SELECT * FROM user WHERE id = ?', [$id]);
$posts = db_all('SELECT * FROM post WHERE user_id = ?', [$user_id]);
$id    = db_insert('INSERT INTO user (name, email) VALUES (?, ?)', [$name, $email]);
db_query('UPDATE user SET name = ? WHERE id = ?', [$name, $id]);
```

### `session.php` — Session Bootstrap

```php
session_boot();       // Panggil di bootstrap — set httponly, SameSite=Strict, dll
session_regen();      // Panggil saat login / logout / privilege escalation
session_fingerprint($salt);  // Opsional: verifikasi fingerprint (User-Agent + Accept-Language + IP /24 subnet)
```

### `password.php` — Password Hashing

Argon2id sebagai algoritma utama, bcrypt sebagai fallback. Mendukung migrasi algorithm tanpa break hash lama.

```php
$hash = pw_hash($password);              // Hash saat registrasi
pw_verify($password, $hash);            // Verifikasi saat login
if (pw_needs_rehash($hash)) {           // Cek setiap login sukses
    $new_hash = pw_hash($password);     // Re-hash dan update DB
}
```

### `rate_guard.php` — Rate Limiter

APCu sebagai primary storage, file fallback ke `/tmp/rate_guard/`. Key convention: `"login:{$ip}"`, `"api:{$token}"`.

```php
if (!rate_check("login:{$ip}", 10, 60)) {
    http_response_code(429);
    exit('Too many attempts.');
}
```

### `headers.php` — Security Headers

Satu panggilan di bootstrap. Set semua header sekaligus: CSP, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS (hanya kalau HTTPS).

```php
apply_security_headers();

// Override CSP per-route kalau butuh inline script
apply_security_headers(['script-src' => "'self' https://cdn.example.com"]);
```

---

## Lapisan 2 — Operational Core

### `config.php` — Config Loader

Dot-notation getter dari `_config/.env`. File `.env` **tidak masuk repo** — hanya `.env.example`.

```php
config('db_dsn');            // DB_DSN dari .env
config('app_debug', false);  // Dengan default value

// Di bootstrap — fatal error kalau key wajib tidak ada
config_require(['APP_KEY', 'DB_DSN', 'SESSION_SALT']);
```

### `error.php` — Error Handler

```php
error_boot(config('app_debug') === 'true');
// Mode debug: tampilkan detail di browser + stack trace
// Mode prod: user dapat halaman generic, detail ditulis ke log
// Semua request dapat header X-Request-ID untuk tracing
```

### `logger.php` — Logger

Format: `[2026-05-13T14:30:00+07:00] [ERROR] [req:abc123] Message {"context":"json"}`

```php
log_info('User logged in', ['user_id' => $id]);
log_error('Payment failed', ['order' => $order_id, 'reason' => $e->getMessage()]);
log_warning('Rate limit approaching', ['key' => $key, 'count' => $count]);
```

Log disimpan di `_storage/logs/app-YYYY-MM-DD.log`. Rotasi per-hari, retention default 30 hari.

### `migrate.php` — Migration Runner

File-based, up-only. Tidak ada down migration — kalau perlu rollback, tulis migration baru.

```bash
php migrate.php
# Applied: 001_create_users.sql
# Applied: 002_add_sessions_table.sql
# No new migrations to run.
```

File migration: `_storage/migrations/001_create_users.sql`, `002_...sql`, dst.

### `health.php` — Health Check

```
GET /_health

{"status":"ok","timestamp":"2026-05-13T14:30:00+07:00","checks":{"db":true,"disk":true,"cache":true}}
```

HTTP 200 kalau semua check pass, 503 kalau ada yang fail. Tidak expose detail sistem.

---

## Lapisan 3 — HTTP Layer

### `router.php` — Array-Based Router

Semua route di satu file, plain PHP array. Next dev buka satu file, lihat semua route.

```php
// routes.php
return [
    'GET  /'           => 'home/index',
    'GET  /about'      => 'home/about',
    'POST /login'      => 'auth/login',
    'GET  /user/{id}'  => 'user/show',
    'GET  /_health'    => 'system/health',
];

// bootstrap.php
$routes = require 'routes.php';
route_dispatch($routes);
```

Handler `'user/show'` → load `_controllers/user.php` → panggil fungsi `show(['id' => '42'])`.

### `request.php` — Request Helper

```php
$name  = input('name');           // POST > GET, sudah di-trim
$page  = input_int('page', 1);   // Cast ke int, aman untuk pagination
$tags  = input_array('tags');    // Multi-select / checkbox
$verb  = method();               // Support _method override untuk PUT/DELETE dari form
$is_xhr = is_ajax();
$client_ip = ip();               // REMOTE_ADDR by default; trust CF-Connecting-IP / X-Forwarded-For hanya kalau REMOTE_ADDR ada di TRUSTED_PROXIES
```

### `response.php` — Response Helper

```php
respond('<h1>Hello</h1>');                    // HTML response
json(['status' => 'ok', 'data' => $result]); // JSON response
view('user/profile', ['user' => $user]);      // Render _views/user/profile.php
redirect('/dashboard');                        // Redirect internal (tolak URL eksternal)
redirect('https://example.com', 302, true);   // Redirect eksternal dengan explicit allow
```

### `middleware.php` — Pipeline

`middleware_run()` menerima nama group, handler, dan definisi groups dari luar. Definisi groups ada di `bootstrap.php`, bukan hardcoded di dalam `middleware.php`.

```php
// Groups didefinisikan di bootstrap.php sebagai $middleware_groups
$middleware_groups = [
    'web'  => ['session_boot', 'csrf_check_post'],
    'api'  => ['api_auth', 'api_rate_check'],
    'open' => [],
];

// Penggunaan di index.php
middleware_run('web',  fn() => route_dispatch($web_routes),  $middleware_groups);
middleware_run('api',  fn() => route_dispatch($api_routes),  $middleware_groups);
middleware_run('open', fn() => route_dispatch($open_routes), $middleware_groups);
```

Fungsi middleware aplikasi (`csrf_check_post`, `api_auth`, `api_rate_check`) ada di `_controllers/_middleware.php` — bukan di `middleware.php`. `middleware.php` hanya berisi engine pipeline, bukan implementasi middleware spesifik project.

---

## Lapisan 4 — Succession Layer

Dokumentasi dan tooling untuk keberlangsungan project.

### `CONVENTIONS.md`

Panduan tim: naming convention, struktur file, kapan pakai `db_one` vs `db_all`, git workflow, step-by-step deploy procedure.

### `SECURITY.md`

Surface area yang dipantau, checklist periodic review (grep berbahaya, threshold rate limit, header policy), incident response, dan daftar keputusan keamanan beserta alasannya.

### `ADR/` — Architecture Decision Records

Setiap keputusan arsitektur besar dicatat dalam format standar: Status, Context, Decision, Consequences.

| File | Keputusan |
|------|-----------|
| [ADR-001](ADR/ADR-001-native-php-bukan-laravel.md) | Native PHP tanpa framework |
| [ADR-002](ADR/ADR-002-sqlite-sebagai-database-default.md) | SQLite sebagai database default |
| [ADR-003](ADR/ADR-003-fetch-pattern-conditional.md) | Conditional fetch via ETag |
| [ADR-004](ADR/ADR-004-single-zone-enforcement-sam.md) | Single-zone enforcement |
| [ADR-005](ADR/ADR-005-config-di-luar-root.md) | `_config/` di luar document root |

### `tests/test_runner.php` — Security Test Harness

```bash
php tests/test_runner.php
# [PASS] e() escapes <script> tag
# [PASS] csrf_verify() gagal tanpa token
# [PASS] csrf_verify() sukses dengan token yang benar
# [PASS] db_query() dengan binding berjalan
# [PASS] pw_verify() match dengan password yang benar
# ...
# Results: 28 passed, 0 failed
```

28 test, fokus security primitives. Jalankan sebelum setiap deploy.

---

## Setup Awal

> **Penting setelah clone:** Repo tidak menyertakan `_config/.env` (file tersebut di-gitignore). Langkah pertama setelah clone **wajib** membuat file ini dari `.env.example`, lalu mengisi semua nilai yang diperlukan sebelum menjalankan aplikasi. Tanpa `.env`, framework akan fatal error saat `config_require()` dipanggil di bootstrap.

```bash
# 1. Copy dan isi konfigurasi
cp _config/.env.example _config/.env
# Edit _config/.env — isi APP_KEY, SESSION_SALT, dsb

# 2. Buat direktori storage
mkdir -p _storage/logs _storage/migrations

# 3. Jalankan migration
php migrate.php

# 4. Verifikasi test
php tests/test_runner.php

# 5. Verifikasi health
curl http://localhost/_health
```

## Skeleton `index.php`

Titik masuk aplikasi. Satu file ini menyambungkan bootstrap, routes, dan middleware:

```php
<?php
// index.php — satu-satunya file yang diakses web server

require __DIR__ . '/bootstrap.php';
// Setelah baris ini tersedia: semua fungsi framework, $middleware_groups

$routes = require __DIR__ . '/routes.php';

middleware_run('web', fn() => route_dispatch($routes), $middleware_groups);
```

`$middleware_groups` di-export oleh `bootstrap.php` sebagai variable biasa di scope file. Ini intentional — lihat `bootstrap.php` untuk definisi lengkapnya.

## Deploy

Lihat [CONVENTIONS.md](CONVENTIONS.md#deploy-procedure) untuk step lengkap.

---

## Aturan Wajib

1. **Tidak boleh `echo $var`** tanpa `e()`, `attr()`, atau `je()`
2. **Tidak boleh query tanpa binding** — selalu lewat `db_query($sql, $params)`
3. **Tidak boleh akses `$_POST`/`$_GET` langsung** di controller — gunakan `input()`
4. **Setiap POST request** harus diproteksi CSRF via `csrf_verify()`
5. **Setiap auth state change** (login/logout) harus panggil `session_regen()`
