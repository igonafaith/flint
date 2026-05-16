<?php
/**
 * Layer 4 — Deprecation Helper
 * Grace period: fungsi deprecated tetap berfungsi minimal 6 bulan atau 2 minor version.
 * Catat di CHANGELOG saat deprecated dan saat removed.
 */

/**
 * Trigger deprecation notice.
 *
 * @param string $old  Nama fungsi/method yang deprecated
 * @param string $new  Nama fungsi/method penggantinya
 */
function deprecated(string $old, string $new): void
{
    trigger_error(
        $old . ' is deprecated, use ' . $new . ' instead.',
        E_USER_DEPRECATED
    );
}
