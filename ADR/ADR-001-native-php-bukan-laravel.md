# ADR-001: Native PHP tanpa Framework

**Status:** Accepted  
**Date:** 2026-05-13

## Context

Project ini dijalankan oleh tim kecil dengan kebutuhan kontrol penuh atas keamanan, performa, dan maintainability jangka panjang. Laravel, Symfony, dan framework besar lainnya menawarkan banyak fitur, tapi juga membawa:

- Dependency chain yang besar (ratusan package)
- Upgrade cycle yang memaksa perubahan codebase
- Abstraksi yang menyulitkan audit keamanan
- Learning curve bagi kontributor yang tidak familiar dengan framework tersebut

## Decision

Gunakan native PHP tanpa framework. Bangun lapisan minimal (security primitives, HTTP layer, config, logging) sendiri. Total file ~15 file PHP, semua bisa dibaca dalam satu sitting.

## Consequences

**Trade-off yang diterima:**
- Tidak ada fitur out-of-the-box seperti ORM, queue, broadcast, dll
- Perlu menulis boilerplate untuk hal-hal yang di framework sudah tersedia
- Tidak ada community package ecosystem yang langsung kompatibel

**Manfaat:**
- Setiap baris kode di-audit sendiri
- Zero dependency CVE dari package eksternal
- Upgrade PHP version tidak breaking karena tidak ada framework compatibility requirement
- Developer baru bisa memahami seluruh codebase dalam waktu singkat
- Deploy sesederhana copy file ke server
