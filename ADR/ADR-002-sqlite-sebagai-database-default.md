# ADR-002: SQLite sebagai Database Default

**Status:** Accepted  
**Date:** 2026-05-13

## Context

Project ini berjalan di single server (atau shared hosting) dengan traffic terbatas. MySQL/PostgreSQL memerlukan setup server terpisah, manajemen user/permission, dan maintenance ongoing.

## Decision

Gunakan SQLite sebagai database default. Satu file `.sqlite` di `_storage/database.sqlite`. Jika project berkembang dan butuh multi-user write concurrency tinggi, ini bisa di-replace dengan PostgreSQL cukup dengan mengubah `DB_DSN` di `.env` — karena semua query lewat PDO dengan interface yang sama.

## Consequences

**Trade-off yang diterima:**
- Write concurrency terbatas (SQLite pakai file-level lock untuk write)
- Tidak ada replication bawaan
- Backup = copy satu file (ini sebenarnya keuntungan juga)

**Manfaat:**
- Zero infrastructure: tidak ada server database terpisah
- Backup semudah `cp database.sqlite database.sqlite.bak`
- Portable: bisa dipindah antar server dengan copy file
- Cocok untuk traffic rendah-menengah (ratusan request per detik masih aman untuk read-heavy workload)
- SQLite mendukung transaction, sehingga migration aman
