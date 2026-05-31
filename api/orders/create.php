<?php
// ============================================================
//  MERCATO NOVA — Créer une commande (achat immédiat)
//  Fichier : api/orders/create.php
//  Méthode : POST
//  Accès   : acheteur connecté
//  Body JSON : { id_artwork, montant_final, mode_achat }
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
$data         = json_decode(file_get_contents('php://input'), true);
$idArtwork    = (int)   ($data['id_artwork']    ?? 0);
$montantFinal = (float) ($data['montant_final'] ?? 0);
$modeAchat    = trim(    $data['mode_achat']    ?? 'immediat');

if ($idArtwork <= 0 || $montantFinal <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

$pdo    = getDB();
$userId = getCurrentUserId();

// ── 2. Transaction pour garantir la cohérence ─────────────────
$pdo->beginTransaction();

try {
    // Récupération de l'œuvre avec verrouillage
    $reqOeuvre = $pdo->prepare("
        SELECT id_user, titre, statut, type_vente, prix_base
        FROM artwork WHERE id_artwork = ? FOR UPDATE
    ");
    $reqOeuvre->execute([$idArtwork]);
    $oeuvre = $reqOeuvre->fetch();

    // ── 3. Vérifications ──────────────────────────────────────
    if (!$oeuvre) {
        throw new Exception('Œuvre introuvable.');
    }

    if ($oeuvre['statut'] !== 'active') {
        throw new Exception('Cette œuvre n\'est plus disponible à l\'achat.');
    }

    if ($oeuvre['id_user'] === $userId) {
        throw new Exception('Vous ne pouvez pas acheter votre propre œuvre.');
    }

    // Pour un achat immédiat : vérifier que le prix est correct
    if ($modeAchat === 'immediat' && $montantFinal != $oeuvre['prix_base']) {
        $montantFinal = (float) $oeuvre['prix_base']; // On utilise le prix officiel
    }

    // ── 4. Création de la commande ────────────────────────────
    $pdo->prepare("
        INSERT INTO `order` (id_artwork, id_acheteur, montant_final, mode_achat, statut)
        VALUES (?, ?, ?, ?, 'confirmee')
    ")->execute([$idArtwork, $userId, $montantFinal, $modeAchat]);

    $idCommande = $pdo->lastInsertId();

    // ── 5. Marquer l'œuvre comme vendue ───────────────────────
    $pdo->prepare("UPDATE artwork SET statut = 'vendue' WHERE id_artwork = ?")
        ->execute([$idArtwork]);

    // ── 6. Notification au vendeur ────────────────────────────
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'commande_confirmee', ?)
    ")->execute([
        $oeuvre['id_user'],
        "Votre œuvre \"{$oeuvre['titre']}\" a été achetée pour {$montantFinal} €.",
    ]);

    // ── 7. Notification à l'acheteur ─────────────────────────
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'commande_confirmee', ?)
    ")->execute([
        $userId,
        "Votre achat de \"{$oeuvre['titre']}\" pour {$montantFinal} € est confirmé.",
    ]);

    $pdo->commit();

    http_response_code(201);
    echo json_encode([
        'success'    => true,
        'message'    => 'Commande confirmée avec succès !',
        'id_order'   => (int) $idCommande,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
