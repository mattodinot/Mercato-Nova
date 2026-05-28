<?php
declare(strict_types=1);

/**
 * Protection CSRF : token stocke en session, transmis via header X-CSRF-Token
 * pour les requetes mutatives (POST / PUT / DELETE).
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function check(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $real = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sent) || $real === '' || !hash_equals($real, $sent)) {
            Response::error('Token CSRF invalide.', 419);
        }
    }
}
