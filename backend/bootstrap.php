<?php
declare(strict_types=1);

/**
 * Bootstrap : autoload simple, .env, session, CORS, gestion d'erreurs.
 * Volontairement sans Composer pour rester minimaliste sur la stack pedagogique.
 */

require_once __DIR__ . '/config/env.php';
Env::load(dirname(__DIR__) . '/.env');

// Chargement par convention : un fichier = une classe (PSR-0-like simplifie).
spl_autoload_register(function (string $class): void {
    $bases = [
        __DIR__ . '/config/',
        __DIR__ . '/core/',
        __DIR__ . '/helpers/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
    ];
    foreach ($bases as $base) {
        $file = $base . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

// Session sécurisée
session_name(Env::get('SESSION_NAME', 'MNSESSID'));
session_set_cookie_params([
    'lifetime' => (int) (Env::get('SESSION_LIFETIME', '7200')),
    'path'     => '/',
    'secure'   => (Env::get('APP_ENV') === 'production'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// CORS (utile quand le frontend React tourne sur un autre port)
$origin = Env::get('CORS_ORIGIN', '*');
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Vary: Origin');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Erreurs non capturees -> reponse JSON propre (jamais de stack trace cote client)
set_exception_handler(function (Throwable $e): void {
    $isDev = Env::get('APP_ENV') === 'development';
    error_log('[MN] ' . $e->getMessage());
    $payload = ['ok' => false, 'error' => 'Erreur serveur.'];
    if ($isDev) {
        $payload['debug'] = [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
});
