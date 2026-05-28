-- =====================================================================
-- Mercato Nova - Schema de base de donnees
-- Marketplace d'art : achat immediat, encheres, negociation
-- SGBD : MySQL 8+ (moteur InnoDB pour FK et transactions)
-- =====================================================================

DROP DATABASE IF EXISTS mercato_nova;
CREATE DATABASE mercato_nova
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mercato_nova;

-- ---------------------------------------------------------------------
-- USER : comptes (acheteur, vendeur/artiste, galerie, admin)
-- ---------------------------------------------------------------------
CREATE TABLE user (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  role            ENUM('acheteur','vendeur','galerie','admin') NOT NULL DEFAULT 'acheteur',
  display_name    VARCHAR(120) NOT NULL,
  bio             TEXT NULL,
  avatar_url      VARCHAR(500) NULL,
  status          ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  email_verified  TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_role (role),
  INDEX idx_user_status (status)
) ENGINE=InnoDB;

-- Lien galerie -> artistes rattaches (auto-reference)
CREATE TABLE gallery_artist (
  gallery_id  INT UNSIGNED NOT NULL,
  artist_id   INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (gallery_id, artist_id),
  FOREIGN KEY (gallery_id) REFERENCES user(id) ON DELETE CASCADE,
  FOREIGN KEY (artist_id)  REFERENCES user(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CATEGORY : taxonomie d'oeuvres (peinture, sculpture, photo, ...)
-- ---------------------------------------------------------------------
CREATE TABLE category (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug  VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ARTWORK : annonce / oeuvre publiee
-- ---------------------------------------------------------------------
CREATE TABLE artwork (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id       INT UNSIGNED NOT NULL,
  category_id     INT UNSIGNED NULL,
  title           VARCHAR(200) NOT NULL,
  description     TEXT NULL,
  technique       VARCHAR(120) NULL,
  style           VARCHAR(120) NULL,
  dimensions      VARCHAR(120) NULL,
  year_created    SMALLINT UNSIGNED NULL,
  condition_state ENUM('neuf','tres_bon','bon','use') NOT NULL DEFAULT 'tres_bon',
  sale_mode       ENUM('immediat','enchere','negociation') NOT NULL,
  price           DECIMAL(10,2) NULL,
  -- Champs enchere
  start_price     DECIMAL(10,2) NULL,
  min_increment   DECIMAL(10,2) NULL,
  starts_at       DATETIME NULL,
  ends_at         DATETIME NULL,
  -- Etat metier
  status          ENUM('brouillon','en_attente','active','vendue','rejetee','retiree')
                  NOT NULL DEFAULT 'en_attente',
  views_count     INT UNSIGNED NOT NULL DEFAULT 0,
  favorites_count INT UNSIGNED NOT NULL DEFAULT 0,
  rejected_reason VARCHAR(255) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (seller_id)   REFERENCES user(id)     ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES category(id) ON DELETE SET NULL,
  INDEX idx_artwork_status     (status),
  INDEX idx_artwork_sale_mode  (sale_mode),
  INDEX idx_artwork_seller     (seller_id),
  INDEX idx_artwork_ends_at    (ends_at),
  FULLTEXT INDEX ft_artwork (title, description)
) ENGINE=InnoDB;

-- ARTWORK_IMAGE : plusieurs visuels par oeuvre
CREATE TABLE artwork_image (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artwork_id   INT UNSIGNED NOT NULL,
  url          VARCHAR(500) NOT NULL,
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_cover     TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE,
  INDEX idx_image_artwork (artwork_id)
) ENGINE=InnoDB;

-- FAVORITE : liste de favoris d'un utilisateur
CREATE TABLE favorite (
  user_id     INT UNSIGNED NOT NULL,
  artwork_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, artwork_id),
  FOREIGN KEY (user_id)    REFERENCES user(id)    ON DELETE CASCADE,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- BID : offres d'enchere (historique complet conserve)
-- ---------------------------------------------------------------------
CREATE TABLE bid (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artwork_id  INT UNSIGNED NOT NULL,
  bidder_id   INT UNSIGNED NOT NULL,
  amount      DECIMAL(10,2) NOT NULL,
  placed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_winning  TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE,
  FOREIGN KEY (bidder_id)  REFERENCES user(id)    ON DELETE CASCADE,
  INDEX idx_bid_artwork (artwork_id, amount DESC),
  INDEX idx_bid_bidder  (bidder_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NEGOTIATION : negociation acheteur <-> vendeur
-- Etats : en_cours -> accepte | refuse | expire | abandonne
-- Une seule negociation active par couple (artwork, buyer)
-- ---------------------------------------------------------------------
CREATE TABLE negotiation (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artwork_id  INT UNSIGNED NOT NULL,
  buyer_id    INT UNSIGNED NOT NULL,
  seller_id   INT UNSIGNED NOT NULL,
  state       ENUM('en_cours','accepte','refuse','expire','abandonne')
              NOT NULL DEFAULT 'en_cours',
  final_price DECIMAL(10,2) NULL,
  exchanges   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  expires_at  DATETIME NOT NULL,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id)   REFERENCES user(id)    ON DELETE CASCADE,
  FOREIGN KEY (seller_id)  REFERENCES user(id)    ON DELETE CASCADE,
  INDEX idx_nego_artwork (artwork_id),
  INDEX idx_nego_state   (state)
) ENGINE=InnoDB;

-- NEGO_MESSAGE : echanges d'une negociation
CREATE TABLE nego_message (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negotiation_id  INT UNSIGNED NOT NULL,
  author_id       INT UNSIGNED NOT NULL,
  message_type    ENUM('offre','message','accept','refus','retrait') NOT NULL,
  amount          DECIMAL(10,2) NULL,
  body            TEXT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (negotiation_id) REFERENCES negotiation(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id)      REFERENCES user(id)        ON DELETE CASCADE,
  INDEX idx_nmsg_negotiation (negotiation_id, created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- ORDER : commande issue d'un achat (immediat / enchere / negociation)
-- "order" est un mot reserve : table nommee "art_order"
-- ---------------------------------------------------------------------
CREATE TABLE art_order (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artwork_id    INT UNSIGNED NOT NULL UNIQUE,
  buyer_id      INT UNSIGNED NOT NULL,
  seller_id     INT UNSIGNED NOT NULL,
  origin        ENUM('immediat','enchere','negociation') NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  status        ENUM('en_attente','confirmee','annulee') NOT NULL DEFAULT 'en_attente',
  payment_ref   VARCHAR(120) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirmed_at  DATETIME NULL,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE,
  FOREIGN KEY (buyer_id)   REFERENCES user(id)    ON DELETE CASCADE,
  FOREIGN KEY (seller_id)  REFERENCES user(id)    ON DELETE CASCADE,
  INDEX idx_order_buyer  (buyer_id),
  INDEX idx_order_seller (seller_id),
  INDEX idx_order_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- CART_ITEM : panier persistant en BDD (achat immediat uniquement)
-- Couple unique (user, artwork) pour eviter les doublons
-- ---------------------------------------------------------------------
CREATE TABLE cart_item (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  artwork_id  INT UNSIGNED NOT NULL,
  added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_user_artwork (user_id, artwork_id),
  FOREIGN KEY (user_id)    REFERENCES user(id)    ON DELETE CASCADE,
  FOREIGN KEY (artwork_id) REFERENCES artwork(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- NOTIFICATION : notifications par utilisateur
-- ---------------------------------------------------------------------
CREATE TABLE notification (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(60) NOT NULL,
  title       VARCHAR(180) NOT NULL,
  body        TEXT NULL,
  link_url    VARCHAR(500) NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
  INDEX idx_notif_user_unread (user_id, is_read)
) ENGINE=InnoDB;
