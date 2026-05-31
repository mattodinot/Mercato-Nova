<?php
// ============================================================
//  MERCATO NOVA — Mes annonces (espace vendeur)
//  Fichier : api/artworks/mes_annonces.php
//  Méthode : GET
//  Accès   : vendeur connecté
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

// Récupère toutes les annonces appartenant à l'utilisateur connecté
$requete = $pdo->prepare("
    SELECT
        a.id_artwork,
        a.titre,
        a.type_vente,
        a.statut,
        a.prix_base,
        a.vues,
        a.date_creation,
        (SELECT COUNT(*) FROM bid b WHERE b.id_artwork = a.id_artwork) AS nb_offres,
        (SELECT COUNT(*) FROM negotiation n WHERE n.id_artwork = a.id_artwork AND n.statut = 'en_cours') AS nb_negociations
    FROM artwork a
    WHERE a.id_user = ?
    ORDER BY a.date_creation DESC
");
$requete->execute([getCurrentUserId()]);

echo json_encode([
    'success'  => true,
    'artworks' => $requete->fetchAll(),
]);
