<?php
/**
 * Layer 1.1 — Output Escape Helpers
 * Aturan: tidak boleh ada echo $var tanpa salah satu dari fungsi ini.
 */

/**
 * Escape untuk HTML body output.
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape untuk HTML attribute context.
 * Dipisah dari e() supaya intent jelas; kalau perlu stricter escaping tinggal ubah sini.
 */
function attr(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Encode data PHP ke JSON yang aman untuk embed di dalam <script> block.
 * Flag JSON_HEX_* mencegah breakout dari JS context.
 */
function je(mixed $data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
    );
}
