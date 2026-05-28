<?php
declare(strict_types=1);

/**
 * Gestion de la session utilisateur cote serveur.
 * Apres login on regenere l'ID de session pour limiter le risque de fixation.
 */
final class Auth
{
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'           => (int) $user['id'],
            'email'        => $user['email'],
            'role'         => $user['role'],
            'display_name' => $user['display_name'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            Response::error('Authentification requise.', 401);
        }
        return $u;
    }

    public static function requireRole(string ...$roles): array
    {
        $u = self::requireLogin();
        if (!in_array($u['role'], $roles, true)) {
            Response::error('Permission refusee.', 403);
        }
        return $u;
    }
}
