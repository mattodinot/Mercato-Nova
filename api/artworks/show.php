<?php
// ============================================================
//  MERCATO NOVA — Détail d'une œuvre
//  Fichier : api/artworks/show.php
//  Méthode : GET
//  Paramètre URL : ?id=3
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';

$pdo = getDB();

// ── 1. Récupération et validation de l'ID ────────────────────
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID d\'œuvre invalide.']);
    exit;
}

// ── 2. Récupération de l'œuvre avec les infos de l'artiste ───
$requete = $pdo->prepare("
    SELECT
        a.*,
        u.nom,
        u.prenom,
        u.bio AS artiste_bio,
        u.avatar AS artiste_avatar,
        COALESCE(
            (SELECT MAX(b.montant) FROM bid b WHERE b.id_artwork = a.id_artwork),
            a.prix_base
        ) AS prix_actuel,
        (SELECT COUNT(*) FROM bid b WHERE b.id_artwork = a.id_artwork) AS nb_offres
    FROM artwork a
    JOIN user u ON a.id_user = u.id_user
    WHERE a.id_artwork = ?
    LIMIT 1
");
$requete->execute([$id]);
$oeuvre = $requete->fetch();

// ── 3. Œuvre introuvable ──────────────────────────────────────
if (!$oeuvre) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Œuvre introuvable.']);
    exit;
}

// ── 4. Incrémenter le compteur de vues ───────────────────────
$pdo->prepare('UPDATE artwork SET vues = vues + 1 WHERE id_artwork = ?')
    ->execute([$id]);

// ── 5. Réponse ────────────────────────────────────────────────
echo json_encode([
    'success' => true,
    'artwork' => $oeuvre,
]);
