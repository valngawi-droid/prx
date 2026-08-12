-- ============================================================
-- Migrasi 008 — V2.2 SOCIAL: Feed Komunitas + Suka + Komentar
--                + Pesan Pribadi (DM) + Bio Profil
-- Mirip: Instagram (feed+foto+suka), Facebook (komentar),
--        WhatsApp/Telegram (chat 1-lawan-1)
-- ============================================================
USE chiperx;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS bio VARCHAR(160) NULL COMMENT 'Bio singkat tampil di profil publik' AFTER badges;

-- ------------------------------------------------ POSTINGAN --
CREATE TABLE IF NOT EXISTS posts (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    body           VARCHAR(500) NOT NULL COMMENT 'Teks postingan',
    image          VARCHAR(80) NULL COMMENT 'Nama file gambar di storage/uploads/social',
    likes_count    INT UNSIGNED NOT NULL DEFAULT 0,
    comments_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_posts_created (created_at),
    INDEX idx_posts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------- SUKA --
CREATE TABLE IF NOT EXISTS post_likes (
    post_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, user_id),
    INDEX idx_pl_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------- KOMENTAR --
CREATE TABLE IF NOT EXISTS post_comments (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    body       VARCHAR(300) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pc_post (post_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------- PESAN PRIBADI --
CREATE TABLE IF NOT EXISTS messages (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id    BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    body         VARCHAR(500) NOT NULL,
    read_at      DATETIME NULL COMMENT 'Kapan dibaca penerima',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_pair (sender_id, recipient_id, id),
    INDEX idx_msg_inbox (recipient_id, sender_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Postingan sambutan dari akun owner pertama (id=1), abaikan jika gagal
INSERT IGNORE INTO posts (id, user_id, body) VALUES
(1, 1, '🎉 Selamat datang di Feed Komunitas ChiperX! Bagikan momenmu, beri ❤️, dan ngobrol seru di sini. Sopan ya! 💜');
