<?php
// ============================================================
//  MERCATO NOVA — Inscription
//  Fichier : api/auth/register.php
//  Méthode : POST
//  Body JSON : { prenom, nom, email, password, role }
//  Réponse  : { success, message } ou { success, error }
// ============================================================

// ── 1. En-têtes HTTP ─────────────────────────────────────────
// Autorise les requêtes depuis le frontend (même domaine en local)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Réponse aux pré-requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── 2. Vérification de la méthode HTTP ───────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée. Utilisez POST.']);
    exit;
}

// ── 3. Chargement des dépendances ────────────────────────────
require_once '../../config/db.php';
require_once '../../config/session.php';

// ── 4. Lecture et décodage du corps JSON ─────────────────────
$corps = file_get_contents('php://input');
$data  = json_decode($corps, true);

// Vérification que le JSON est valide
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de la requête invalide.']);
    exit;
}

// ── 5. Récupération et nettoyage des champs ───────────────────
// trim() supprime les espaces en début/fin de chaîne
$prenom   = trim($data['prenom']   ?? '');
$nom      = trim($data['nom']      ?? '');
$email    = trim($data['email']    ?? '');
$password = $data['password']      ?? '';
$role     = trim($data['role']     ?? 'acheteur');

// ── 6. Validation des champs ──────────────────────────────────

// Champs obligatoires
if (empty($prenom) || empty($nom) || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tous les champs sont obligatoires.']);
    exit;
}

// Format de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'L\'adresse e-mail n\'est pas valide.']);
    exit;
}

// Longueur du mot de passe
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.']);
    exit;
}

// Rôle autorisé (on ne permet pas de créer un compte admin via l'inscription publique)
$rolesAutorises = ['acheteur', 'vendeur', 'galerie'];
if (!in_array($role, $rolesAutorises)) {
    $role = 'acheteur'; // Valeur par défaut si rôle invalide
}

// ── 7. Connexion à la base de données ────────────────────────
$pdo = getDB();

// ── 8. Vérification que l'email n'est pas déjà utilisé ───────
$requete = $pdo->prepare('SELECT id_user FROM user WHERE email = ?');
$requete->execute([$email]);

if ($requete->fetch()) {
    // Un compte avec cet email existe déjà
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'error' => 'Cette adresse e-mail est déjà utilisée.']);
    exit;
}

// ── 9. Hashage du mot de passe ────────────────────────────────
// password_hash() utilise bcrypt par défaut
// On ne stocke JAMAIS le mot de passe en clair dans la BDD
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// ── 10. Insertion en base de données ─────────────────────────
$insertion = $pdo->prepare(
    'INSERT INTO user (prenom, nom, email, password_hash, role)
     VALUES (?, ?, ?, ?, ?)'
);

$insertion->execute([$prenom, $nom, $email, $passwordHash, $role]);

// Récupération de l'ID du nouvel utilisateur
$idNouvelUtilisateur = $pdo->lastInsertId();

// ── 11. Réponse de succès ─────────────────────────────────────
http_response_code(201); // Created
echo json_encode([
    'success' => true,
    'message' => 'Compte créé avec succès.',
    'id_user' => (int) $idNouvelUtilisateur,
]);
