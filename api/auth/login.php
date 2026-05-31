<?php
// ============================================================
//  MERCATO NOVA — Connexion
//  Fichier : api/auth/login.php
//  Méthode : POST
//  Body JSON : { email, password }
//  Réponse  : { success, user } ou { success, error }
// ============================================================

// ── 1. En-têtes HTTP ─────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── 2. Vérification de la méthode HTTP ───────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

// ── 3. Chargement des dépendances ────────────────────────────
require_once '../../config/db.php';
require_once '../../config/session.php';

// ── 4. Lecture du corps JSON ──────────────────────────────────
$corps = file_get_contents('php://input');
$data  = json_decode($corps, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de la requête invalide.']);
    exit;
}

// ── 5. Récupération des champs ────────────────────────────────
$email    = trim($data['email']    ?? '');
$password = $data['password']      ?? '';

// ── 6. Validation basique ─────────────────────────────────────
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email et mot de passe sont obligatoires.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Format d\'email invalide.']);
    exit;
}

// ── 7. Recherche de l'utilisateur en base ────────────────────
$pdo = getDB();

$requete = $pdo->prepare(
    'SELECT id_user, prenom, nom, email, password_hash, role
     FROM user
     WHERE email = ?
     LIMIT 1'
);
$requete->execute([$email]);
$utilisateur = $requete->fetch();

// ── 8. Vérification du mot de passe ──────────────────────────
// Si l'utilisateur n'existe pas OU si le mot de passe est incorrect
// On renvoie le même message pour ne pas indiquer si l'email existe
if (!$utilisateur || !password_verify($password, $utilisateur['password_hash'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect.']);
    exit;
}

// ── 9. Création de la session ─────────────────────────────────
// La fonction loginUser() est définie dans config/session.php
// Elle appelle session_regenerate_id() pour sécuriser la session
loginUser($utilisateur);

// ── 10. Réponse de succès ─────────────────────────────────────
// On renvoie les infos publiques de l'utilisateur (pas le hash !)
echo json_encode([
    'success' => true,
    'message' => 'Connexion réussie.',
    'user'    => [
        'id_user' => (int) $utilisateur['id_user'],
        'prenom'  => $utilisateur['prenom'],
        'nom'     => $utilisateur['nom'],
        'email'   => $utilisateur['email'],
        'role'    => $utilisateur['role'],
    ],
]);
