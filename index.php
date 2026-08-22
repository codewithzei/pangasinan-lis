<?php

require_once __DIR__ . '/app/config/app.php';

function _exception_handler_bootstrap(Throwable $e): void
{
    try {
        $ctx = [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'code'    => $e->getCode(),
            'trace'   => array_slice(array_map(function ($f) {
                return ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '')
                    . (isset($f['file']) ? ' @ ' . basename($f['file']) . ':' . ($f['line'] ?? '?') : '');
            }, $e->getTrace()), 0, 12),
        ];
        $level = ($e instanceof Error) ? 'CRITICAL' : 'ERROR';
        system_log($level, "Uncaught " . get_class($e) . ": " . $e->getMessage(), $ctx);
    } catch (\Throwable $ignored) {
    }

    if (ini_get('display_errors')) {
        throw $e;
    } else {
        http_response_code(500);
        try {
            if (function_exists('view') || function_exists('auth')) {
                echo '<!DOCTYPE html><html><head><title>500 Internal Server Error</title><meta charset="utf-8"></head><body style="font-family:sans-serif;padding:40px;"><h1>500 — Something went wrong.</h1><p>An unexpected error occurred. Details have been logged.</p></body></html>';
            } else {
                echo '500 Internal Server Error';
            }
        } catch (\Throwable $ignored) {}
    }
    exit(1);
}
function _error_handler_bootstrap(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
{
    $reporting = error_reporting();
    if (($reporting & $errno) === 0) return false;
    $map = [
        E_ERROR             => ['ERROR', 'E_ERROR'],
        E_WARNING           => ['WARNING', 'E_WARNING'],
        E_PARSE             => ['CRITICAL', 'E_PARSE'],
        E_NOTICE            => ['NOTICE', 'E_NOTICE'],
        E_CORE_ERROR        => ['CRITICAL', 'E_CORE_ERROR'],
        E_CORE_WARNING      => ['WARNING', 'E_CORE_WARNING'],
        E_COMPILE_ERROR     => ['CRITICAL', 'E_COMPILE_ERROR'],
        E_COMPILE_WARNING   => ['WARNING', 'E_COMPILE_WARNING'],
        E_USER_ERROR        => ['ERROR', 'E_USER_ERROR'],
        E_USER_WARNING      => ['WARNING', 'E_USER_WARNING'],
        E_USER_NOTICE       => ['NOTICE', 'E_USER_NOTICE'],
        E_STRICT            => ['DEBUG', 'E_STRICT'],
        E_RECOVERABLE_ERROR => ['ERROR', 'E_RECOVERABLE_ERROR'],
        E_DEPRECATED        => ['DEBUG', 'E_DEPRECATED'],
        E_USER_DEPRECATED   => ['DEBUG', 'E_USER_DEPRECATED'],
    ];
    [$level, $name] = $map[$errno] ?? ['INFO', 'UNKNOWN'];
    try {
        system_log($level, "PHP {$name}: {$errstr}", ['file' => $errfile, 'line' => $errline, 'errno' => $errno]);
    } catch (\Throwable $ignored) {}
    return false;
}
set_exception_handler('_exception_handler_bootstrap');
set_error_handler('_error_handler_bootstrap');
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        try {
            system_log('CRITICAL', "Fatal PHP error on shutdown: {$err['message']}", ['file' => $err['file'], 'line' => $err['line'], 'type' => $err['type']]);
        } catch (\Throwable $ignored) {}
    }
    if (function_exists('log_system_event') && defined('APP_NAME')) {
        try {
            // optional shutdown trace on high-load monitoring; skipped by default to avoid noise.
        } catch (\Throwable $ignored) {}
    }
});

$routes = require __DIR__ . '/routes/web.php';

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$route = str_replace(BASE_URL, '', $requestUri);
$route = trim($route, '/');

if ($route === '') {
    $route = 'home';
}

