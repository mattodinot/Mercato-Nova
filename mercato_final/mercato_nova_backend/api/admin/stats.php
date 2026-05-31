<?php
// ============================================================
//  MERCATO NOVA — Statistiques globales
//  Fichier : api/admin/stats.php
//  Méthode : GET
//  Accès   : admin uniquement
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';
require_once '../../config/session.php';

requireLogin();
requireRole('admin');

$pdo = getDB();

// Toutes les stats en quelques requêtes simples
$stats = [];

// Nombre total d'utilisateurs
$stats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();

// Œuvres actives
$stats['artworks_actives'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM artwork WHERE statut = 'active'"
)->fetchColumn();

// Annonces en attente de validation
$stats['en_attente'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM artwork WHERE statut = 'en_attente'"
)->fetchColumn();

// Nombre de commandes
$stats['orders'] = (int) $pdo->query("SELECT COUNT(*) FROM `order`")->fetchColumn();

// Enchères en cours
$stats['encheres_actives'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM artwork WHERE type_vente = 'enchere' AND statut = 'active'"
)->fetchColumn();

// Négociations en cours
$stats['negociations_actives'] = (int) $pdo->query(
    "SELECT COUNT(*) FROM negotiation WHERE statut = 'en_cours'"
)->fetchColumn();

echo json_encode([
    'success' => true,
    'stats'   => $stats,
]);
