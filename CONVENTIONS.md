# CONVENTIONS

## Naming

- File: `snake_case.php`
- Function: `snake_case()`
- Constant: `UPPER_SNAKE_CASE`
- Tabel database: singular (`user`, bukan `users`; `session`, bukan `sessions`)
- Kolom: `snake_case`
- Migration file: `NNN_deskripsi.sql` (contoh: `001_create_users.sql`)

## Struktur File

```
_config/          Config & .env (di luar document root)
_controllers/     Controller PHP — satu file per resource
_storage/
  logs/           Log harian (app-YYYY-MM-DD.log)
  migrations/     File SQL migration (001_xxx.sql, dst)
_views/           Template PHP — plain PHP, bukan template engine
ADR/              Architecture Decision Records
tests/            Security test harness
escape.php        Layer 1: Output escaping
csrf.php          Layer 1: CSRF token
db.php            Layer 1: PDO wrapper
session.php       Layer 1: Session bootstrap
password.php      Layer 1: Password hashing
rate_guard.php    Layer 1: Rate limiter
headers.php       Layer 1: Security headers
config.php        Layer 2: Config loader
error.php         Layer 2: Error handler
logger.php        Layer 2: Logger
migrate.php       Layer 2: Migration runner
health.php        Layer 2: Health check
router.php        Layer 3: Array-based router
request.php       Layer 3: Request helper
response.php      Layer 3: Response helper
middleware.php    Layer 3: Middleware pipeline
deprecation.php   Layer 4: Deprecation helper
```

## Kapan Pakai db_one vs db_all vs db_query

| Fungsi       | Kapan dipakai                                                       |
|--------------|---------------------------------------------------------------------|
| `db_one()`   | Ambil satu row — user by ID, cek email unik, dll.                   |
| `db_all()`   | Ambil banyak rows — list item, pagination, report.                  |
| `db_insert()`| INSERT dan perlu last insert ID.                                    |
| `db_query()` | UPDATE, DELETE, CREATE TABLE, atau query yang return PDOStatement.  |

Gunakan positional parameter binding: `[$id]` dengan placeholder `?`. Codebase seluruhnya konsisten menggunakan style ini.

## Git Workflow

- Branch: `feature/nama-fitur`, `fix/deskripsi-bug`, `chore/deskripsi-task`
- Commit message: `type: deskripsi singkat`
  - `feat:` fitur baru
  - `fix:` bug fix
  - `sec:` patch keamanan (prioritas review)
  - `chore:` maintenance, tanpa perubahan fungsional
  - `docs:` dokumentasi saja
- Tidak ada force push ke `main`/`master`

## Deploy Procedure

1. Backup database (`cp _storage/database.sqlite _storage/database.sqlite.bak`)
2. Pull kode terbaru (`git pull origin main`)
3. Cek apakah ada migration baru: `php migrate.php`
4. Cek health endpoint: `GET /_health` harus return `{"status":"ok",...}`
5. Cek error log: `tail _storage/logs/app-YYYY-MM-DD.log`
6. Kalau ada masalah: rollback kode (`git checkout HEAD~1`), restore DB dari backup

## Output Escaping

Wajib pakai salah satu dari:
- `e($str)` — untuk HTML body
- `attr($str)` — untuk HTML attribute
- `je($data)` — untuk embed di `<script>` block

Tidak boleh ada `echo $var` tanpa escape. Ini yang di-grep di code review.
