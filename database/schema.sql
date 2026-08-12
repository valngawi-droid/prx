-- ============================================================
-- ChiperX — Database Schema (MySQL 8.0+ / MariaDB 10.6+)
-- Charset: utf8mb4 | Engine: InnoDB | FK enforced
-- ============================================================
CREATE DATABASE IF NOT EXISTS chiperx
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chiperx;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- TABEL: users  (Autentikasi berbasis Email OTP, tanpa password)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(80)  NOT NULL DEFAULT 'Pengguna ChiperX',
    username         VARCHAR(40)  NULL COMMENT 'Nama pengguna untuk login (unik)',
    password_hash    VARCHAR(255) NULL COMMENT 'bcrypt/argon2 — plaintext tidak pernah disimpan',
    email            VARCHAR(190) NOT NULL,
    role             ENUM('owner','admin','user') NOT NULL DEFAULT 'user',
    status           ENUM('active','banned') NOT NULL DEFAULT 'active',
    is_verified      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Centang biru — diatur Owner',
    badges           VARCHAR(190) NULL COMMENT 'CSV tags kustom profil (maks 5)',
    coin_balance     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    balance          BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Saldo IDR (opsional / fitur masa depan)',
    play_tickets     TINYINT UNSIGNED NOT NULL DEFAULT 3 COMMENT 'Tiket main harian, reset 00:00 WIB',
    tickets_reset_at DATE NULL COMMENT 'Tanggal (WIB) terakhir tiket di-reset',
    avatar           VARCHAR(255) NULL,
    referral_code    VARCHAR(12) NULL COMMENT 'Kode referral unik milik user',
    referred_by      BIGINT UNSIGNED NULL COMMENT 'ID user pengundang (referral)',
    last_daily_claim DATE NULL COMMENT 'Tanggal (WIB) klaim bonus harian terakhir',
    last_login       DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_referral (referral_code),
    KEY idx_users_role (role),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_referred FOREIGN KEY (referred_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: otp_codes  (OTP 6 digit, disimpan sebagai HASH Argon2id)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS otp_codes;
CREATE TABLE otp_codes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email        VARCHAR(190) NOT NULL,
    otp_hash     VARCHAR(255) NOT NULL COMMENT 'password_hash Argon2id, OTP mentah tidak pernah disimpan',
    expires_at   DATETIME NOT NULL COMMENT 'Berlaku 5 menit',
    consumed_at  DATETIME NULL,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Maks 5 percobaan verifikasi',
    ip_address   VARCHAR(45) NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otp_email (email),
    KEY idx_otp_expires (expires_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: links  (Halaman "All Link ChiperX" ala Linktree/Bento)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS links;
CREATE TABLE links (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(120) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    icon_class VARCHAR(80) NOT NULL DEFAULT 'link' COMMENT 'discord|telegram|instagram|github|youtube|web|link',
    color      VARCHAR(20) NOT NULL DEFAULT '#8b5cf6',
    order_num  INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_links_order (is_active, order_num)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: products  (Redeem Center [koin] + Premium Store [IDR])
-- ------------------------------------------------------------
DROP TABLE IF EXISTS products;
CREATE TABLE products (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(160) NOT NULL,
    slug        VARCHAR(190) NOT NULL,
    type        ENUM('coin_redeem','premium_store') NOT NULL,
    price       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Koin untuk coin_redeem, Rupiah untuk premium_store',
    stock       INT NULL DEFAULT NULL COMMENT 'NULL = stok tak terbatas',
    file_url    VARCHAR(500) NOT NULL COMMENT 'URL eksternal ATAU path relatif di storage/uploads',
    description TEXT NULL,
    image       VARCHAR(500) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_type (type, is_active),
    CONSTRAINT fk_products_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: transactions  (Pembelian store / penukaran koin)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS transactions;
CREATE TABLE transactions (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        BIGINT UNSIGNED NOT NULL,
    product_id     BIGINT UNSIGNED NOT NULL,
    amount         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    payment_method VARCHAR(40) NOT NULL DEFAULT 'coin' COMMENT 'coin|qris|ewallet|bank|dll',
    status         ENUM('pending','paid','failed','expired','refunded') NOT NULL DEFAULT 'pending',
    gateway_ref    VARCHAR(120) NULL COMMENT 'Reference ID dari payment gateway',
    checkout_url   VARCHAR(500) NULL,
    qr_url         VARCHAR(500) NULL,
    expired_at     DATETIME NULL,
    paid_at        DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tx_gateway_ref (gateway_ref),
    KEY idx_tx_user (user_id, status),
    KEY idx_tx_status (status),
    CONSTRAINT fk_tx_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tx_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: logs  (Audit trail aplikasi + mirror ke Discord)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS logs;
CREATE TABLE logs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NULL,
    action     VARCHAR(120) NOT NULL COMMENT 'login|register|login_failed|purchase|file_edit|...',
    level      ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    metadata   JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logs_user (user_id),
    KEY idx_logs_action (action),
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: game_history  (Riwayat mini games per user)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS game_history;
CREATE TABLE game_history (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    game       ENUM('gacha','mystery_box','card_flip') NOT NULL,
    result_key VARCHAR(40) NOT NULL COMMENT 'zonk|coin_10|coin_50|coin_100|...',
    reward     INT NOT NULL DEFAULT 0 COMMENT 'Jumlah koin yang dimenangkan',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_gh_user (user_id, created_at),
    CONSTRAINT fk_gh_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: settings  (Konfigurasi runtime: RTP game, bonus, dsb.)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    `key`       VARCHAR(80) NOT NULL,
    `value`     TEXT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: changelogs  (Riwayat update website — halaman Info)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS changelogs;
CREATE TABLE changelogs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version     VARCHAR(20) NOT NULL,
    title       VARCHAR(160) NOT NULL,
    body        TEXT NULL,
    released_at DATE NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_changelog_release (released_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: announcements  (Pengumuman — fitur komunitas Admin)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS announcements;
CREATE TABLE announcements (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title      VARCHAR(160) NOT NULL,
    body       TEXT NOT NULL,
    level      ENUM('info','success','warning') NOT NULL DEFAULT 'info',
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ann_creator FOREIGN KEY (created_by)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: tickets + ticket_replies  (Support system komunitas)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS tickets;
CREATE TABLE tickets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT UNSIGNED NOT NULL,
    subject    VARCHAR(160) NOT NULL,
    category   ENUM('umum','pembayaran','bug','lainnya') NOT NULL DEFAULT 'umum',
    status     ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_status (status),
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS ticket_replies;
CREATE TABLE ticket_replies (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id  BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    message    TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_reply_ticket FOREIGN KEY (ticket_id)
        REFERENCES tickets (id) ON DELETE CASCADE,
    CONSTRAINT fk_reply_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: feedback  (Ulasan komunitas — dimoderasi Admin)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS feedback;
CREATE TABLE feedback (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80) NOT NULL,
    message     VARCHAR(1000) NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    ip_address  VARCHAR(45) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_approval (is_approved)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: weekly_rewards  (Distribusi hadiah leaderboard mingguan)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS weekly_rewards;
CREATE TABLE weekly_rewards (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period     VARCHAR(10) NOT NULL COMMENT 'Key ISO week, mis. 2026-W33',
    user_id    BIGINT UNSIGNED NOT NULL,
    `rank`     TINYINT UNSIGNED NOT NULL,
    bonus      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wr_period_user (period, user_id),
    UNIQUE KEY uq_wr_period_rank (period, `rank`),
    CONSTRAINT fk_wr_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: achievements + user_achievements  (Sistem Badge)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS user_achievements;
DROP TABLE IF EXISTS achievements;
CREATE TABLE achievements (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NULL,
    icon            VARCHAR(10) NOT NULL DEFAULT '🏅',
    condition_type  VARCHAR(40) NOT NULL COMMENT 'total_plays|total_wins|big_win|redeem_count|referral_count|coin_balance',
    condition_value INT UNSIGNED NOT NULL DEFAULT 1,
    reward_coins    INT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ach_code (code)
) ENGINE=InnoDB;

CREATE TABLE user_achievements (
    user_id        BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NOT NULL,
    unlocked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    CONSTRAINT fk_ua_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_ach FOREIGN KEY (achievement_id)
        REFERENCES achievements (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: api_tokens  (Public API untuk bot Discord dsb.)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS api_tokens;
CREATE TABLE api_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(80) NOT NULL COMMENT 'Identitas pemakai token (mis. Bot Discord #1)',
    token_hash   CHAR(64) NOT NULL COMMENT 'SHA-256 dari token plaintext — plaintext tidak disimpan',
    scopes       VARCHAR(255) NOT NULL DEFAULT 'stats' COMMENT 'CSV: stats,leaderboard,user',
    last_used_at DATETIME NULL,
    revoked_at   DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_token_hash (token_hash)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
-- Token magic-link login (sekali pakai, TTL 10 menit, hash sha256)
CREATE TABLE IF NOT EXISTS login_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL,
    expires_at  DATETIME     NOT NULL,
    consumed_at DATETIME     NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lt_user (user_id),
    KEY idx_lt_expires (expires_at),
    CONSTRAINT fk_lt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
