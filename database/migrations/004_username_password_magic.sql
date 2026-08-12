-- ============================================================
-- Migrasi 004 — Username & Password + Magic Login Token
-- ============================================================
-- REGISTER: Gmail → OTP → Username & Password → Selesai
-- LOGIN   : Username & Password → link verifikasi email (tanpa ketik OTP)

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS username      VARCHAR(40)  NULL UNIQUE AFTER name,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL COMMENT 'bcrypt/argon2 — plaintext tidak pernah disimpan' AFTER username;

-- Isi username awal untuk akun lama (unik via suffix id)
UPDATE users SET username = CONCAT(LEFT(SUBSTRING_INDEX(email, '@', 1), 30), '_', id)
 WHERE username IS NULL;

-- Token magic-link login: sekali pakai, TTL 10 menit, disimpan sebagai hash
CREATE TABLE IF NOT EXISTS login_tokens (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL COMMENT 'sha256 dari token URL mentah',
    expires_at  DATETIME     NOT NULL COMMENT 'Berlaku 10 menit (WIB)',
    consumed_at DATETIME     NULL,
    ip_address  VARCHAR(45)  NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lt_user (user_id),
    KEY idx_lt_expires (expires_at),
    CONSTRAINT fk_lt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
