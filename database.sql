-- ============================================================
--  MERCATO NOVA — Base de données
--  Projet Web Dynamique 2026 — ECE Paris ING2
--  Fichier : database.sql
--  Encodage : UTF-8
-- ============================================================

-- Suppression et recréation de la base
DROP DATABASE IF EXISTS mercato_nova;
CREATE DATABASE mercato_nova
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mercato_nova;

-- Désactivation temporaire des clés étrangères pour l'import
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
--  TABLE : user
-- ============================================================
CREATE TABLE user (
    id_user         INT             NOT NULL AUTO_INCREMENT,
    email           VARCHAR(150)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    nom             VARCHAR(80)     NOT NULL,
    prenom          VARCHAR(80)     NOT NULL,
    role            ENUM('admin','vendeur','acheteur','galerie')
                                    NOT NULL DEFAULT 'acheteur',
    bio             TEXT            NULL,
    avatar          VARCHAR(255)    NULL,
    date_inscription DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_user      PRIMARY KEY (id_user),
    CONSTRAINT uq_user_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : artwork
-- ============================================================
CREATE TABLE artwork (
    id_artwork      INT             NOT NULL AUTO_INCREMENT,
    id_user         INT             NOT NULL,
    titre           VARCHAR(200)    NOT NULL,
    description     TEXT            NULL,
    technique       VARCHAR(100)    NULL,
    dimensions      VARCHAR(50)     NULL,
    annee           YEAR            NULL,
    etat            ENUM('neuf','tres_bon','bon','occasion')
                                    NOT NULL DEFAULT 'neuf',
    prix_base       DECIMAL(10,2)   NOT NULL,
    type_vente      ENUM('immediat','enchere','negociation')
                                    NOT NULL,
    statut          ENUM('en_attente','active','vendue','rejetee')
                                    NOT NULL DEFAULT 'en_attente',
    surenchere_min  DECIMAL(10,2)   NULL,
    duree_enchere   INT             NULL COMMENT 'Durée en heures : 24, 48 ou 168',
    date_fin_enchere DATETIME       NULL,
    livraison       VARCHAR(100)    NULL DEFAULT 'Livraison gratuite',
    vues            INT             NOT NULL DEFAULT 0,
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_artwork   PRIMARY KEY (id_artwork),
    CONSTRAINT fk_artwork_user
        FOREIGN KEY (id_user) REFERENCES user(id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : bid  (offres d'enchère)
-- ============================================================
CREATE TABLE bid (
    id_bid          INT             NOT NULL AUTO_INCREMENT,
    id_artwork      INT             NOT NULL,
    id_user         INT             NOT NULL,
    montant         DECIMAL(10,2)   NOT NULL,
    date_offre      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    est_gagnante    TINYINT(1)      NOT NULL DEFAULT 0,

    CONSTRAINT pk_bid       PRIMARY KEY (id_bid),
    CONSTRAINT fk_bid_artwork
        FOREIGN KEY (id_artwork) REFERENCES artwork(id_artwork)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bid_user
        FOREIGN KEY (id_user) REFERENCES user(id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : negotiation
-- ============================================================
CREATE TABLE negotiation (
    id_nego         INT             NOT NULL AUTO_INCREMENT,
    id_artwork      INT             NOT NULL,
    id_acheteur     INT             NOT NULL,
    statut          ENUM('en_cours','accepte','refuse','expire','abandonne')
                                    NOT NULL DEFAULT 'en_cours',
    nb_echanges     INT             NOT NULL DEFAULT 0,
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_expiration DATETIME        NOT NULL,

    CONSTRAINT pk_negotiation   PRIMARY KEY (id_nego),
    CONSTRAINT fk_nego_artwork
        FOREIGN KEY (id_artwork) REFERENCES artwork(id_artwork)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_nego_acheteur
        FOREIGN KEY (id_acheteur) REFERENCES user(id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : nego_message  (messages dans une négociation)
-- ============================================================
CREATE TABLE nego_message (
    id_msg          INT             NOT NULL AUTO_INCREMENT,
    id_nego         INT             NOT NULL,
    montant_propose DECIMAL(10,2)   NOT NULL,
    contenu         TEXT            NULL,
    emetteur        ENUM('vendeur','acheteur')
                                    NOT NULL,
    date_envoi      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_nego_message  PRIMARY KEY (id_msg),
    CONSTRAINT fk_msg_nego
        FOREIGN KEY (id_nego) REFERENCES negotiation(id_nego)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : `order`  (commandes finalisées)
-- ============================================================
CREATE TABLE `order` (
    id_order        INT             NOT NULL AUTO_INCREMENT,
    id_artwork      INT             NOT NULL,
    id_acheteur     INT             NOT NULL,
    montant_final   DECIMAL(10,2)   NOT NULL,
    mode_achat      ENUM('immediat','enchere','negociation')
                                    NOT NULL,
    statut          ENUM('en_attente','confirmee','annulee')
                                    NOT NULL DEFAULT 'en_attente',
    date_achat      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_order     PRIMARY KEY (id_order),
    CONSTRAINT fk_order_artwork
        FOREIGN KEY (id_artwork) REFERENCES artwork(id_artwork)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_order_acheteur
        FOREIGN KEY (id_acheteur) REFERENCES user(id_user)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLE : notification
-- ============================================================
CREATE TABLE notification (
    id_notif        INT             NOT NULL AUTO_INCREMENT,
    id_user         INT             NOT NULL,
    type            VARCHAR(50)     NOT NULL
                    COMMENT 'ex: surenchere, nego_recue, commande_confirmee, annonce_validee',
    message         TEXT            NOT NULL,
    lu              TINYINT(1)      NOT NULL DEFAULT 0,
    date_creation   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT pk_notification  PRIMARY KEY (id_notif),
    CONSTRAINT fk_notif_user
        FOREIGN KEY (id_user) REFERENCES user(id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  INDEX  (amélioration des performances)
-- ============================================================
CREATE INDEX idx_artwork_user       ON artwork(id_user);
CREATE INDEX idx_artwork_type       ON artwork(type_vente);
CREATE INDEX idx_artwork_statut     ON artwork(statut);
CREATE INDEX idx_artwork_fin        ON artwork(date_fin_enchere);
CREATE INDEX idx_bid_artwork        ON bid(id_artwork);
CREATE INDEX idx_bid_montant        ON bid(montant DESC);
CREATE INDEX idx_nego_artwork       ON negotiation(id_artwork);
CREATE INDEX idx_nego_statut        ON negotiation(statut);
CREATE INDEX idx_notif_user_lu      ON notification(id_user, lu);


-- ============================================================
--  RÉACTIVATION des clés étrangères
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
--  DONNÉES DE TEST
-- ============================================================

-- ------------------------------------------------------------
--  Utilisateurs (mots de passe tous = "password123")
--  Hash généré avec password_hash('password123', PASSWORD_BCRYPT)
-- ------------------------------------------------------------
INSERT INTO user (email, password_hash, nom, prenom, role, bio) VALUES

-- Admin
('admin@mercatonova.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Nova', 'Admin',
 'admin',
 'Administrateur de la plateforme Mercato Nova.'),

-- Vendeurs / Artistes
('marie.durand@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Durand', 'Marie',
 'vendeur',
 'Peintre plasticienne spécialisée dans l\'abstrait. Basée à Lyon, expose régulièrement dans des galeries parisiennes.'),

('thomas.renard@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Renard', 'Thomas',
 'vendeur',
 'Sculpteur travaillant le bronze et le marbre. Atelier à Bordeaux.'),

('kana.leblanc@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Leblanc', 'Kana',
 'vendeur',
 'Photographe documentaire et plasticienne. Séries urbaines et paysagères.'),

-- Acheteurs
('sophie.martin@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Martin', 'Sophie',
 'acheteur',
 NULL),

('lucas.bernard@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Bernard', 'Lucas',
 'acheteur',
 NULL),

('jean.dupont@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Dupont', 'Jean',
 'acheteur',
 NULL),

('amira.khalil@email.fr',
 '$2y$10$lsXKo24T1oEdk2QTEFkbaeM7zNA18NaaM06Rc3jFHBWhanL0eM.w6',
 'Khalil', 'Amira',
 'acheteur',
 NULL);


-- ------------------------------------------------------------
--  Œuvres
-- ------------------------------------------------------------
INSERT INTO artwork
    (id_user, titre, description, technique, dimensions, annee, etat,
     prix_base, type_vente, statut, surenchere_min, duree_enchere,
     date_fin_enchere, livraison, vues)
VALUES

-- Achat immédiat (Marie Durand = id 2)
(2,
 'Composition en Or et Nuit',
 'Une exploration des contrastes entre la lumière dorée et l\'obscurité veloutée. L\'artiste joue avec les épaisseurs de matière pour créer une œuvre tactile et lumineuse.',
 'Huile sur toile', '100 × 80 cm', 2023, 'neuf',
 1200.00, 'immediat', 'active',
 NULL, NULL, NULL, 'Livraison gratuite', 234),

(2,
 'Aquarelle du Crépuscule',
 'Paysage de coucher de soleil réalisé en plein air sur les rives de la Loire. Papier Arches 300g, encadrée sous passe-partout blanc.',
 'Aquarelle', '50 × 70 cm', 2024, 'neuf',
 680.00, 'immediat', 'active',
 NULL, NULL, NULL, 'Livraison gratuite', 98),

(2,
 'Paysage Intérieur III',
 'Pastel sec sur papier velours, représentant un espace domestique baigné de lumière naturelle. Sous verre antireflet, cadre bois naturel.',
 'Pastel', '65 × 50 cm', 2023, 'neuf',
 520.00, 'immediat', 'active',
 NULL, NULL, NULL, 'Livraison gratuite', 112),

-- Enchères (Thomas Renard = id 3)
(3,
 'L\'Éveil de la Forme',
 'Sculpture en bronze à la cire perdue, numérotée 3/8. Représente le mouvement d\'un corps en train de se libérer de ses contraintes. Socle en chêne massif inclus.',
 'Bronze', '45 × 30 × 25 cm', 2022, 'neuf',
 2500.00, 'enchere', 'active',
 50.00, 48,
 DATE_ADD(NOW(), INTERVAL 2 HOUR),
 'Expédition sécurisée', 512),

(3,
 'Éclat de Marbre #2',
 'Sculpture en marbre de Carrare travaillée à la main. Pièce unique explorant la tension entre la rigueur géométrique et la fluidité organique. Socle inclus.',
 'Marbre', '35 × 20 × 15 cm', 2022, 'neuf',
 3000.00, 'enchere', 'active',
 100.00, 168,
 DATE_ADD(NOW(), INTERVAL 6 HOUR),
 'Expédition sécurisée', 445),

(3,
 'Mémoire Bleue',
 'Grande toile acrylique aux textures généreuses, évoquant la profondeur de l\'océan. Œuvre unique accompagnée d\'un certificat d\'authenticité signé.',
 'Acrylique', '120 × 90 cm', 2021, 'bon',
 1500.00, 'enchere', 'active',
 50.00, 24,
 DATE_ADD(NOW(), INTERVAL 23 HOUR),
 'Expédition sécurisée', 389),

-- Négociation (Kana Leblanc = id 4)
(4,
 'Sérénité Urbaine #7',
 'Série capturant les instants de calme dans le chaos des grandes villes. Tirage sur papier Fine Art 300g, encadrée sous verre antireflet.',
 'Photographie numérique', '60 × 90 cm', 2024, 'neuf',
 450.00, 'negociation', 'active',
 NULL, NULL, NULL, 'Expédition rapide', 187),

(4,
 'Lumière du Nord',
 'Photographie argentique noir et blanc de paysages scandinaves. Tirage baryté développé en laboratoire traditionnel. Numérotée 2/5, signée au dos.',
 'Photographie argentique', '50 × 75 cm', 2023, 'neuf',
 750.00, 'negociation', 'active',
 NULL, NULL, NULL, 'Expédition rapide', 168),

(4,
 'Portrait de l\'Absence',
 'Dessin à l\'encre de Chine sur papier Canson 200g. Portrait introspectif aux traits nerveux et précis. Non encadré, livré dans un tube de protection.',
 'Encre de Chine', '30 × 42 cm', 2023, 'tres_bon',
 280.00, 'negociation', 'active',
 NULL, NULL, NULL, 'Expédition rapide', 76);


-- ------------------------------------------------------------
--  Offres d'enchères  (sur L'Éveil de la Forme = id 4)
-- ------------------------------------------------------------
INSERT INTO bid (id_artwork, id_user, montant, date_offre, est_gagnante) VALUES
(4, 5, 2600.00, DATE_SUB(NOW(), INTERVAL 5 HOUR),  0),
(4, 6, 2700.00, DATE_SUB(NOW(), INTERVAL 4 HOUR),  0),
(4, 7, 2850.00, DATE_SUB(NOW(), INTERVAL 3 HOUR),  0),
(4, 5, 2950.00, DATE_SUB(NOW(), INTERVAL 2 HOUR),  0),
(4, 6, 3050.00, DATE_SUB(NOW(), INTERVAL 1 HOUR),  0),
(4, 7, 3100.00, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1);

-- Offres sur Éclat de Marbre #2 = id 5
INSERT INTO bid (id_artwork, id_user, montant, date_offre, est_gagnante) VALUES
(5, 5, 3150.00, DATE_SUB(NOW(), INTERVAL 3 HOUR),  0),
(5, 8, 3300.00, DATE_SUB(NOW(), INTERVAL 2 HOUR),  0),
(5, 6, 3500.00, DATE_SUB(NOW(), INTERVAL 1 HOUR),  0),
(5, 5, 3650.00, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 0),
(5, 8, 3800.00, DATE_SUB(NOW(), INTERVAL 10 MINUTE), 1);

-- Offres sur Mémoire Bleue = id 6
INSERT INTO bid (id_artwork, id_user, montant, date_offre, est_gagnante) VALUES
(6, 7, 1600.00, DATE_SUB(NOW(), INTERVAL 5 HOUR),  0),
(6, 8, 1750.00, DATE_SUB(NOW(), INTERVAL 3 HOUR),  0),
(6, 5, 1900.00, DATE_SUB(NOW(), INTERVAL 1 HOUR),  1);


-- ------------------------------------------------------------
--  Négociations
-- ------------------------------------------------------------
-- Négociation 1 : Sophie (id 5) négocie Sérénité Urbaine #7 (id 7)
INSERT INTO negotiation
    (id_artwork, id_acheteur, statut, nb_echanges, date_expiration)
VALUES
(7, 5, 'en_cours', 2, DATE_ADD(NOW(), INTERVAL 46 HOUR));

-- Messages de la négociation 1 (id_nego = 1)
INSERT INTO nego_message (id_nego, montant_propose, contenu, emetteur) VALUES
(1, 450.00, 'Bonjour, je suis intéressée par cette œuvre. Le prix est-il négociable ?', 'acheteur'),
(1, 420.00, 'Je propose 420 €, livraison incluse.', 'vendeur');

-- Négociation 2 : Lucas (id 6) négocie Portrait de l'Absence (id 9)
INSERT INTO negotiation
    (id_artwork, id_acheteur, statut, nb_echanges, date_expiration)
VALUES
(9, 6, 'en_cours', 4, DATE_ADD(NOW(), INTERVAL 10 HOUR));

-- Messages de la négociation 2 (id_nego = 2)
INSERT INTO nego_message (id_nego, montant_propose, contenu, emetteur) VALUES
(2, 280.00, 'Je suis très intéressé par ce dessin.', 'acheteur'),
(2, 250.00, 'Ma contre-offre : 250 €.', 'vendeur'),
(2, 260.00, 'Je monte à 260 €, c\'est mon maximum.', 'acheteur'),
(2, 265.00, 'Je descends à 265 €, c\'est mon dernier prix.', 'vendeur');


-- ------------------------------------------------------------
--  Commandes
-- ------------------------------------------------------------
-- Sophie (id 5) a acheté Paysage Intérieur III (id 3) en achat immédiat
INSERT INTO `order` (id_artwork, id_acheteur, montant_final, mode_achat, statut)
VALUES (3, 5, 520.00, 'immediat', 'confirmee');

-- Mise à jour du statut de l'œuvre achetée
UPDATE artwork SET statut = 'vendue' WHERE id_artwork = 3;


-- ------------------------------------------------------------
--  Notifications
-- ------------------------------------------------------------
INSERT INTO notification (id_user, type, message, lu) VALUES

-- Notifications pour Marie Durand (id 2, vendeur)
(2, 'annonce_validee',
 'Votre annonce "Composition en Or et Nuit" a été validée et est maintenant active.',
 1),
(2, 'commande_confirmee',
 'Votre œuvre "Paysage Intérieur III" a été achetée pour 520 €. Une commande a été créée.',
 0),

-- Notifications pour Thomas Renard (id 3, vendeur)
(3, 'surenchere',
 'Nouvelle enchère sur "L\'Éveil de la Forme" : offre actuelle à 3 100 €.',
 0),
(3, 'surenchere',
 'Nouvelle enchère sur "Éclat de Marbre #2" : offre actuelle à 3 800 €.',
 0),

-- Notifications pour Sophie (id 5, acheteur)
(5, 'commande_confirmee',
 'Votre achat de "Paysage Intérieur III" pour 520 € est confirmé.',
 0),
(5, 'surenchere',
 'Vous avez été surenchéri sur "L\'Éveil de la Forme". Offre actuelle : 3 100 €.',
 0),

-- Notifications pour Lucas (id 6, acheteur)
(6, 'nego_recue',
 'Le vendeur a répondu à votre négociation sur "Portrait de l\'Absence" : 265 €.',
 0),

-- Notifications pour Jean (id 7, acheteur)
(7, 'surenchere',
 'Vous avez été surenchéri sur "Mémoire Bleue". Offre actuelle : 1 900 €.',
 0);


-- ============================================================
--  VÉRIFICATION FINALE
-- ============================================================
SELECT 'Tables créées :' AS '';
SHOW TABLES;

SELECT 'Utilisateurs insérés :' AS '';
SELECT id_user, email, role FROM user;

SELECT 'Oeuvres insérées :' AS '';
SELECT id_artwork, titre, type_vente, statut, prix_base FROM artwork;
