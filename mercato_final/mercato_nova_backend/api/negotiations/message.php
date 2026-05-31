<?php
// ============================================================
//  MERCATO NOVA — Envoyer une contre-offre
//  Fichier : api/negotiations/message.php
//  Méthode : POST
//  Accès   : vendeur ou acheteur participant à la négociation
//  Body JSON : { id_nego, montant_propose, contenu? }
//
//  Règles métier :
//  - Maximum 5 échanges
//  - Les participants alternent (acheteur puis vendeur, etc.)
//  - La négociation ne doit pas être expirée
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
$idNego         = (int)   ($data['id_nego']          ?? 0);
$montantPropose = (float) ($data['montant_propose']  ?? 0);
$contenu        = trim(    $data['contenu']           ?? '');

if ($idNego <= 0 || $montantPropose <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

$pdo    = getDB();
$userId = getCurrentUserId();

// ── 2. Récupération de la négociation avec l'œuvre ───────────
$reqNego = $pdo->prepare("
    SELECT n.*, a.id_user AS id_vendeur, a.titre
    FROM negotiation n
    JOIN artwork a ON n.id_artwork = a.id_artwork
    WHERE n.id_nego = ?
    LIMIT 1
");
$reqNego->execute([$idNego]);
$nego = $reqNego->fetch();

if (!$nego) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Négociation introuvable.']);
    exit;
}

// ── 3. Vérifications métier ───────────────────────────────────

// La négociation doit être en cours
if ($nego['statut'] !== 'en_cours') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cette négociation est terminée.']);
    exit;
}

// La négociation n'est pas expirée
if (strtotime($nego['date_expiration']) < time()) {
    // On la marque comme expirée en base
    $pdo->prepare("UPDATE negotiation SET statut = 'expire' WHERE id_nego = ?")
        ->execute([$idNego]);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cette négociation a expiré.']);
    exit;
}

// Maximum 5 échanges
if ($nego['nb_echanges'] >= 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Nombre maximum d\'échanges atteint (5/5).']);
    exit;
}

// Déterminer si l'utilisateur est le vendeur ou l'acheteur
$estVendeur  = ($userId === (int) $nego['id_vendeur']);
$estAcheteur = ($userId === (int) $nego['id_acheteur']);

if (!$estVendeur && !$estAcheteur) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous ne participez pas à cette négociation.']);
    exit;
}

// Vérifier que c'est bien le tour de cet utilisateur
// (ils doivent alterner : acheteur d'abord, puis vendeur, etc.)
$dernier = $pdo->prepare("
    SELECT emetteur FROM nego_message WHERE id_nego = ? ORDER BY date_envoi DESC LIMIT 1
");
$dernier->execute([$idNego]);
$dernierEmetteur = $dernier->fetchColumn();

$emetteurActuel = $estVendeur ? 'vendeur' : 'acheteur';
if ($dernierEmetteur === $emetteurActuel) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'C\'est à l\'autre partie de répondre.']);
    exit;
}

// ── 4. Enregistrement du message et mise à jour ──────────────
$pdo->beginTransaction();

try {
    // Ajout du message
    $pdo->prepare("
        INSERT INTO nego_message (id_nego, montant_propose, contenu, emetteur)
        VALUES (?, ?, ?, ?)
    ")->execute([$idNego, $montantPropose, $contenu, $emetteurActuel]);

    // Incrémentation du compteur d'échanges
    // Réinitialisation de l'expiration : encore 48h à partir de maintenant
    $pdo->prepare("
        UPDATE negotiation
        SET nb_echanges     = nb_echanges + 1,
            date_expiration = DATE_ADD(NOW(), INTERVAL 48 HOUR)
        WHERE id_nego = ?
    ")->execute([$idNego]);

    // Notification à l'autre partie
    $idDestinataire = $estVendeur ? $nego['id_acheteur'] : $nego['id_vendeur'];
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'nego_recue', ?)
    ")->execute([
        $idDestinataire,
        "Nouvelle contre-offre reçue : {$montantPropose} € pour \"{$nego['titre']}\".",
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Contre-offre envoyée avec succès.',
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'envoi.']);
}
