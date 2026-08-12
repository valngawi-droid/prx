-- ============================================================
-- Migrasi 009 — V2.3: Ikuti member (ala IG/FB) + Stories 24 jam
--                (ala WA Status/IG Story) + Status Online
-- ============================================================
USE chiperx;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_activity DATETIME NULL COMMENT 'Aktivitas terakhir (status online)' AFTER bio;

-- ----------------------------------------- IKUTI / PENGIKUT --
CREATE TABLE IF NOT EXISTS follows (
    follower_id BIGINT UNSIGNED NOT NULL COMMENT 'Yang mengikuti',
    followed_id BIGINT UNSIGNED NOT NULL COMMENT 'Yang diikuti',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, followed_id),
    INDEX idx_fl_followed (followed_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------- STORIES --
CREATE TABLE IF NOT EXISTS stories (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL,
    image      VARCHAR(80) NOT NULL COMMENT 'File foto di storage/uploads/social',
    caption    VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_st_user (user_id, id),
    INDEX idx_st_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
