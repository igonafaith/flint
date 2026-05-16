# ADR-003: Conditional Fetch Pattern (ETag / Hash-based)

**Status:** Accepted  
**Date:** 2026-05-13

## Context

Beberapa endpoint mengembalikan data yang jarang berubah (referensi, konfigurasi publik). Polling biasa membuang bandwidth dan server resource kalau data belum berubah.

## Decision

Gunakan conditional fetch via `ETag` header dan `If-None-Match` request header:

1. Server hitung hash (`md5` atau `sha1`) dari response body
2. Set `ETag: "{hash}"` di response
3. Kalau request berikutnya kirim `If-None-Match: "{hash}"` yang sama, return `304 Not Modified` tanpa body

Implementasi minimal, tidak butuh cache layer eksternal.

```php
function etag_respond(string $body, string $content_type = 'text/html'): void {
    $etag = '"' . md5($body) . '"';
    header('ETag: ' . $etag);

    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    respond($body, 200, ['Content-Type' => $content_type]);
}
```

## Consequences

**Trade-off yang diterima:**
- Server tetap harus generate response body untuk menghitung hash — saving hanya di bandwidth, bukan di computation
- Tidak cocok untuk data yang selalu berubah (real-time feed, personalized content)

**Manfaat:**
- Bandwidth saving signifikan untuk data statis/semi-statis
- Browser/CDN bisa cache dengan benar
- Implementasi simple, tidak butuh Redis atau cache server
