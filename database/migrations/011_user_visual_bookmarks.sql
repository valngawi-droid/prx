-- ============================================================
-- Migrasi 011 — V2.7: Avatar + Sampul + Aksen + Status profil,
--                     Bookmark (simpan postingan)
-- ============================================================
USE chiperx;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS avatar VARCHAR(80) NULL COMMENT 'File avatar di storage/uploads/avatar' AFTER bio,
    ADD COLUMN IF NOT EXISTS cover VARCHAR(80) NULL COMMENT 'File sampul di storage/uploads/sampul' AFTER avatar,
    ADD COLUMN IF NOT EXISTS accent VARCHAR(7) NULL COMMENT 'Warna aksen profil (#RRGGBB)' AFTER cover,
    ADD COLUMN IF NOT EXISTS status_text VARCHAR(60) NULL COMMENT 'Status/mood singkat tampil di profil' AFTER accent;

-- 🔖 Bookmark: postingan yang disimpan member (ala Instagram Saved)
CREATE TABLE IF NOT EXISTS bookmarks (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    post_id    BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bookmark (user_id, post_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
