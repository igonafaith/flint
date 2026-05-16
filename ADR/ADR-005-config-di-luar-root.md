# ADR-005: _config/ di Luar Document Root

**Status:** Accepted  
**Date:** 2026-05-13

## Context

File konfigurasi (terutama `.env`) berisi credential sensitif: database path/credential, app key, session salt. Jika `.env` berada di dalam document root dan web server misconfigured, file ini bisa diakses langsung via HTTP.

Contoh insiden nyata: ribuan Laravel app ter-expose `.env` karena Nginx/Apache misconfiguration yang serve file statis tanpa filter.

## Decision

Direktori `_config/` ditempatkan **di luar document root**. Struktur:

```
/var/www/myapp/
├── public/           ← document root (web server point ke sini)
│   └── index.php
├── _config/          ← DI LUAR document root
│   ├── .env
│   └── .env.example
├── _storage/
├── _controllers/
├── _views/
└── *.php             ← file aplikasi, tidak accessible langsung
```

PHP mengakses `_config/.env` via path absolut filesystem, bukan via HTTP.

## Consequences

**Trade-off yang diterima:**
- Setup web server perlu dikonfigurasi dengan benar (document root ke `public/`, bukan root project)
- Beberapa shared hosting tidak support konfigurasi ini

**Manfaat:**
- File `.env` tidak bisa diakses via HTTP bahkan kalau web server misconfigured
- Defense in depth: bahkan kalau attacker tahu path file, tidak ada HTTP route ke sana
- `.env.example` tetap bisa masuk repo untuk dokumentasi tanpa risiko credential bocor
