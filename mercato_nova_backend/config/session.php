<?php
// ============================================================
//  MERCATO NOVA — Configuration des sessions
//  Fichier : config/session.php
//  À inclure avec require_once avant tout accès à la session
// ============================================================

// ── Sécurisation de la session ───────────────────────────────

// Le cookie de session ne sera accessible qu'en HTTP (pas en JavaScript)
ini_set('session.cookie_httponly', 1);

// Durée de vie du cookie de session : 2 heures (en secondes)
ini_set('session.cookie_lifetime', 7200);

// Durée de vie des données de session côté serveur : 2 heures
ini_set('session.gc_maxlifetime', 7200);

// Utiliser uniquement les cookies pour identifier la session (pas l'URL)
ini_set('session.use_only_cookies', 1);

// Démarrage de la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers session ──────────────────────────────────────────

/**
 * Vérifie si un utilisateur est connecté.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retourne l'ID de l'utilisateur connecté, ou null.
 */
function getCurrentUserId(): ?int {
    return isLoggedIn() ? (int) $_SESSION['user_id'] : null;
}

/**
 * Retourne le rôle de l'utilisateur connecté, ou null.
 */
function getCurrentUserRole(): ?string {
    return isLoggedIn() ? $_SESSION['user_role'] : null;
}

/**
 * Vérifie si l'utilisateur connecté a le rôle demandé.
 * Utilisation : requireRole('admin') ou requireRole('vendeur')
 */
function requireRole(string $role): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Vous devez être connecté pour accéder à cette ressource.',
        ]);
        exit;
    }

    if (getCurrentUserRole() !== $role && getCurrentUserRole() !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Vous n\'avez pas les permissions nécessaires.',
        ]);
        exit;
    }
}

/**
 * Exige simplement que l'utilisateur soit connecté (peu importe le rôle).
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Vous devez être connecté pour accéder à cette ressource.',
        ]);
        exit;
    }
}

/**
 * Connecte un utilisateur en session.
 * À appeler juste après la vérification du mot de passe.
 */
function loginUser(array $user): void {
    // Régénère l'ID de session pour éviter les attaques par fixation de session
    session_regenerate_id(true);

    $_SESSION['user_id']    = $user['id_user'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_nom']   = $user['nom'];
    $_SESSION['user_prenom']= $user['prenom'];
}

/**
 * Déconnecte l'utilisateur et détruit la session.
 */
function logoutUser(): void {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}
