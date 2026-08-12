-- ============================================================
-- Migrasi 007 — V2.1: Login Streak + Quest + Kode Redeem + Shoutbox
-- ============================================================
USE chiperx;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS streak_count     INT  NOT NULL DEFAULT 0 COMMENT 'Hari ke-N beruntun klaim harian' AFTER last_daily_claim,
    ADD COLUMN IF NOT EXISTS quest_claimed_on DATE NULL COMMENT 'Tanggal klaim quest harian terakhir' AFTER streak_count;

CREATE TABLE IF NOT EXISTS redeem_codes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(40) NOT NULL UNIQUE COMMENT 'Kode (UPPERCASE)',
    coins      INT NOT NULL DEFAULT 0 COMMENT 'Koin yang diberikan',
    quota      INT NULL COMMENT 'Maksimal klaim total; NULL = tanpa batas',
    used       INT NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS redeem_code_claims (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_claim (code_id, user_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shouts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    message    VARCHAR(190) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kode perkenalan V2.1 (100 klaim pertama, 30 hari)
INSERT INTO redeem_codes (code, coins, quota, expires_at) VALUES
('NEBULA21', 50, 100, DATE_ADD(NOW(), INTERVAL 30 DAY))
ON DUPLICATE KEY UPDATE code = code;
