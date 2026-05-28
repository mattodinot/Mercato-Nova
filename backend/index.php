<?php
declare(strict_types=1);

/**
 * Point d'entree unique de l'API REST.
 * Utilise avec `php -S 127.0.0.1:8000 backend/index.php` en developpement.
 */

require __DIR__ . '/bootstrap.php';

// Si un fichier statique est demande via le serveur built-in PHP, on laisse passer.
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    if ($reqPath !== '/' && is_file(__DIR__ . '/../frontend' . $reqPath)) {
        return false;
    }
}

$router = new Router();

$router->get ('/api/auth/me',       [AuthController::class, 'me']);
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login',    [AuthController::class, 'login']);
$router->post('/api/auth/logout',   [AuthController::class, 'logout']);

$router->get('/api/categories',       [ArtworkController::class, 'categories']);
$router->get('/api/artworks',         [ArtworkController::class, 'index']);
$router->get('/api/artworks/{id}',    [ArtworkController::class, 'show']);

// Healthcheck
$router->get('/api/health', function (): void {
    Response::ok(['status' => 'ok', 'time' => date(DATE_ATOM)]);
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router->dispatch($method, $path);
