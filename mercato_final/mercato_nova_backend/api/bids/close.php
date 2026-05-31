<?php
// ============================================================
//  MERCATO NOVA — Clôturer les enchères expirées
//  Fichier : api/bids/close.php
//  Méthode : POST
//  Accès   : admin uniquement (ou appel automatique par cron)
//
//  Ce fichier est appelé soit manuellement par un admin,
//  soit automatiquement par un cron job toutes les X minutes.
//  Il cherche toutes les enchères dont la date de fin est dépassée
//  et les clôture en créant une commande pour le gagnant.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../../config/db.php';
require_once '../../config/session.php';

// Pour un appel manuel : vérifier que c'est un admin
// Pour un cron job : commenter les 2 lignes suivantes
requireLogin();
requireRole('admin');

$pdo = getDB();

// ── 1. Récupération des enchères expirées ─────────────────────
$requete = $pdo->prepare("
    SELECT id_artwork, id_user AS id_vendeur, prix_base
    FROM artwork
    WHERE type_vente = 'enchere'
      AND statut = 'active'
      AND date_fin_enchere IS NOT NULL
      AND date_fin_enchere < NOW()
");
$requete->execute();
$encheres = $requete->fetchAll();

$cloturees = 0;

foreach ($encheres as $enchere) {
    // Transaction pour chaque enchère
    $pdo->beginTransaction();

    try {
        $idArtwork = $enchere['id_artwork'];

        // ── 2. Trouver la meilleure offre ─────────────────────
        $reqGagnant = $pdo->prepare("
            SELECT id_user, montant
            FROM bid
            WHERE id_artwork = ?
            ORDER BY montant DESC, date_offre ASC
            LIMIT 1
        ");
        $reqGagnant->execute([$idArtwork]);
        $gagnant = $reqGagnant->fetch();

        if ($gagnant) {
            // ── 3. Créer la commande pour le gagnant ──────────
            $pdo->prepare("
                INSERT INTO `order` (id_artwork, id_acheteur, montant_final, mode_achat, statut)
                VALUES (?, ?, ?, 'enchere', 'confirmee')
            ")->execute([$idArtwork, $gagnant['id_user'], $gagnant['montant']]);

            // ── 4. Marquer l'œuvre comme vendue ───────────────
            $pdo->prepare("
                UPDATE artwork SET statut = 'vendue' WHERE id_artwork = ?
            ")->execute([$idArtwork]);

            // ── 5. Notification au gagnant ────────────────────
            $pdo->prepare("
                INSERT INTO notification (id_user, type, message)
                VALUES (?, 'enchere_gagnee', ?)
            ")->execute([
                $gagnant['id_user'],
                "Félicitations ! Vous avez remporté l'enchère pour {$gagnant['montant']} €.",
            ]);

            // ── 6. Notification au vendeur ────────────────────
            $pdo->prepare("
                INSERT INTO notification (id_user, type, message)
                VALUES (?, 'enchere_terminee', ?)
            ")->execute([
                $enchere['id_vendeur'],
                "Votre enchère s'est terminée. Vendue pour {$gagnant['montant']} €.",
            ]);

            // ── 7. Notifications aux enchérisseurs perdants ───
            $perdants = $pdo->prepare("
                SELECT DISTINCT id_user FROM bid
                WHERE id_artwork = ? AND id_user != ?
            ");
            $perdants->execute([$idArtwork, $gagnant['id_user']]);
            foreach ($perdants->fetchAll() as $perdant) {
                $pdo->prepare("
                    INSERT INTO notification (id_user, type, message)
                    VALUES (?, 'enchere_perdue', ?)
                ")->execute([
                    $perdant['id_user'],
                    "L'enchère à laquelle vous participiez s'est terminée.",
                ]);
            }

        } else {
            // Aucune offre : l'enchère se termine sans vente
            $pdo->prepare("
                UPDATE artwork SET statut = 'active' WHERE id_artwork = ?
            ")->execute([$idArtwork]);
            // On pourrait aussi remettre en 'active' ou marquer 'rejetee'
        }

        $pdo->commit();
        $cloturees++;

    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

echo json_encode([
    'success'   => true,
    'message'   => "{$cloturees} enchère(s) clôturée(s).",
    'cloturees' => $cloturees,
]);
