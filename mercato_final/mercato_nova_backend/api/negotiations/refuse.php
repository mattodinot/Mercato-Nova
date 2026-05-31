<?php
// ============================================================
//  MERCATO NOVA — Refuser / Abandonner une négociation
//  Fichier : api/negotiations/refuse.php
//  Méthode : POST
//  Accès   : vendeur ou acheteur participant
//  Body JSON : { id_nego }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();

// ── 1. Lecture de l'ID ────────────────────────────────────────
$data   = json_decode(file_get_contents('php://input'), true);
$idNego = (int) ($data['id_nego'] ?? 0);

if ($idNego <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide.']);
    exit;
}

$pdo    = getDB();
$userId = getCurrentUserId();

// ── 2. Récupération de la négociation ─────────────────────────
$reqNego = $pdo->prepare("
    SELECT n.*, a.id_user AS id_vendeur, a.titre
    FROM negotiation n
    JOIN artwork a ON n.id_artwork = a.id_artwork
    WHERE n.id_nego = ?
");
$reqNego->execute([$idNego]);
$nego = $reqNego->fetch();

if (!$nego || $nego['statut'] !== 'en_cours') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Négociation introuvable ou déjà terminée.']);
    exit;
}

// ── 3. Vérification des droits ────────────────────────────────
$estVendeur  = ($userId === (int) $nego['id_vendeur']);
$estAcheteur = ($userId === (int) $nego['id_acheteur']);

if (!$estVendeur && !$estAcheteur) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé.']);
    exit;
}

// Vendeur = refuse / Acheteur = abandonne
$nouveauStatut = $estVendeur ? 'refuse' : 'abandonne';
$messageRefus  = $estVendeur
    ? "Votre offre pour \"{$nego['titre']}\" a été refusée par le vendeur."
    : "La négociation pour \"{$nego['titre']}\" a été abandonnée par l'acheteur.";

// ── 4. Mise à jour et notification ───────────────────────────
$pdo->beginTransaction();

try {
    $pdo->prepare("UPDATE negotiation SET statut = ? WHERE id_nego = ?")
        ->execute([$nouveauStatut, $idNego]);

    // Notifier l'autre partie
    $idDestinataire = $estVendeur ? $nego['id_acheteur'] : $nego['id_vendeur'];
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'nego_refusee', ?)
    ")->execute([$idDestinataire, $messageRefus]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $estVendeur ? 'Offre refusée.' : 'Négociation abandonnée.',
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors du refus.']);
}
