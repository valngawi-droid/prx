-- Migrasi 005 — Profil: centang biru (verified) + tags kustom
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Centang biru — diatur Owner',
    ADD COLUMN IF NOT EXISTS badges VARCHAR(190) NULL COMMENT 'CSV tags kustom profil (maks 5, @ 24 char)';
