<?php

namespace App\Core;

/**
 * Lightweight Custom HTTP Request Router & Middleware Dispatcher.
 * 
 * Provides regex-based URI pattern matching, route middleware execution, global CSRF defense, and centralized error handling.
 */
class Router
{
    private array $routes = [];

    /**
     * Add a route to the routing table.
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri The URI pattern (e.g., '/documents/{id}')
     * @param array|callable $action The controller class and method [Controller::class, 'method'] or a closure
     * @param array $middleware Array of middleware classes to run before the action
     */
    public function add(string $method, string $uri, $action, array $middleware = []): self
    {
        // Convert route placeholders like {id} into named regex capture groups (?P<id>[a-zA-Z0-9_-]+) for dynamic parameter binding
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_-]+)', $uri);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method' => strtoupper($method),
            'uri' => $uri,
            'pattern' => $pattern,
            'action' => $action,
            'middleware' => $middleware
        ];

        return $this;
    }

    public function get(string $uri, $action, array $middleware = []): self
    {
        return $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, $action, array $middleware = []): self
    {
        return $this->add('POST', $uri, $action, $middleware);
    }

    /**
     * Dispatch the current request to the appropriate route.
     * 
     * Core request dispatcher. Handles URI pattern matching, named parameter extraction, 
     * middleware execution, and global CSRF protection.
     * 
     * @param string $requestUri The requested URI path
     * @param string $requestMethod The HTTP method used
     */
    public function dispatch(string $requestUri, string $requestMethod): void
    {
        // Enforce global CSRF token verification on state-mutating POST requests using constant-time hash comparison to prevent timing attacks
        if (strtoupper($requestMethod) === 'POST') {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
                http_response_code(419);
                echo "419 Page Expired: CSRF token mismatch. Please refresh and try again.";
                exit;
            }
        }

        // Strip query string parameters to isolate the path string for route pattern evaluation
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        foreach ($this->routes as $route) {
            // Match request method and compile regex pattern against the current request path
            if ($route['method'] === strtoupper($requestMethod) && preg_match($route['pattern'], $path, $matches)) {
                
                // Filter out positional regex indexes, preserving only named string keys to pass clean parameter arrays to controllers
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run Middleware Pipeline: dynamically instantiate each assigned middleware class and unpack optional colon-delimited arguments
                foreach ($route['middleware'] as $middlewareConfig) {
                    $parts = explode(':', $middlewareConfig);
                    $middlewareClass = $parts[0];
                    $args = isset($parts[1]) ? explode(',', $parts[1]) : [];
                    
                    $middleware = new $middlewareClass();
                    $middleware->handle(...$args);
                }

                $action = $route['action'];

                try {
                    // Dispatch directly if action is a Closure or callable function
                    if (is_callable($action)) {
                        call_user_func_array($action, $params);
                        return;
                    }

                    // Dynamically instantiate Controller class and invoke target action method with named URI parameters
                    if (is_array($action) && count($action) === 2) {
                        [$class, $method] = $action;
                        if (class_exists($class)) {
                            $controller = new $class();
                            if (method_exists($controller, $method)) {
                                call_user_func_array([$controller, $method], $params);
                                return;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Log unhandled exceptions for server debugging while preventing exposure of raw stack traces to end-users
                    error_log("Unhandled Exception in Router: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

                    // Differentiate between asynchronous XHR/fetch requests and full page navigations to return appropriate JSON or HTTP redirects
                    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

                    if ($isAjax) {
                        http_response_code(500);
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'An internal server error occurred.'
                        ]);
                        exit;
                    }

                    $_SESSION['error'] = "An unexpected error occurred. Please try again or contact support.";
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
                    exit;
                }
            }
        }

        // Return HTTP 404 response if no matching route pattern was matched in the loop
        $this->abort(404);
    }

    private function abort(int $code = 404): void
    {
        http_response_code($code);
        echo "404 Not Found";
        exit;
    }
}

