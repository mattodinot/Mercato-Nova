<?php
// ============================================================
//  MERCATO NOVA — Accepter une négociation
//  Fichier : api/negotiations/accept.php
//  Méthode : POST
//  Accès   : vendeur ou acheteur participant
//  Body JSON : { id_nego }
//
//  Quand une offre est acceptée :
//  1. La négociation passe en statut 'accepte'
//  2. Une commande est créée automatiquement
//  3. L'œuvre est marquée comme vendue
//  4. Les deux parties reçoivent une notification
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

// ── 1. Lecture de l'ID de négociation ────────────────────────
$data   = json_decode(file_get_contents('php://input'), true);
$idNego = (int) ($data['id_nego'] ?? 0);

if ($idNego <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de négociation invalide.']);
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
    LIMIT 1
");
$reqNego->execute([$idNego]);
$nego = $reqNego->fetch();

if (!$nego) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Négociation introuvable.']);
    exit;
}

// ── 3. Vérifications ──────────────────────────────────────────
if ($nego['statut'] !== 'en_cours') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cette négociation n\'est plus active.']);
    exit;
}

// Seuls les participants peuvent accepter
$estVendeur  = ($userId === (int) $nego['id_vendeur']);
$estAcheteur = ($userId === (int) $nego['id_acheteur']);

if (!$estVendeur && !$estAcheteur) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous ne participez pas à cette négociation.']);
    exit;
}

// ── 4. Récupération de la dernière offre (montant accepté) ────
$reqDerniere = $pdo->prepare("
    SELECT montant_propose FROM nego_message
    WHERE id_nego = ?
    ORDER BY date_envoi DESC
    LIMIT 1
");
$reqDerniere->execute([$idNego]);
$montantAccepte = (float) $reqDerniere->fetchColumn();

// ── 5. Transaction : acceptation + commande + notifications ───
$pdo->beginTransaction();

try {
    // Marquer la négociation comme acceptée
    $pdo->prepare("UPDATE negotiation SET statut = 'accepte' WHERE id_nego = ?")
        ->execute([$idNego]);

    // Créer la commande
    $pdo->prepare("
        INSERT INTO `order` (id_artwork, id_acheteur, montant_final, mode_achat, statut)
        VALUES (?, ?, ?, 'negociation', 'confirmee')
    ")->execute([$nego['id_artwork'], $nego['id_acheteur'], $montantAccepte]);

    // Marquer l'œuvre comme vendue
    $pdo->prepare("UPDATE artwork SET statut = 'vendue' WHERE id_artwork = ?")
        ->execute([$nego['id_artwork']]);

    // Notification à l'acheteur
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'nego_acceptee', ?)
    ")->execute([
        $nego['id_acheteur'],
        "Votre négociation pour \"{$nego['titre']}\" a été acceptée ! Montant : {$montantAccepte} €.",
    ]);

    // Notification au vendeur
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'nego_acceptee', ?)
    ")->execute([
        $nego['id_vendeur'],
        "Accord conclu pour \"{$nego['titre']}\" à {$montantAccepte} €.",
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Accord conclu pour {$montantAccepte} € ! La commande a été créée.",
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'acceptation.']);
}
