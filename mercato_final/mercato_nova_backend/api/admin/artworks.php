<?php
// ============================================================
//  MERCATO NOVA — Administration des annonces
//  Fichier : api/admin/artworks.php
//  Méthode : GET  → liste les annonces selon un statut
//            POST → valider ou rejeter une annonce
//  Accès   : admin uniquement
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';
require_once '../../config/session.php';

// Seul l'admin a accès à ce fichier
requireLogin();
requireRole('admin');

$pdo = getDB();

// ────────────────────────────────────────────────────────────
//  GET : Liste des annonces à modérer
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $statut = $_GET['statut'] ?? 'en_attente';

    $requete = $pdo->prepare("
        SELECT
            a.id_artwork,
            a.titre,
            a.description,
            a.technique,
            a.prix_base,
            a.type_vente,
            a.statut,
            a.date_creation,
            u.nom,
            u.prenom,
            u.email
        FROM artwork a
        JOIN user u ON a.id_user = u.id_user
        WHERE a.statut = ?
        ORDER BY a.date_creation ASC
    ");
    $requete->execute([$statut]);

    echo json_encode([
        'success'  => true,
        'artworks' => $requete->fetchAll(),
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
//  POST : Valider ou rejeter une annonce
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data      = json_decode(file_get_contents('php://input'), true);
    $idArtwork = (int)   ($data['id_artwork'] ?? 0);
    $action    = trim(    $data['action']     ?? '');

    if ($idArtwork <= 0 || !in_array($action, ['valider', 'rejeter'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides.']);
        exit;
    }

    // Récupération de l'annonce et du vendeur
    $reqOeuvre = $pdo->prepare("
        SELECT a.id_user, a.titre FROM artwork a WHERE a.id_artwork = ?
    ");
    $reqOeuvre->execute([$idArtwork]);
    $oeuvre = $reqOeuvre->fetch();

    if (!$oeuvre) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Annonce introuvable.']);
        exit;
    }

    // Nouveau statut selon l'action
    $nouveauStatut = ($action === 'valider') ? 'active' : 'rejetee';

    $pdo->beginTransaction();

    try {
        // Mise à jour du statut de l'annonce
        $pdo->prepare("UPDATE artwork SET statut = ? WHERE id_artwork = ?")
            ->execute([$nouveauStatut, $idArtwork]);

        // Notification au vendeur
        $messageNotif = ($action === 'valider')
            ? "Votre annonce \"{$oeuvre['titre']}\" a été validée et est maintenant publiée."
            : "Votre annonce \"{$oeuvre['titre']}\" a été rejetée par un administrateur.";

        $pdo->prepare("
            INSERT INTO notification (id_user, type, message)
            VALUES (?, 'annonce_validee', ?)
        ")->execute([$oeuvre['id_user'], $messageNotif]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $action === 'valider'
                ? 'Annonce validée et publiée avec succès.'
                : 'Annonce rejetée.',
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'opération.']);
    }
}
