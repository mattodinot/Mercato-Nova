<?php
// ============================================================
//  MERCATO NOVA — Créer une annonce
//  Fichier : api/artworks/create.php
//  Méthode : POST
//  Accès   : vendeur, galerie ou admin uniquement
//  Body JSON : { titre, description, technique, dimensions,
//               annee, etat, prix_base, type_vente,
//               surenchere_min, duree_enchere, livraison }
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

// ── 1. Vérification que l'utilisateur est connecté ───────────
requireLogin();

// ── 2. Vérification du rôle (pas les acheteurs simples) ──────
$role = getCurrentUserRole();
if (!in_array($role, ['vendeur', 'galerie', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Seuls les vendeurs peuvent créer des annonces.']);
    exit;
}

// ── 3. Lecture du corps JSON ──────────────────────────────────
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Corps de la requête invalide.']);
    exit;
}

// ── 4. Récupération et nettoyage des champs ───────────────────
$titre         = trim($data['titre']         ?? '');
$description   = trim($data['description']   ?? '');
$technique     = trim($data['technique']     ?? '');
$dimensions    = trim($data['dimensions']    ?? '');
$annee         = !empty($data['annee'])   ? (int)   $data['annee']   : null;
$etat          = trim($data['etat']          ?? 'neuf');
$prix_base     = !empty($data['prix_base'])  ? (float) $data['prix_base']  : 0;
$type_vente    = trim($data['type_vente']    ?? '');
$surenchere    = !empty($data['surenchere_min']) ? (float) $data['surenchere_min'] : null;
$duree_enchere = !empty($data['duree_enchere'])  ? (int)   $data['duree_enchere']  : null;
$livraison     = trim($data['livraison']     ?? 'Livraison gratuite');

// ── 5. Validation ─────────────────────────────────────────────
if (empty($titre)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le titre est obligatoire.']);
    exit;
}

if ($prix_base <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Le prix doit être supérieur à 0.']);
    exit;
}

$typesValides = ['immediat', 'enchere', 'negociation'];
if (!in_array($type_vente, $typesValides)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Type de vente invalide.']);
    exit;
}

$etatsValides = ['neuf', 'tres_bon', 'bon', 'occasion'];
if (!in_array($etat, $etatsValides)) {
    $etat = 'neuf';
}

// Pour les enchères : vérification des champs spécifiques
if ($type_vente === 'enchere') {
    if (!$duree_enchere || !in_array($duree_enchere, [24, 48, 168])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Durée d\'enchère invalide (24, 48 ou 168 heures).']);
        exit;
    }
}

// ── 6. Calcul de la date de fin d'enchère ─────────────────────
$date_fin_enchere = null;
if ($type_vente === 'enchere' && $duree_enchere) {
    // Date actuelle + durée en heures
    $date_fin_enchere = date('Y-m-d H:i:s', strtotime("+{$duree_enchere} hours"));
}

// ── 7. Insertion en base de données ──────────────────────────
// Le statut est 'en_attente' par défaut : l'admin doit valider
$pdo = getDB();

$requete = $pdo->prepare("
    INSERT INTO artwork
        (id_user, titre, description, technique, dimensions, annee,
         etat, prix_base, type_vente, statut, surenchere_min,
         duree_enchere, date_fin_enchere, livraison)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?, ?, ?)
");

$requete->execute([
    getCurrentUserId(),
    $titre,
    $description,
    $technique,
    $dimensions,
    $annee,
    $etat,
    $prix_base,
    $type_vente,
    $surenchere,
    $duree_enchere,
    $date_fin_enchere,
    $livraison,
]);

$idAnnonce = $pdo->lastInsertId();

// ── 8. Réponse ────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'success'    => true,
    'message'    => 'Annonce créée. Elle sera publiée après validation par un administrateur.',
    'id_artwork' => (int) $idAnnonce,
]);