function resolveRoute(array $routes, string $route, string $method): ?array
{
    if (isset($routes[$route])) {
        $def = $routes[$route];
        if (isset($def['method']) && strtoupper($def['method']) !== strtoupper($method)) {
            return null;
        }
        if (isset($def[0]) && is_array($def[0])) {
            foreach ($def as $sub) {
                $subMethod = strtoupper($sub['method'] ?? 'GET');
                if ($subMethod === strtoupper($method)) {
                    return $sub;
                }
            }
            return null;
        }
        return $def;
    }
    return null;
}

function runMiddleware(array $middlewares): void
{
    foreach ($middlewares as $middleware) {
        $middlewareFile = __DIR__ . '/app/middleware/' . $middleware . '.php';
        if (!file_exists($middlewareFile)) {
            http_response_code(500);
            die("Middleware not found: {$middleware}");
        }
        require_once $middlewareFile;
        $class = pathinfo($middleware, PATHINFO_FILENAME);
        if (!class_exists($class)) {
            http_response_code(500);
            die("Middleware class not found: {$class}");
        }
        $instance = new $class();
        if (!method_exists($instance, 'handle')) {
            http_response_code(500);
            die("Middleware {$class} missing handle() method.");
        }
        $instance->handle();
    }
}

$GLOBALS['route'] = $route;

$currentRoute = resolveRoute($routes, $route, $requestMethod);

if ($currentRoute !== null) {
    $isGuestPostInsecure = ($route === 'login' && $requestMethod === 'POST')
        || ($route === 'register' && $requestMethod === 'POST')
        || ($route === 'logout' && $requestMethod === 'POST');
    $isWriteRequest = in_array(strtoupper($requestMethod), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    $isAuthenticated = function_exists('is_logged_in') && is_logged_in();
    if ($isAuthenticated || $isWriteRequest || $isGuestPostInsecure) {
        try {
            $ctx = ['route' => $route, 'method' => $requestMethod];
            if (!empty($_GET)) {
                $ctx['$_GET'] = $_GET;
            }
            if ($isWriteRequest && !empty($_POST)) {
                $redacted = $_POST;
                foreach (array_keys($redacted) as $k) {
                    if (stripos($k, 'password') !== false || stripos($k, 'token') !== false || stripos($k, 'secret') !== false) {
                        $redacted[$k] = '[REDACTED]';
                    }
                }
                $ctx['$_POST'] = $redacted;
            }
            system_log('INFO', "Route hit: {$requestMethod} /{$route}", $ctx);
        } catch (\Throwable $e) {
            // never let logging break the request.
        }
    }
}

if ($currentRoute === null) {
    http_response_code(404);
    require __DIR__ . '/resources/views/404.php';
    exit;
}

if (!empty($currentRoute['middleware'])) {
    $middlewareList = is_array($currentRoute['middleware'])
        ? $currentRoute['middleware']
        : [$currentRoute['middleware']];
    runMiddleware($middlewareList);
}

if (isset($currentRoute['controller'])) {
    $controllerPath = $currentRoute['controller'];
    $controllerFile = __DIR__ . '/app/controllers/' . $controllerPath . '.php';

    if (!file_exists($controllerFile)) {
        http_response_code(500);
        die("Controller not found: {$controllerPath}");
    }

    require_once $controllerFile;

    $parts = explode('/', str_replace('\\', '/', $controllerPath));
    $controllerClass = end($parts);

    if (!class_exists($controllerClass)) {
        http_response_code(500);
        die("Controller class not found: {$controllerClass}");
    }

    $controller = new $controllerClass();
    $action = $currentRoute['action'] ?? 'index';

    if (!method_exists($controller, $action)) {
        http_response_code(500);
        die("Controller action not found: {$controllerClass}::{$action}()");
    }

    $controller->$action();
    old_clear();
    exit;
}

if (isset($currentRoute['view'])) {
    $view = __DIR__ . '/resources/views/' . $currentRoute['view'] . '.php';
    if (file_exists($view)) {
        $pageTitle = $currentRoute['title'] ?? pathinfo($currentRoute['view'], PATHINFO_FILENAME);
        require $view;
        old_clear();
        exit;
    }
}

http_response_code(404);
require __DIR__ . '/resources/views/404.php';