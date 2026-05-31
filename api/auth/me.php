<?php
// ============================================================
//  MERCATO NOVA — Utilisateur connecté
//  Fichier : api/auth/me.php
//  Méthode : GET
//  Réponse  : { success, user } ou { success, error }
//
//  Utilisé par le frontend pour vérifier si la session
//  est toujours active au chargement de chaque page.
// ============================================================

// ── 1. En-têtes HTTP ─────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── 2. Chargement des dépendances ────────────────────────────
require_once '../../config/db.php';
require_once '../../config/session.php';

// ── 3. Vérification de la session ────────────────────────────
// Si personne n'est connecté, on renvoie une erreur 401
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Aucune session active. Veuillez vous connecter.',
    ]);
    exit;
}

// ── 4. Récupération des infos à jour depuis la BDD ───────────
// On relit depuis la BDD plutôt que depuis la session
// pour avoir les informations les plus récentes (ex: changement de rôle)
$pdo = getDB();

$requete = $pdo->prepare(
    'SELECT id_user, prenom, nom, email, role, bio, avatar, date_inscription
     FROM user
     WHERE id_user = ?
     LIMIT 1'
);
$requete->execute([getCurrentUserId()]);
$utilisateur = $requete->fetch();

// Si l'utilisateur a été supprimé entre temps
if (!$utilisateur) {
    logoutUser();
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Compte introuvable. Veuillez vous reconnecter.',
    ]);
    exit;
}

// ── 5. Réponse de succès ──────────────────────────────────────
echo json_encode([
    'success' => true,
    'user'    => [
        'id_user'          => (int) $utilisateur['id_user'],
        'prenom'           => $utilisateur['prenom'],
        'nom'              => $utilisateur['nom'],
        'email'            => $utilisateur['email'],
        'role'             => $utilisateur['role'],
        'bio'              => $utilisateur['bio'],
        'avatar'           => $utilisateur['avatar'],
        'date_inscription' => $utilisateur['date_inscription'],
    ],
]);
