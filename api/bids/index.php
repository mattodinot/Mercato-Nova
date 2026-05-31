<?php
// ============================================================
//  MERCATO NOVA — Historique des offres d'une enchère
//  Fichier : api/bids/index.php
//  Méthode : GET
//  Paramètre URL : ?id_artwork=4
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';

$pdo       = getDB();
$idArtwork = (int) ($_GET['id_artwork'] ?? 0);

if ($idArtwork <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID d\'œuvre invalide.']);
    exit;
}

// Récupération de toutes les offres, de la plus haute à la plus basse
// On affiche "Enchérisseur #X" plutôt que le vrai nom pour préserver la vie privée
$requete = $pdo->prepare("
    SELECT
        b.id_bid,
        b.id_artwork,
        b.id_user,
        b.montant,
        b.date_offre,
        b.est_gagnante,
        CONCAT('Enchérisseur #', b.id_user) AS pseudo
    FROM bid b
    WHERE b.id_artwork = ?
    ORDER BY b.montant DESC, b.date_offre ASC
");
$requete->execute([$idArtwork]);

echo json_encode([
    'success' => true,
    'bids'    => $requete->fetchAll(),
]);
