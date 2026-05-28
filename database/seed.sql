-- =====================================================================
-- Mercato Nova - Donnees de demonstration
-- A executer apres schema.sql
-- Mots de passe : tous les comptes de demo utilisent "Test1234!"
-- Hash bcrypt genere une fois : $2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS
-- (a regenerer en local pour la prod via password_hash())
-- =====================================================================
USE mercato_nova;

-- Categories
INSERT INTO category (slug, label) VALUES
  ('peinture',   'Peinture'),
  ('sculpture',  'Sculpture'),
  ('photo',      'Photographie'),
  ('numerique',  'Art numerique'),
  ('dessin',     'Dessin');

-- Utilisateurs (mot de passe = Test1234!)
INSERT INTO user (email, password_hash, role, display_name, bio, email_verified) VALUES
  ('admin@mercato.nova',  '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'admin',    'Admin Mercato',  'Equipe de moderation.', 1),
  ('marie@mercato.nova',  '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'vendeur',  'Marie Durand',   'Peintre figurative basee a Lyon.', 1),
  ('kana@mercato.nova',   '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'vendeur',  'Kana Leblanc',   'Photographe documentaire.', 1),
  ('lucas@mercato.nova',  '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'vendeur',  'Lucas Bernard',  'Sculpteur sur bois.', 1),
  ('galerie@mercato.nova','$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'galerie',  'Galerie Nova',   'Galerie partenaire Paris 11e.', 1),
  ('alice@mercato.nova',  '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'acheteur', 'Alice Martin',   NULL, 1),
  ('bob@mercato.nova',    '$2y$10$wH7sIb5gKf7w6CXf9P0Yiu2c1Wjz3iH8Bf3Nm7tQs6gqK3K1m8bDS', 'acheteur', 'Bob Petit',      NULL, 1);

-- Galerie -> artistes rattaches
INSERT INTO gallery_artist (gallery_id, artist_id) VALUES
  (5, 2),
  (5, 3);

-- Oeuvres
INSERT INTO artwork
  (seller_id, category_id, title, description, technique, style, dimensions, year_created,
   sale_mode, price, start_price, min_increment, ends_at, status)
VALUES
  (2, 1, 'Composition en Or et Nuit', 'Huile sur toile, oeuvre signee.',
   'Huile sur toile', 'Abstrait', '100 x 80 cm', 2023,
   'immediat', 1200.00, NULL, NULL, NULL, 'active'),

  (3, 3, 'Lumiere du Nord', 'Tirage argentique, edition limitee 5/10.',
   'Argentique', 'Documentaire', '60 x 40 cm', 2022,
   'enchere', NULL, 350.00, 20.00, DATE_ADD(NOW(), INTERVAL 3 DAY), 'active'),

  (4, 2, 'Racines', 'Sculpture en chene massif, piece unique.',
   'Bois sculpte', 'Organique', '45 x 30 x 25 cm', 2024,
   'negociation', 2200.00, NULL, NULL, NULL, 'active'),

  (2, 1, 'Aurore Mediterraneenne', 'Acrylique sur toile.',
   'Acrylique', 'Figuratif', '70 x 50 cm', 2024,
   'immediat', 680.00, NULL, NULL, NULL, 'en_attente');

-- Quelques offres d'enchere de demo (pour l'oeuvre 2)
INSERT INTO bid (artwork_id, bidder_id, amount, is_winning) VALUES
  (2, 6, 350.00, 0),
  (2, 7, 380.00, 0),
  (2, 6, 420.00, 1);
