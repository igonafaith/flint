# ADR-004: Single-Zone Enforcement di SAM

**Status:** Accepted  
**Date:** 2026-05-13

## Context

Aplikasi ini beroperasi dalam satu zone/environment pada satu waktu (development, staging, atau production). Multi-zone deployment dengan shared state menambah kompleksitas dan surface area keamanan.

## Decision

Satu instance = satu zone. Tidak ada shared session store atau database replikasi lintas zone. Konfigurasi zone ditentukan via `APP_ENV` di `.env`:

- `development` — debug aktif, log verbose, tidak perlu HTTPS
- `staging` — mirip production, tapi data bisa di-reset
- `production` — debug off, log minimal (warning ke atas), wajib HTTPS

Setiap zone memiliki `.env` sendiri yang tidak pernah di-commit ke repo.

## Consequences

**Trade-off yang diterima:**
- Tidak ada horizontal scaling otomatis
- Zero-downtime deployment perlu prosedur manual (maintenance mode → deploy → verify → up)

**Manfaat:**
- Tidak ada race condition state antar instance
- Security posture lebih mudah di-audit (satu point of entry)
- Incident response lebih sederhana (matikan satu instance, bukan koordinasi cluster)
- Konfigurasi zone terisolasi — tidak mungkin production config bocor ke dev
