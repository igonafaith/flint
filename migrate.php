<?php
/**
 * Layer 2.4 — Migration Runner
 * File-based, up-only. Tidak ada down migration — by design.
 * CLI: php migrate.php
 */

define('MIGRATIONS_DIR', __DIR__ . '/_storage/migrations');
define('MIGRATIONS_TABLE', '_migrations');

/**
 * Pastikan tabel tracking sudah ada.
 */
function _migrate_ensure_table(): void
{
    db_query('CREATE TABLE IF NOT EXISTS ' . MIGRATIONS_TABLE . ' (
        file       TEXT NOT NULL PRIMARY KEY,
        applied_at TEXT NOT NULL
    )');
}

/**
 * Return list file migration yang sudah dijalankan.
 */
function _migrate_applied(): array
{
    _migrate_ensure_table();
    $rows = db_all('SELECT file FROM ' . MIGRATIONS_TABLE . ' ORDER BY file');
    return array_column($rows, 'file');
}

/**
 * Scan folder, jalankan migration yang belum dijalankan.
 * Return list nama file yang baru dijalankan.
 */
function migrate_run(): array
{
    $applied = _migrate_applied();
    $files   = glob(MIGRATIONS_DIR . '/*.sql');
    sort($files);

    $ran = [];
    foreach ($files as $path) {
        $name = basename($path);

        if (in_array($name, $applied, true)) {
            continue;
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            continue;
        }

        $pdo = db();
        $in_transaction = false;

        try {
            $pdo->beginTransaction();
            $in_transaction = true;

            // SQLite mendukung multi-statement exec secara native.
            // Jangan split dengan explode(';') — akan salah pada string literal yang mengandung semicolon.
            //
            // PENTING: File migration TIDAK BOLEH mengandung statement BEGIN / COMMIT / ROLLBACK sendiri.
            // Runner ini sudah membungkus eksekusi dalam transaction. Nested transaction di SQLite
            // akan melempar exception "cannot start a transaction within a transaction".
            $pdo->exec($sql);

            db_insert(
                'INSERT INTO ' . MIGRATIONS_TABLE . ' (file, applied_at) VALUES (?, ?)',
                [$name, date('c')]
            );

            $pdo->commit();
            $ran[] = $name;
        } catch (\Throwable $e) {
            if ($in_transaction) {
                $pdo->rollBack();
            }
            throw new \RuntimeException('Migration failed: ' . $name . ' — ' . $e->getMessage(), 0, $e);
        }
    }

    return $ran;
}

// CLI runner
if (PHP_SAPI === 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/db.php';

    try {
        $ran = migrate_run();

        if (empty($ran)) {
            echo "No new migrations to run.\n";
        } else {
            foreach ($ran as $file) {
                echo "Applied: $file\n";
            }
        }
    } catch (\Throwable $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
        exit(1);
    }
}
