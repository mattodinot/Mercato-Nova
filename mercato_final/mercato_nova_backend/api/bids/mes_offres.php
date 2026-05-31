<?php
// ============================================================
//  MERCATO NOVA — Mes offres d'enchères
//  Fichier : api/bids/mes_offres.php
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

$pdo = getDB();

// Récupère toutes les offres de l'utilisateur connecté
// avec le titre de l'œuvre correspondante
$requete = $pdo->prepare("
    SELECT
        b.id_bid,
        b.id_artwork,
        b.montant,
        b.date_offre,
        b.est_gagnante,
        a.titre,
        a.statut AS statut_oeuvre,
        a.date_fin_enchere
    FROM bid b
    JOIN artwork a ON b.id_artwork = a.id_artwork
    WHERE b.id_user = ?
    ORDER BY b.date_offre DESC
");
$requete->execute([getCurrentUserId()]);

echo json_encode([
    'success' => true,
    'bids'    => $requete->fetchAll(),
]);
