<?php
// ============================================================
//  MERCATO NOVA — Administration des utilisateurs
//  Fichier : api/admin/users.php
//  Méthode : GET  → liste tous les utilisateurs
//            POST → suspendre un compte (changer le rôle)
//  Accès   : admin uniquement
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// ────────────────────────────────────────────────────────────
//  GET : Liste de tous les utilisateurs
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $requete = $pdo->prepare("
        SELECT
            id_user,
            prenom,
            nom,
            email,
            role,
            date_inscription,
            -- Statistiques rapides par utilisateur
            (SELECT COUNT(*) FROM artwork  WHERE id_user    = u.id_user) AS nb_annonces,
            (SELECT COUNT(*) FROM `order`  WHERE id_acheteur= u.id_user) AS nb_commandes,
            (SELECT COUNT(*) FROM bid      WHERE id_user    = u.id_user) AS nb_offres
        FROM user u
        ORDER BY date_inscription DESC
    ");
    $requete->execute();

    echo json_encode([
        'success' => true,
        'users'   => $requete->fetchAll(),
    ]);
    exit;
}

// ────────────────────────────────────────────────────────────
//  POST : Suspendre un compte (ou changer son rôle)
// ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data   = json_decode(file_get_contents('php://input'), true);
    $idUser = (int)  ($data['id_user'] ?? 0);
    $action = trim(   $data['action']  ?? '');

    if ($idUser <= 0 || empty($action)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides.']);
        exit;
    }

    // Un admin ne peut pas se suspendre lui-même
    if ($idUser === getCurrentUserId()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas modifier votre propre compte.']);
        exit;
    }

    // Vérification que l'utilisateur existe et n'est pas admin
    $reqUser = $pdo->prepare('SELECT role FROM user WHERE id_user = ?');
    $reqUser->execute([$idUser]);
    $userCible = $reqUser->fetch();

    if (!$userCible) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable.']);
        exit;
    }

    if ($userCible['role'] === 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Impossible de modifier un compte administrateur.']);
        exit;
    }

    // Actions disponibles
    switch ($action) {
        case 'suspendre':
            // On marque le rôle comme 'suspendu' (à ajouter dans l'ENUM si besoin)
            // Alternative simple : on supprime l'utilisateur
            // Ici on choisit de garder une trace en changeant le rôle
            $pdo->prepare("UPDATE user SET role = 'acheteur' WHERE id_user = ?")
                ->execute([$idUser]);
            echo json_encode(['success' => true, 'message' => 'Compte réinitialisé en acheteur.']);
            break;

        case 'promouvoir_vendeur':
            $pdo->prepare("UPDATE user SET role = 'vendeur' WHERE id_user = ?")
                ->execute([$idUser]);
            echo json_encode(['success' => true, 'message' => 'Compte promu en vendeur.']);
            break;

        case 'supprimer':
            // Suppression complète (CASCADE supprime ses annonces, offres, etc.)
            $pdo->prepare("DELETE FROM user WHERE id_user = ?")
                ->execute([$idUser]);
            echo json_encode(['success' => true, 'message' => 'Compte supprimé.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    }
}
