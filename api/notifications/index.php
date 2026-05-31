<?php
// ============================================================
//  MERCATO NOVA — Liste des notifications
//  Fichier : api/notifications/index.php
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

// Récupère les 50 dernières notifications de l'utilisateur
$requete = $pdo->prepare("
    SELECT id_notif, type, message, lu, date_creation
    FROM notification
    WHERE id_user = ?
    ORDER BY date_creation DESC
    LIMIT 50
");
$requete->execute([$userId]);
$notifications = $requete->fetchAll();

// Compte des notifications non lues (pour le badge dans la navbar)
$reqNonLues = $pdo->prepare("SELECT COUNT(*) FROM notification WHERE id_user = ? AND lu = 0");
$reqNonLues->execute([$userId]);
$nbNonLues  = (int) $reqNonLues->fetchColumn();

echo json_encode([
    'success'       => true,
    'notifications' => $notifications,
    'nb_non_lues'   => $nbNonLues,
]);
