<?php
// ============================================================
//  MERCATO NOVA — Déconnexion
//  Fichier : api/auth/logout.php
//  Méthode : POST
//  Réponse  : { success, message }
// ============================================================

// ── 1. En-têtes HTTP ─────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── 2. Chargement de la session ───────────────────────────────
require_once '../../config/session.php';

// ── 3. Destruction de la session ─────────────────────────────
// La fonction logoutUser() est définie dans config/session.php
// Elle vide $_SESSION, supprime le cookie de session, et détruit la session
logoutUser();

// ── 4. Réponse de succès ──────────────────────────────────────
echo json_encode([
    'success' => true,
    'message' => 'Déconnexion réussie.',
]);
