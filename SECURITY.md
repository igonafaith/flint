# SECURITY

## Surface Area yang Dipantau

- PHP version CVE — pantau https://www.php.net/ChangeLog-8.php
- Saat ini zero dependency (lihat ADR-001). Kalau di masa depan ada dependency ditambahkan, cek advisory database-nya secara berkala.
- Security header policy changes — pantau OWASP, MDN CSP updates
- Cryptographic algorithm deprecation — terutama hashing (bcrypt, Argon2)

## Checklist Periodic Review (tiap 3–6 bulan)

### Output Escaping
```bash
# Cari echo/print tanpa escape — wajib kosong hasilnya
grep -rn "echo \$" _controllers/ _views/
grep -rn "print \$" _controllers/ _views/
```

### SQL Injection
```bash
# Cari query langsung tanpa binding
grep -rn "db()->query\|db()->exec" .
# Cari instantiasi PDO langsung (bypass db() wrapper)
grep -rn "new PDO" .
```

### Dangerous Functions
```bash
grep -rn "eval\|unserialize\|system\|exec\|shell_exec\|passthru" .
```

### Rate Guard Thresholds
- Login: max 10 request per 60 detik per IP (review kalau traffic pattern berubah)
- API: max 60 request per 60 detik per IP
- CSRF fail: max 5 per 60 detik per IP

### Header Policy
- Jalankan https://securityheaders.com terhadap production URL
- Pastikan CSP tidak ada `unsafe-inline` atau `unsafe-eval` kecuali ada justifikasi eksplisit

### Dependency Check
```bash
# Kalau pakai composer
composer audit
```

## Incident Response

### Kontak
- Security incident: [isi nama/email/Slack handle PIC keamanan]
- Emergency credential: disimpan di [isi lokasi password manager tim, bukan di sini]

### Step Emergency Lockdown
1. Enable maintenance mode:
   ```php
   // Di bootstrap, tambahkan sementara:
   http_response_code(503);
   echo file_get_contents(__DIR__ . '/_views/maintenance.html');
   exit;
   ```
2. Blokir IP yang mencurigakan di Cloudflare / firewall
3. Rotate session salt (`SESSION_SALT` di `.env`) — semua session existing akan invalid
4. Rotate credential yang terekspos
5. Dokumentasikan timeline incident

## Keputusan Keamanan yang Sudah Dibuat

| Keputusan | Alasan |
|-----------|--------|
| Argon2id sebagai algoritma password hashing utama | Lebih tahan terhadap GPU/ASIC attack dibanding bcrypt. PHP >= 7.3 mendukung. Bcrypt tetap sebagai fallback untuk compatibility. |
| `SameSite=Strict` untuk session cookie | Mencegah CSRF dari cross-site request sepenuhnya. Trade-off: link dari email/aplikasi eksternal akan kehilangan session, user perlu login ulang. Acceptable untuk aplikasi ini. |
| CSRF token per-session, bukan per-request | Per-request token membreak multi-tab usage. Per-session token masih aman kalau dikombinasikan dengan `SameSite=Strict` dan `HttpOnly`. |
| `hash_equals()` untuk CSRF comparison | Mencegah timing attack yang bisa digunakan untuk brute-force token karakter per karakter. |
| `ATTR_EMULATE_PREPARES => false` di PDO | Memastikan prepared statement diproses di DB engine (bukan emulasi PHP), memberikan proteksi SQL injection yang lebih kuat. |
| `open_redirect` protection di `redirect()` | Default menolak redirect ke URL eksternal untuk mencegah phishing via open redirect. Protocol-relative URL (`//evil.com`) juga ditolak — tidak hanya URL dengan scheme eksplisit. |
| File `.env` di luar document root | Mencegah akses langsung ke konfigurasi kalau web server misconfigured. Lihat ADR-005. |
| `ip()` hanya trust forwarded headers jika `REMOTE_ADDR` ada di `TRUSTED_PROXIES` | Sebelum v1.2.0, `CF-Connecting-IP` dan `X-Forwarded-For` selalu dipercaya — siapapun bisa kirim header itu untuk spoof IP dan bypass rate limiter. Sekarang header tersebut hanya dipakai kalau `REMOTE_ADDR` cocok dengan salah satu IP di config `TRUSTED_PROXIES`. Kalau belum pakai proxy/Cloudflare, biarkan `TRUSTED_PROXIES` kosong — `ip()` akan langsung return `REMOTE_ADDR`. |
| `session_fingerprint()` menggunakan binary `inet_pton()` untuk ekstraksi subnet IPv6 | Normalisasi via `inet_ntop(inet_pton())` + `explode(':')` menghasilkan string berbeda untuk address yang secara semantik identik (misalnya `::1` vs `0:0:0:0:0:0:0:1`). Ini bisa menyebabkan false positive (session dihancurkan padahal user sama) atau false negative (session theft tidak terdeteksi). Diganti dengan `bin2hex(inet_pton())` + `str_split(..., 4)` yang selalu menghasilkan 32 hex char normalized. |
