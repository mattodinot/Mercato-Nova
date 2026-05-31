<?php
// ============================================================
//  MERCATO NOVA — Mes négociations
//  Fichier : api/negotiations/mes_negociations.php
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

// Récupère les négociations où l'utilisateur est acheteur OU vendeur
$requete = $pdo->prepare("
    SELECT
        n.id_nego,
        n.id_artwork,
        n.statut,
        n.nb_echanges,
        n.date_expiration,
        a.titre,
        a.id_user AS id_vendeur,
        -- Dernière offre proposée
        (SELECT montant_propose FROM nego_message
         WHERE id_nego = n.id_nego
         ORDER BY date_envoi DESC LIMIT 1) AS montant_propose
    FROM negotiation n
    JOIN artwork a ON n.id_artwork = a.id_artwork
    WHERE n.id_acheteur = ? OR a.id_user = ?
    ORDER BY n.date_creation DESC
");
$requete->execute([$userId, $userId]);

echo json_encode([
    'success'      => true,
    'negotiations' => $requete->fetchAll(),
]);
