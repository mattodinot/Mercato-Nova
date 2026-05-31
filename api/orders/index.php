<?php
// ============================================================
//  MERCATO NOVA — Historique des commandes
//  Fichier : api/orders/index.php
//  Méthode : GET
//  Accès   : utilisateur connecté
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
$userId = getCurrentUserId();

// Récupère les commandes de l'utilisateur connecté
// avec le titre de l'œuvre et le nom du vendeur
$requete = $pdo->prepare("
    SELECT
        o.id_order,
        o.montant_final,
        o.mode_achat,
        o.statut,
        o.date_achat,
        a.id_artwork,
        a.titre,
        a.technique,
        a.dimensions,
        u.nom AS vendeur_nom,
        u.prenom AS vendeur_prenom
    FROM `order` o
    JOIN artwork a ON o.id_artwork = a.id_artwork
    JOIN user u    ON a.id_user    = u.id_user
    WHERE o.id_acheteur = ?
    ORDER BY o.date_achat DESC
");
$requete->execute([$userId]);

echo json_encode([
    'success' => true,
    'orders'  => $requete->fetchAll(),
]);
