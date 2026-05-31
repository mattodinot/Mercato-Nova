<?php
// ============================================================
//  MERCATO NOVA — Liste des œuvres
//  Fichier : api/artworks/index.php
//  Méthode : GET
//  Paramètres URL optionnels :
//    ?type=immediat|enchere|negociation
//    ?statut=active|en_attente|vendue
//    ?categorie=peinture|sculpture|...
//    ?prix_max=1000
//    ?q=recherche texte
//    ?tri=recent|prix_asc|prix_desc
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';

$pdo = getDB();

// ── 1. Lecture des filtres depuis l'URL ───────────────────────
$type     = $_GET['type']     ?? null;
$statut   = $_GET['statut']   ?? 'active'; // Par défaut : uniquement les annonces actives
$prix_max = $_GET['prix_max'] ?? null;
$q        = $_GET['q']        ?? null;     // Recherche texte
$tri      = $_GET['tri']      ?? 'recent'; // Tri par défaut : plus récentes

// ── 2. Construction de la requête SQL avec filtres dynamiques ─
// On utilise un tableau de conditions qu'on assemblera avec AND
$conditions = ['a.statut = ?'];
$valeurs    = [$statut];

// Filtre par type de vente
if ($type) {
    $conditions[] = 'a.type_vente = ?';
    $valeurs[]    = $type;
}

// Filtre par prix maximum
if ($prix_max) {
    $conditions[] = 'a.prix_base <= ?';
    $valeurs[]    = (float) $prix_max;
}

// Recherche texte : dans le titre, la technique ou le nom de l'artiste
if ($q) {
    $conditions[] = '(a.titre LIKE ? OR a.technique LIKE ? OR u.nom LIKE ? OR u.prenom LIKE ?)';
    $terme        = '%' . $q . '%';
    $valeurs[]    = $terme;
    $valeurs[]    = $terme;
    $valeurs[]    = $terme;
    $valeurs[]    = $terme;
}

// Assemblage de la clause WHERE
$clauseWhere = 'WHERE ' . implode(' AND ', $conditions);

// ── 3. Clause ORDER BY selon le tri demandé ───────────────────
$orderBy = match($tri) {
    'prix_asc'  => 'ORDER BY a.prix_base ASC',
    'prix_desc' => 'ORDER BY a.prix_base DESC',
    'recent'    => 'ORDER BY a.date_creation DESC',
    default     => 'ORDER BY a.date_creation DESC',
};

// ── 4. Requête principale ─────────────────────────────────────
// On joint la table user pour récupérer le nom de l'artiste
// On calcule le nombre d'offres d'enchère avec une sous-requête
$sql = "
    SELECT
        a.id_artwork,
        a.titre,
        a.description,
        a.technique,
        a.dimensions,
        a.annee,
        a.etat,
        a.prix_base,
        a.type_vente,
        a.statut,
        a.surenchere_min,
        a.date_fin_enchere,
        a.livraison,
        a.vues,
        a.date_creation,
        u.id_user,
        u.nom,
        u.prenom,
        -- Prix actuel pour les enchères = meilleure offre ou prix de base
        COALESCE(
            (SELECT MAX(b.montant) FROM bid b WHERE b.id_artwork = a.id_artwork),
            a.prix_base
        ) AS prix_actuel,
        -- Nombre d'offres pour les enchères
        (SELECT COUNT(*) FROM bid b WHERE b.id_artwork = a.id_artwork) AS nb_offres
    FROM artwork a
    JOIN user u ON a.id_user = u.id_user
    $clauseWhere
    $orderBy
";

$requete = $pdo->prepare($sql);
$requete->execute($valeurs);
$oeuvres = $requete->fetchAll();

// ── 5. Incrémentation du compteur de vues ─────────────────────
// (uniquement si on récupère une seule œuvre par recherche)

// ── 6. Réponse ────────────────────────────────────────────────
echo json_encode([
    'success'  => true,
    'count'    => count($oeuvres),
    'artworks' => $oeuvres,
]);
