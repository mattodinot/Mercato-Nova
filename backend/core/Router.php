<?php
declare(strict_types=1);

/**
 * Mini routeur REST :
 *   $router->get('/artworks', [ArtworkController::class, 'index']);
 *   $router->post('/auth/login', [AuthController::class, 'login']);
 * Supporte les parametres simples : '/artworks/{id}'.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:callable|array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void    { $this->add('GET',    $path, $handler); }
    public function post(string $path, callable|array $handler): void   { $this->add('POST',   $path, $handler); }
    public function put(string $path, callable|array $handler): void    { $this->add('PUT',    $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => "#^$pattern$#",
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $handler = [new $class(), $action];
                }
                call_user_func($handler, $params);
                return;
            }
        }
        Response::error('Route inconnue : ' . $method . ' ' . $path, 404);
    }
}
