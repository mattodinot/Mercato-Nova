<?php
// ============================================================
//  MERCATO NOVA — Démarrer une négociation
//  Fichier : api/negotiations/create.php
//  Méthode : POST
//  Accès   : acheteur connecté
//  Body JSON : { id_artwork, montant_propose, contenu? }
//
//  Règles métier :
//  - Une seule négociation active par œuvre à la fois
//  - Le vendeur ne peut pas négocier sa propre œuvre
//  - L'œuvre doit être de type 'negociation' et statut 'active'
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

// ── 1. Lecture des données ────────────────────────────────────
$data           = json_decode(file_get_contents('php://input'), true);
$idArtwork      = (int)   ($data['id_artwork']      ?? 0);
$montantPropose = (float) ($data['montant_propose'] ?? 0);
$contenu        = trim(    $data['contenu']          ?? '');

if ($idArtwork <= 0 || $montantPropose <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

$pdo    = getDB();
$userId = getCurrentUserId();

// ── 2. Récupération de l'œuvre ────────────────────────────────
$reqOeuvre = $pdo->prepare('SELECT id_user, type_vente, statut FROM artwork WHERE id_artwork = ?');
$reqOeuvre->execute([$idArtwork]);
$oeuvre = $reqOeuvre->fetch();

if (!$oeuvre) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Œuvre introuvable.']);
    exit;
}

// ── 3. Vérifications métier ───────────────────────────────────
if ($oeuvre['type_vente'] !== 'negociation') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cette œuvre n\'est pas disponible à la négociation.']);
    exit;
}

if ($oeuvre['statut'] !== 'active') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cette œuvre n\'est plus disponible.']);
    exit;
}

if ($oeuvre['id_user'] === $userId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas négocier votre propre œuvre.']);
    exit;
}

// ── 4. Vérification : une seule négociation active à la fois ──
$reqExistante = $pdo->prepare("
    SELECT id_nego FROM negotiation
    WHERE id_artwork = ? AND statut = 'en_cours'
    LIMIT 1
");
$reqExistante->execute([$idArtwork]);

if ($reqExistante->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Une négociation est déjà en cours sur cette œuvre.']);
    exit;
}

// ── 5. Création de la négociation ─────────────────────────────
// Date d'expiration : maintenant + 48 heures
$dateExpiration = date('Y-m-d H:i:s', strtotime('+48 hours'));

$pdo->beginTransaction();

try {
    // Création de la négociation
    $pdo->prepare("
        INSERT INTO negotiation (id_artwork, id_acheteur, statut, nb_echanges, date_expiration)
        VALUES (?, ?, 'en_cours', 1, ?)
    ")->execute([$idArtwork, $userId, $dateExpiration]);

    $idNego = $pdo->lastInsertId();

    // Premier message de l'acheteur
    $pdo->prepare("
        INSERT INTO nego_message (id_nego, montant_propose, contenu, emetteur)
        VALUES (?, ?, ?, 'acheteur')
    ")->execute([$idNego, $montantPropose, $contenu]);

    // Notification au vendeur
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'nego_recue', ?)
    ")->execute([
        $oeuvre['id_user'],
        "Nouvelle proposition de négociation : {$montantPropose} €.",
    ]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Votre offre a été envoyée au vendeur.',
        'id_nego' => (int) $idNego,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la négociation.']);
}
