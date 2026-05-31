<?php
// ============================================================
//  MERCATO NOVA — Marquer les notifications comme lues
//  Fichier : api/notifications/read.php
//  Méthode : POST
//  Accès   : utilisateur connecté
//  Body JSON optionnel : { id_notif } pour une seule notif
//                        (sans body = toutes marquées comme lues)
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();

$pdo    = getDB();
$userId = getCurrentUserId();
$data   = json_decode(file_get_contents('php://input'), true);

// Si un id_notif est précisé : marquer seulement celle-là
// Sinon : marquer toutes les notifications de l'utilisateur
if (!empty($data['id_notif'])) {
    $idNotif = (int) $data['id_notif'];
    $pdo->prepare("
        UPDATE notification SET lu = 1
        WHERE id_notif = ? AND id_user = ?
    ")->execute([$idNotif, $userId]);

    echo json_encode(['success' => true, 'message' => 'Notification marquée comme lue.']);
} else {
    $pdo->prepare("
        UPDATE notification SET lu = 1 WHERE id_user = ?
    ")->execute([$userId]);

    echo json_encode(['success' => true, 'message' => 'Toutes les notifications ont été lues.']);
}
