-- ============================================================
-- Migrasi 010 — V2.4 SECURITY: Firewall anti-deface/hack
-- (ban IP 10 menit untuk peringatan, permanen untuk serangan)
-- + rate limiting + jurnal ancaman
-- ============================================================
USE chiperx;

-- IP yang diblokir
CREATE TABLE IF NOT EXISTS banned_ips (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip           VARCHAR(45) NOT NULL UNIQUE,
    reason       VARCHAR(190) NOT NULL DEFAULT 'Aktivitas mencurigakan',
    permanent    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = ban permanen (deface/hack)',
    strikes      INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Total pelanggaran tercatat',
    banned_until DATETIME NULL COMMENT 'NULL = permanen',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NULL,
    INDEX idx_ban_until (banned_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jurnal ancaman (untuk halaman Owner > Firewall)
CREATE TABLE IF NOT EXISTS threat_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45) NOT NULL,
    level      ENUM('warning','critical') NOT NULL DEFAULT 'warning',
    pattern    VARCHAR(190) NOT NULL COMMENT 'Pola serangan yang cocok',
    uri        VARCHAR(250) NOT NULL,
    agent      VARCHAR(250) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_threat_ip (ip),
    INDEX idx_threat_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ember rate-limit (hitungan request per IP per menit)
CREATE TABLE IF NOT EXISTS fw_rate (
    ip     VARCHAR(45) NOT NULL PRIMARY KEY,
    bucket CHAR(12) NOT NULL COMMENT 'YmdHi (menit berjalan)',
    hits   INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
