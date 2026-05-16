# CHANGELOG

Format: [Semantic Versioning](https://semver.org/)  
Tanggal dalam format ISO 8601 (YYYY-MM-DD).

---

## [1.3.0] — 2026-05-16

### Changed

- **rate_guard.php** — `RATE_GUARD_FILE_DIR` kini dibaca dari `config('RATE_GUARD_FILE_DIR')` terlebih dahulu, fallback ke `sys_get_temp_dir()/rate_guard`. Key di `.env.example` yang sebelumnya dead sekarang aktif.
- **response.php**, **router.php** — Ganti `htmlspecialchars()` langsung dengan `e()` agar konsisten dengan aturan escaping framework dan grep audit di SECURITY.md tetap reliable.
- **middleware.php** — URI yang ditulis ke log kini di-`rawurldecode()` agar match dengan URI yang dipakai router saat debug routing.
- **CONVENTIONS.md** — Perjelas bahwa codebase menggunakan positional binding `[$id]` (bukan named `['id' => $id]`).

### Fixed

- **health.php** — `_health_check_db()` kini cek `function_exists('db_query')` sebelum memanggil fungsi, mencegah fatal error kalau health.php di-include tanpa db.php.
- **ADR-002** — Rename file `ADR-002-sqlite-per-modul.md` → `ADR-002-sqlite-sebagai-database-default.md` agar konsisten dengan judul di dalam file.

### Docs

- **README.md** — Koreksi test count `24` → `28`, update deskripsi `ip()` (mention `TRUSTED_PROXIES`), update deskripsi `session_fingerprint()` (mention Accept-Language + IP subnet).
- **tests/test_runner.php** — Koreksi docblock dari `~20` → `28` test.
- **SECURITY.md** — Tambah `grep -rn "new PDO" .` di SQL Injection checklist; ubah bullet "Dependency CVE" agar konsisten dengan ADR-001 zero dependency.
- **SECURITY.md** — Tambah baris keputusan untuk `TRUSTED_PROXIES` dan normalisasi IPv6 di `session_fingerprint()`.
- **_config/.env.example** — Tambah komentar penjelasan untuk `RATE_GUARD_FILE_DIR`.
- **bootstrap.php** — Tambah komentar bahwa `request.php` wajib di-load sebelum `session_boot()` / `apply_security_headers()` dipanggil.
- **CONVENTIONS.md** — Selaraskan contoh binding dengan codebase (positional).

---

## [1.2.1] — 2026-05-16

### Fixed

- **config.php** — Hapus docblock duplikat di atas `config_require()`; hanya satu docblock yang tertinggal, lengkap dengan catatan caveat test override.
- **session.php** — Perbaiki bug ekstraksi /48 prefix IPv6 di `session_fingerprint()`: ganti `inet_ntop(inet_pton()) + explode(':')` dengan `bin2hex(inet_pton()) + str_split(..., 4)` sehingga hasilnya selalu normalized terlepas dari compressed form (`::1`, `0:0:0:0:0:0:0:1`, `::0001` semuanya menghasilkan prefix `0000:0000:0000`). Bug lama bisa menyebabkan false positive (session destroy untuk user yang sama) atau false negative (session theft tidak terdeteksi) pada address IPv6 compressed.

---

## [1.2.0] — 2026-05-13

### Fixed

- **db.php** — `db($dsn)` kini melakukan force-reconnect saat DSN baru di-pass; sebelumnya argumen kedua diabaikan diam-diam.
- **error.php** — `_error_render_generic()` kini punya guard `static $sent` untuk mencegah double-exit race condition pada concurrent shutdown handlers.
- **request.php** — `ip()` tidak lagi mempercayai `CF-Connecting-IP` / `X-Forwarded-For` secara default. Header tersebut hanya dipercaya jika `REMOTE_ADDR` ada di daftar `TRUSTED_PROXIES` di config, mencegah IP spoofing oleh klien sembarang.
- **rate_guard.php** — `_rate_check_file()` kini menggunakan `fopen` + `flock(LOCK_EX)` + `ftruncate` + `rewind` + `fwrite` untuk atomic read-modify-write, menghilangkan TOCTOU race condition.
- **rate_guard.php** — `_rate_check_apcu()` kini menggunakan `apcu_add()` (atomic first-init) diikuti `apcu_inc()` alih-alih fetch-then-store non-atomic.
- **migrate.php** — Ganti `explode(';', $sql)` + loop dengan satu `$pdo->exec($sql)` langsung; SQLite natively mendukung multi-statement exec dan cara lama salah pada semicolon di dalam string literal.
- **session.php** — `session_fingerprint()` diperkuat: fingerprint kini mencakup `Accept-Language` dan subnet `/24` dari IP klien di samping User-Agent; ditambahkan docblock warning tentang keterbatasan defence-in-depth.
- **logger.php** — `_log_cleanup()` dipindah dari hot path `log_write()` ke `register_shutdown_function` yang didaftarkan sekali; mengurangi I/O overhead signifikan pada log-heavy request.
- **config.php** — Ditambahkan test override seam: `config('KEY', null, ['KEY' => 'val'])` untuk inject nilai saat testing tanpa menyentuh `.env`.
- **config.php** — `_config_parse()` kini mendeteksi malformed quotes (misal `"hello" world"`) dengan memeriksa bahwa tidak ada karakter quote yang sama di dalam string sebelum memperlakukannya sebagai quoted.
- **headers.php** — `apply_security_headers()` kini punya guard `static $applied` sehingga header security tidak pernah dikirim dua kali (mencegah override dari double-call).
- **router.php** — `_route_match()` kini memisahkan pattern pada placeholder `{param}` dan memanggil `preg_quote()` pada segmen literal, mencegah karakter seperti `.` matching "any char".
- **_controllers/_middleware.php** — `csrf_check_post()` kini menggunakan helper `method()` (bukan `$_SERVER['REQUEST_METHOD']` langsung) agar `_method` override dari form tidak bisa bypass CSRF check.

### Added

- **_config/.env.example** — Tambahkan kunci `TRUSTED_PROXIES` dengan komentar penjelasan.

---

## [1.1.0] — 2026-05-13

### Added
- `bootstrap.php`: entry point eksplisit yang menyambungkan semua modul dengan urutan include yang benar. Di-include sekali dari `index.php`. Juga mendefinisikan `$middleware_groups` yang dipakai oleh `middleware_run()`.
- `_controllers/_middleware.php`: application-specific middleware (`csrf_check_post`, `api_auth`, `api_rate_check`) dipindah dari `middleware.php` ke sini supaya framework code dan app code terpisah.
- `request.php`: helper `_is_https(): bool` yang dipakai oleh `session.php` dan `headers.php` untuk mendeteksi HTTPS di belakang proxy (Cloudflare, Nginx). Menggantikan duplikasi tiga baris identik di dua file.
- `health.php`: rate limiting ditambahkan (max 10 request/60 detik per IP) untuk mencegah amplification attack.
- `tests/test_runner.php`: test case `_is_external_url() tolak protocol-relative URL (//evil.com)` ditambahkan. Total naik dari 20 ke 28 test.

### Changed
- `middleware_run()`: sekarang menerima parameter ketiga `array $groups = []` — definisi group tidak lagi hardcoded di dalam `middleware.php`. **Breaking change untuk caller yang tidak pass `$groups`** — gunakan variable `$middleware_groups` dari `bootstrap.php`.
- `db()`: tidak lagi bergantung pada `defined('DB_DSN')`. Sekarang memanggil `config('DB_DSN')` secara langsung sehingga nilai dari `.env` benar-benar dipakai. Signature diubah ke `?string $dsn = null` untuk kompatibilitas PHP 8.x.
- `session.php`: HTTPS detection menggunakan `_is_https()` dari `request.php` — aware terhadap `X-Forwarded-Proto` dan `CF-Visitor` header.
- `headers.php`: HSTS check menggunakan `_is_https()` yang sama.
- `router.php`: trailing slash dinormalisasi (`/about/` diperlakukan sama dengan `/about`).
- `error.php`: debug output menggunakan `e()` dari `escape.php` konsisten dengan aturan codebase. Sebelumnya pakai `htmlspecialchars()` langsung.

### Fixed
- `response.php` — **Security**: `_is_external_url()` sekarang menolak protocol-relative URL (`//evil.com/path`) yang sebelumnya lolos validasi karena dimulai dengan `/`. Ini adalah open redirect vulnerability.
- `config.php` — **Bug**: urutan parsing dibalik — strip quotes dulu, baru strip inline comment. Sebelumnya value seperti `APP_KEY="abc #123"` dipotong menjadi `"abc` karena `#` dianggap komentar sebelum quote dilepas.

---

## [1.0.0] — 2026-05-13

### Added
- Layer 1 — Security Primitives
  - `escape.php`: output escape helpers (`e()`, `attr()`, `je()`)
  - `csrf.php`: CSRF token helper (`csrf_field()`, `csrf_token()`, `csrf_verify()`)
  - `db.php`: PDO wrapper dengan binding wajib
  - `session.php`: session bootstrap dengan setting keamanan
  - `password.php`: password hashing dengan Argon2id
  - `rate_guard.php`: rate limiter dengan APCu/file backend
  - `headers.php`: security headers (`apply_security_headers()`)
- Layer 2 — Operational Core
  - `config.php`: config loader dari `_config/.env`
  - `error.php`: error handler dengan mode debug/production
  - `logger.php`: logger harian dengan rotasi dan retention
  - `migrate.php`: migration runner file-based, up-only
  - `health.php`: health check endpoint JSON
- Layer 3 — HTTP Layer
  - `router.php`: array-based router dengan named parameter
  - `request.php`: request helper (`input()`, `ip()`, dll)
  - `response.php`: response helper (`respond()`, `json()`, `view()`, `redirect()`)
  - `middleware.php`: pipeline sederhana (before/handle/after)
- Layer 4 — Succession Layer
  - `CONVENTIONS.md`: panduan naming, struktur file, git workflow, deploy
  - `SECURITY.md`: surface area, checklist, incident response, keputusan keamanan
  - `ADR/`: 5 ADR awal (native PHP, SQLite, conditional fetch, single-zone, config path)
  - `VERSION`: file versi
  - `CHANGELOG.md`: file ini
  - `deprecation.php`: deprecation helper
  - `tests/test_runner.php`: security test harness (~20 test)
