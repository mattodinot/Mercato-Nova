<?php
// ============================================================
//  MERCATO NOVA — Supprimer une annonce
//  Fichier : api/artworks/delete.php
//  Méthode : DELETE
//  Accès   : vendeur propriétaire ou admin
//  Body JSON : { id_artwork }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();

// ── 1. Lecture de l'ID ────────────────────────────────────────
$data      = json_decode(file_get_contents('php://input'), true);
$idArtwork = (int) ($data['id_artwork'] ?? 0);

if ($idArtwork <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide.']);
    exit;
}

// ── 2. Récupération de l'annonce ─────────────────────────────
$pdo     = getDB();
$req     = $pdo->prepare('SELECT id_user, statut FROM artwork WHERE id_artwork = ?');
$req->execute([$idArtwork]);
$oeuvre  = $req->fetch();

if (!$oeuvre) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Œuvre introuvable.']);
    exit;
}

// ── 3. Vérification des droits ────────────────────────────────
$userId = getCurrentUserId();
$role   = getCurrentUserRole();

if ($oeuvre['id_user'] !== $userId && $role !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous n\'êtes pas autorisé à supprimer cette annonce.']);
    exit;
}

// Une annonce avec des enchères actives ne peut pas être supprimée
if ($oeuvre['statut'] === 'active') {
    $nbOffres = $pdo->prepare('SELECT COUNT(*) FROM bid WHERE id_artwork = ?');
    $nbOffres->execute([$idArtwork]);
    if ($nbOffres->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Impossible de supprimer une annonce avec des enchères en cours.']);
        exit;
    }
}

// ── 4. Suppression (CASCADE supprime aussi les bids/negociations) ─
$pdo->prepare('DELETE FROM artwork WHERE id_artwork = ?')->execute([$idArtwork]);

// ── 5. Réponse ────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Annonce supprimée avec succès.',
]);
