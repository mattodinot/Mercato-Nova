<?php
// ============================================================
//  MERCATO NOVA — Modifier une annonce
//  Fichier : api/artworks/update.php
//  Méthode : PUT
//  Accès   : vendeur propriétaire ou admin
//  Body JSON : { id_artwork, titre, description, ... }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

require_once '../../config/db.php';
require_once '../../config/session.php';

// ── 1. Vérification de la connexion ──────────────────────────
requireLogin();

// ── 2. Lecture du corps JSON ──────────────────────────────────
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de la requête invalide.']);
    exit;
}

$idArtwork = (int) ($data['id_artwork'] ?? 0);

if ($idArtwork <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID d\'œuvre invalide.']);
    exit;
}

// ── 3. Récupération de l'annonce en base ─────────────────────
$pdo = getDB();

$requete = $pdo->prepare('SELECT id_user, statut FROM artwork WHERE id_artwork = ?');
$requete->execute([$idArtwork]);
$oeuvre  = $requete->fetch();

if (!$oeuvre) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Œuvre introuvable.']);
    exit;
}

// ── 4. Vérification des droits ────────────────────────────────
// Seul le propriétaire ou un admin peut modifier
$userId = getCurrentUserId();
$role   = getCurrentUserRole();

if ($oeuvre['id_user'] !== $userId && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous n\'êtes pas autorisé à modifier cette annonce.']);
    exit;
}

// Une annonce vendue ne peut plus être modifiée
if ($oeuvre['statut'] === 'vendue') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Une annonce vendue ne peut pas être modifiée.']);
    exit;
}

// ── 5. Récupération des champs à modifier ─────────────────────
$titre       = trim($data['titre']       ?? '');
$description = trim($data['description'] ?? '');
$technique   = trim($data['technique']   ?? '');
$dimensions  = trim($data['dimensions']  ?? '');
$livraison   = trim($data['livraison']   ?? '');

if (empty($titre)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le titre est obligatoire.']);
    exit;
}

// ── 6. Mise à jour en base ────────────────────────────────────
// Quand un vendeur modifie, l'annonce repasse en attente de validation
$nouveauStatut = ($role === 'admin') ? $oeuvre['statut'] : 'en_attente';

$update = $pdo->prepare("
    UPDATE artwork
    SET titre       = ?,
        description = ?,
        technique   = ?,
        dimensions  = ?,
        livraison   = ?,
        statut      = ?
    WHERE id_artwork = ?
");

$update->execute([
    $titre,
    $description,
    $technique,
    $dimensions,
    $livraison,
    $nouveauStatut,
    $idArtwork,
]);

// ── 7. Réponse ────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => $role === 'admin'
        ? 'Annonce mise à jour.'
        : 'Annonce mise à jour. Elle doit être revalidée par un administrateur.',
]);
