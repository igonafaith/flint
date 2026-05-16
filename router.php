<?php
/**
 * Layer 3.1 — Array-Based Router
 * Semua route didefinisikan di satu file, plain PHP array.
 * Handler format: 'controller/function', misal 'user/show'
 * Controller: _controllers/user.php, function show(array $params)
 */

/**
 * Dispatch request ke handler yang sesuai.
 *
 * @param array $routes  ['METHOD /path' => 'controller/function', ...]
 */
function route_dispatch(array $routes): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $uri    = '/' . ltrim(rawurldecode($uri), '/');
    $uri    = rtrim($uri, '/') ?: '/';  // normalisasi trailing slash

    foreach ($routes as $definition => $handler) {
        [$route_method, $route_path] = explode(' ', $definition, 2);

        if (strtoupper($route_method) !== $method) {
            continue;
        }

        $params = _route_match($route_path, $uri);
        if ($params !== null) {
            _route_invoke($handler, $params);
            return;
        }
    }

    // 404
    http_response_code(404);
    $file = __DIR__ . '/_views/404.html';
    if (file_exists($file)) {
        readfile($file);
    } else {
        echo '<h1>404 Not Found</h1>';
    }
}

/**
 * Match URI terhadap route pattern. Return array params kalau match, null kalau tidak.
 */
function _route_match(string $pattern, string $uri): ?array
{
    // Split pattern pada placeholder {param} sehingga bagian literal bisa di-preg_quote
    // tanpa mengacaukan karakter seperti '.' yang harus match literal, bukan "any char".
    $parts = preg_split('/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
    $regex = '';
    foreach ($parts as $part) {
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $part, $m)) {
            $regex .= '(?P<' . $m[1] . '>[^/]+)';
        } else {
            $regex .= preg_quote($part, '#');
        }
    }
    $regex = '#^' . $regex . '$#u';

    if (!preg_match($regex, $uri, $matches)) {
        return null;
    }

    // Ambil hanya named captures
    $params = [];
    foreach ($matches as $key => $value) {
        if (is_string($key)) {
            $params[$key] = $value;
        }
    }
    return $params;
}

/**
 * Load controller dan panggil fungsi handler.
 * Handler: 'user/show' → require _controllers/user.php, call show($params)
 */
function _route_invoke(string $handler, array $params): void
{
    [$controller, $function] = explode('/', $handler, 2);

    $controller_file = __DIR__ . '/_controllers/' . $controller . '.php';

    if (!file_exists($controller_file)) {
        http_response_code(500);
        exit('Controller not found: ' . e($controller));
    }

    require_once $controller_file;

    if (!function_exists($function)) {
        http_response_code(500);
        exit('Handler function not found: ' . e($function));
    }

    $function($params);
}
