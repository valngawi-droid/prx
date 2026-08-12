-- ============================================================
-- Migrasi 002 — Referral & Bonus Login Harian
-- Untuk instalasi LAMA yang sudah menjalankan schema.sql v1.
-- (Instalasi baru sudah mencakup ini di schema.sql terbaru.)
-- ============================================================
USE chiperx;

ALTER TABLE users
    ADD COLUMN referral_code    VARCHAR(12) NULL COMMENT 'Kode referral unik milik user' AFTER avatar,
    ADD UNIQUE KEY uq_users_referral (referral_code),
    ADD COLUMN referred_by      BIGINT UNSIGNED NULL COMMENT 'ID user pengundang (referral)' AFTER referral_code,
    ADD COLUMN last_daily_claim DATE NULL COMMENT 'Tanggal (WIB) klaim bonus harian terakhir' AFTER referred_by,
    ADD CONSTRAINT fk_users_referred FOREIGN KEY (referred_by)
        REFERENCES users (id) ON DELETE SET NULL;

-- Backfill kode referral untuk akun yang sudah ada (dijamin unik via ID)
UPDATE users SET referral_code = CONCAT('CX', LPAD(id, 6, '0')) WHERE referral_code IS NULL;

-- Pengaturan baru
INSERT INTO settings (`key`, `value`) VALUES
('daily_bonus',    '15'),
('referral_bonus', '50')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
