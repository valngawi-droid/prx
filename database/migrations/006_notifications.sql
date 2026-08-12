-- Migrasi 006 — Pusat Notifikasi (lonceng 🔔 per pengguna)
CREATE TABLE IF NOT EXISTS notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL COMMENT 'Pemilik notifikasi',
    type       VARCHAR(30)  NOT NULL DEFAULT 'info' COMMENT 'info|success|warning|critical',
    title      VARCHAR(120) NOT NULL,
    body       VARCHAR(500) NULL,
    url        VARCHAR(190) NULL COMMENT 'Tautan tujuan saat notifikasi diklik',
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
