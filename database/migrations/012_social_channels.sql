-- =====================================================================
-- 012_social_channels.sql — NEBULA v2.8 "SOCIAL NEBULA"
-- Fitur Discord (channels+slowmode+pin+reaksi), WhatsApp (DM pro),
-- Instagram (arsip/balas komentar/story views), Telegram (poll+self-destruct)
-- =====================================================================

-- 📢 CHANNEL gaya Discord (#umum, #gaming, #pengumuman announce-only)
CREATE TABLE IF NOT EXISTS channels (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(40)  NOT NULL,
    name        VARCHAR(60)  NOT NULL,
    topic       VARCHAR(120) NULL,
    kind        VARCHAR(10)  NOT NULL DEFAULT 'text' COMMENT 'text=semua bisa chat | announce=hanya staf',
    slowmode    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Jeda kirim (detik), 0=mati',
    pinned_id   BIGINT UNSIGNED NULL COMMENT 'Pesan yang di-pin (channel_messages.id)',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_channels_slug (slug)
) ENGINE=InnoDB;

-- Seed channel bawaan (aman diulang — abaikan bila slug sudah ada)
INSERT IGNORE INTO channels (slug, name, topic, kind)
VALUES ('umum', '💬 Umum', 'Ngobrol santai semua member', 'text'),
       ('gaming', '🎮 Gaming', 'Bahas game & strategi', 'text'),
       ('pengumuman', '📢 Pengumuman', 'Info resmi dari tim ChiperX', 'announce');

-- Pesan channel (text | poll) + reply kutipan + label diedit
CREATE TABLE IF NOT EXISTS channel_messages (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel_id   BIGINT UNSIGNED NOT NULL,
    user_id      BIGINT UNSIGNED NOT NULL,
    kind         VARCHAR(8)  NOT NULL DEFAULT 'text',
    body         TEXT        NOT NULL,
    reply_to     BIGINT UNSIGNED NULL,
    poll_options VARCHAR(600) NULL COMMENT 'JSON ["opsi1","opsi2",...]',
    edited_at    DATETIME    NULL,
    created_at   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cm_channel (channel_id, id)
) ENGINE=InnoDB;

-- 😀 Reaksi emoji di pesan channel (satu user per emoji)
CREATE TABLE IF NOT EXISTS channel_reactions (
    message_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    emoji      VARCHAR(8) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id, emoji)
) ENGINE=InnoDB;

-- 📊 Suara polling (1 suara per user per poll)
CREATE TABLE IF NOT EXISTS channel_poll_votes (
    message_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    opt        TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id, user_id)
) ENGINE=InnoDB;

-- 👀 Siapa melihat Story (Instagram)
CREATE TABLE IF NOT EXISTS story_views (
    story_id  BIGINT UNSIGNED NOT NULL,
    user_id   BIGINT UNSIGNED NOT NULL,
    viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (story_id, user_id)
) ENGINE=InnoDB;

-- 📇 Blokir user (WhatsApp) — yang diblokir tak bisa mengirim DM
CREATE TABLE IF NOT EXISTS blocks (
    blocker_id BIGINT UNSIGNED NOT NULL,
    blocked_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_id, blocked_id)
) ENGINE=InnoDB;

-- ✍️ Typing indicator DM (TTL 8 detik, dipruning saat baca)
CREATE TABLE IF NOT EXISTS dm_typing (
    user_id BIGINT UNSIGNED NOT NULL,
    peer_id BIGINT UNSIGNED NOT NULL,
    ts      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, peer_id)
) ENGINE=InnoDB;

-- 😀 Reaksi pengumuman (Telegram channel vibes)
CREATE TABLE IF NOT EXISTS announcement_reactions (
    announcement_id BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    emoji           VARCHAR(8) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, user_id, emoji)
) ENGINE=InnoDB;

-- 📥 Instagram: arsip postingan + 🔗 balas komentar (parent)
ALTER TABLE posts
    ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = diarsipkan pemiliknya (disembunyikan)';
ALTER TABLE post_comments
    ADD COLUMN IF NOT EXISTS parent_id BIGINT UNSIGNED NULL COMMENT 'Balasan komentar (post_comments.id)';

-- ✉️ WhatsApp/Telegram DM pro: foto, reply, hapus-untuk-semua, pin, self-destruct
ALTER TABLE messages
    ADD COLUMN IF NOT EXISTS image      VARCHAR(80) NULL COMMENT 'Foto lampiran (bucket dm)',
    ADD COLUMN IF NOT EXISTS reply_to   BIGINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL COMMENT 'Hapus untuk semua orang',
    ADD COLUMN IF NOT EXISTS pinned_at  DATETIME NULL COMMENT 'Pesan di-pin di atas chat',
    ADD COLUMN IF NOT EXISTS expire_at  DATETIME NULL COMMENT 'Self-destruct (Telegram secret chat)';

-- ⏰ Pengumuman terjadwal (Telegram scheduled post)
ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS scheduled_at DATETIME NULL COMMENT 'Tampil mulai jam ini; NULL = langsung';
