<?php
// ============================================================
//  MERCATO NOVA — Configuration base de données
//  Fichier : config/db.php
//  À inclure avec require_once dans chaque fichier API PHP
// ============================================================

// ── Paramètres de connexion ──────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'mercato_nova');
define('DB_USER',    'root');       // Utilisateur MySQL par défaut XAMPP/WAMP
define('DB_PASS',    '');           // Mot de passe vide par défaut sur XAMPP
define('DB_CHARSET', 'utf8mb4');

// ── Connexion PDO ────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;

    // Singleton : on ne crée la connexion qu'une seule fois
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        // Lève une exception en cas d'erreur SQL (ne pas afficher les erreurs en prod)
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

        // Les résultats sont retournés sous forme de tableaux associatifs par défaut
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Désactive l'émulation des requêtes préparées (plus sécurisé)
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // En cas d'échec de connexion, on renvoie une réponse JSON d'erreur
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Connexion à la base de données impossible.',
            // Décommenter la ligne suivante UNIQUEMENT en développement :
            // 'detail' => $e->getMessage(),
        ]);
        exit;
    }

    return $pdo;
}
