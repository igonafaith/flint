<?php
/**
 * Layer 2.2 — Error Handler
 * Mode debug: tampilkan detail di browser.
 * Mode production: user dapat halaman generic, detail ke log file.
 */

/**
 * Daftarkan error handler, exception handler, dan shutdown function.
 *
 * @param bool $debug  true = mode dev (detail di browser), false = mode prod
 */
function error_boot(bool $debug = false): void
{
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($debug): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        if ($debug) {
            echo '<pre>[Error ' . $errno . '] ' . e($errstr)
                . ' in ' . e($errfile)
                . ' on line ' . $errline . '</pre>';
        } else {
            log_write('error', $errstr, [
                'errno'   => $errno,
                'file'    => $errfile,
                'line'    => $errline,
                'req_id'  => _request_id(),
            ]);
            _error_render_generic(500);
        }

        return true;
    });

    set_exception_handler(function (\Throwable $e) use ($debug): void {
        if ($debug) {
            echo '<pre>Uncaught ' . e(get_class($e)) . ': '
                . e($e->getMessage())
                . "\n\n" . e($e->getTraceAsString())
                . '</pre>';
        } else {
            log_write('critical', $e->getMessage(), [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'req_id'    => _request_id(),
            ]);
            _error_render_generic(500);
        }
    });

    register_shutdown_function(function () use ($debug): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if ($debug) {
                echo '<pre>[Fatal] ' . e($error['message'])
                    . ' in ' . e($error['file'])
                    . ' on line ' . $error['line'] . '</pre>';
            } else {
                log_write('emergency', $error['message'], [
                    'type'   => $error['type'],
                    'file'   => $error['file'],
                    'line'   => $error['line'],
                    'req_id' => _request_id(),
                ]);
                _error_render_generic(500);
            }
        }
    });

    // Set X-Request-ID header untuk tracing
    header('X-Request-ID: ' . _request_id());
}

/**
 * Generate atau return cached request ID (UUID v4).
 */
function _request_id(): string
{
    static $id = null;
    if ($id === null) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $id  = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
             . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
    return $id;
}

/**
 * Render halaman error generic ke user.
 */
function _error_render_generic(int $code): void
{
    static $sent = false;
    if ($sent || headers_sent()) {
        return;
    }
    $sent = true;

    http_response_code($code);
    $file = __DIR__ . '/_views/' . $code . '.html';

    if (file_exists($file)) {
        readfile($file);
    } else {
        echo '<h1>An error occurred. Please try again later.</h1>';
    }
    exit;
}
