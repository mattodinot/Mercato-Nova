<?php
// ============================================================
//  MERCATO NOVA — Détail et historique d'une négociation
//  Fichier : api/negotiations/show.php
//  Méthode : GET
//  Paramètre URL : ?id_nego=2
//  Accès   : vendeur ou acheteur participant
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();

$pdo    = getDB();
$idNego = (int) ($_GET['id_nego'] ?? 0);
$userId = getCurrentUserId();

if ($idNego <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID invalide.']);
    exit;
}

// ── 1. Récupération de la négociation ─────────────────────────
$reqNego = $pdo->prepare("
    SELECT n.*, a.id_user AS id_vendeur, a.titre, a.prix_base
    FROM negotiation n
    JOIN artwork a ON n.id_artwork = a.id_artwork
    WHERE n.id_nego = ?
");
$reqNego->execute([$idNego]);
$nego = $reqNego->fetch();

if (!$nego) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Négociation introuvable.']);
    exit;
}

// ── 2. Vérification : seuls les participants peuvent voir ─────
$estParticipant = ($userId === (int) $nego['id_vendeur'])
               || ($userId === (int) $nego['id_acheteur'])
               || (getCurrentUserRole() === 'admin');

if (!$estParticipant) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé.']);
    exit;
}

// ── 3. Récupération des messages ──────────────────────────────
$reqMessages = $pdo->prepare("
    SELECT id_msg, montant_propose, contenu, emetteur, date_envoi
    FROM nego_message
    WHERE id_nego = ?
    ORDER BY date_envoi ASC
");
$reqMessages->execute([$idNego]);
$messages = $reqMessages->fetchAll();

// ── 4. Réponse ────────────────────────────────────────────────
echo json_encode([
    'success'     => true,
    'negotiation' => $nego,
    'messages'    => $messages,
    // Indique à quel rôle appartient l'utilisateur courant
    'mon_role'    => ($userId === (int) $nego['id_vendeur']) ? 'vendeur' : 'acheteur',
]);
