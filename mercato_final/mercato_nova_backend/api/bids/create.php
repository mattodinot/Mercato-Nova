<?php
// ============================================================
//  MERCATO NOVA — Placer une offre d'enchère
//  Fichier : api/bids/create.php
//  Méthode : POST
//  Accès   : acheteur connecté
//  Body JSON : { id_artwork, montant }
//
//  POINT CLÉ : utilisation d'une transaction SQL pour éviter
//  les incohérences si deux utilisateurs enchérissent en même temps
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

// ── 1. Lecture du corps JSON ──────────────────────────────────
$data      = json_decode(file_get_contents('php://input'), true);
$idArtwork = (int)   ($data['id_artwork'] ?? 0);
$montant   = (float) ($data['montant']    ?? 0);

if ($idArtwork <= 0 || $montant <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données invalides.']);
    exit;
}

$pdo    = getDB();
$userId = getCurrentUserId();

// ── 2. DÉBUT DE LA TRANSACTION ────────────────────────────────
// La transaction garantit qu'aucune autre offre ne peut s'intercaler
// entre notre vérification et notre insertion
$pdo->beginTransaction();

try {
    // ── 3. Récupération de l'œuvre (avec verrouillage de ligne) ─
    // FOR UPDATE verrouille la ligne pour empêcher les lectures concurrentes
    $reqOeuvre = $pdo->prepare("
        SELECT id_user, type_vente, statut, prix_base, surenchere_min, date_fin_enchere
        FROM artwork
        WHERE id_artwork = ?
        FOR UPDATE
    ");
    $reqOeuvre->execute([$idArtwork]);
    $oeuvre = $reqOeuvre->fetch();

    // ── 4. Vérifications métier ──────────────────────────────────

    // L'œuvre existe-t-elle ?
    if (!$oeuvre) {
        throw new Exception('Œuvre introuvable.');
    }

    // Est-ce bien une enchère ?
    if ($oeuvre['type_vente'] !== 'enchere') {
        throw new Exception('Cette œuvre n\'est pas en vente aux enchères.');
    }

    // L'enchère est-elle encore active ?
    if ($oeuvre['statut'] !== 'active') {
        throw new Exception('Cette enchère n\'est plus active.');
    }

    // L'enchère n'est-elle pas terminée ?
    if ($oeuvre['date_fin_enchere'] && strtotime($oeuvre['date_fin_enchere']) < time()) {
        throw new Exception('Cette enchère est terminée.');
    }

    // Le vendeur ne peut pas enchérir sur sa propre œuvre
    if ($oeuvre['id_user'] === $userId) {
        throw new Exception('Vous ne pouvez pas enchérir sur votre propre œuvre.');
    }

    // ── 5. Récupération de la meilleure offre actuelle ────────
    $reqMeilleure = $pdo->prepare("
        SELECT MAX(montant) AS meilleure FROM bid WHERE id_artwork = ?
    ");
    $reqMeilleure->execute([$idArtwork]);
    $meilleure = (float) ($reqMeilleure->fetchColumn() ?? $oeuvre['prix_base']);

    // Calcul du montant minimum requis
    $surenchere = (float) ($oeuvre['surenchere_min'] ?? 50);
    $minimum    = $meilleure + $surenchere;

    // Le montant proposé est-il suffisant ?
    if ($montant < $minimum) {
        throw new Exception("Votre offre est trop basse. Minimum requis : {$minimum} €.");
    }

    // ── 6. Insertion de l'offre ───────────────────────────────
    $pdo->prepare("
        INSERT INTO bid (id_artwork, id_user, montant, est_gagnante)
        VALUES (?, ?, ?, 1)
    ")->execute([$idArtwork, $userId, $montant]);

    // ── 7. L'ancienne meilleure offre n'est plus gagnante ─────
    $pdo->prepare("
        UPDATE bid
        SET est_gagnante = 0
        WHERE id_artwork = ? AND id_user != ? AND est_gagnante = 1
    ")->execute([$idArtwork, $userId]);

    // ── 8. Notification au vendeur ────────────────────────────
    $pdo->prepare("
        INSERT INTO notification (id_user, type, message)
        VALUES (?, 'surenchere', ?)
    ")->execute([
        $oeuvre['id_user'],
        "Nouvelle enchère sur votre annonce : {$montant} €.",
    ]);

    // ── 9. VALIDATION DE LA TRANSACTION ──────────────────────
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Votre offre de {$montant} € a été enregistrée !",
    ]);

} catch (Exception $e) {
    // En cas d'erreur : annulation de toutes les opérations
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
