-- ============================================================
-- Migrasi 003 — Leaderboard Mingguan, Achievement, API Tokens
-- Untuk instalasi LAMA (instalasi baru sudah include di schema.sql)
-- ============================================================
USE chiperx;

CREATE TABLE IF NOT EXISTS weekly_rewards (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    period     VARCHAR(10) NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    `rank`     TINYINT UNSIGNED NOT NULL,
    bonus      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wr_period_user (period, user_id),
    UNIQUE KEY uq_wr_period_rank (period, `rank`),
    CONSTRAINT fk_wr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS achievements (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    code            VARCHAR(40) NOT NULL,
    name            VARCHAR(120) NOT NULL,
    description     VARCHAR(255) NULL,
    icon            VARCHAR(10) NOT NULL DEFAULT '🏅',
    condition_type  VARCHAR(40) NOT NULL,
    condition_value INT UNSIGNED NOT NULL DEFAULT 1,
    reward_coins    INT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ach_code (code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_achievements (
    user_id        BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NOT NULL,
    unlocked_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, achievement_id),
    CONSTRAINT fk_ua_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ua_ach FOREIGN KEY (achievement_id) REFERENCES achievements (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_tokens (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(80) NOT NULL,
    token_hash   CHAR(64) NOT NULL,
    scopes       VARCHAR(255) NOT NULL DEFAULT 'stats',
    last_used_at DATETIME NULL,
    revoked_at   DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_token_hash (token_hash)
) ENGINE=InnoDB;

INSERT IGNORE INTO achievements (code, name, description, icon, condition_type, condition_value, reward_coins) VALUES
('first_play',   'Langkah Pertama',     'Mainkan mini game pertamamu.',                  '🎮', 'total_plays',    1,   10),
('grinder_50',   'Sang Penggiling',     'Selesaikan 50 permainan.',                      '⚙️', 'total_plays',    50,  50),
('winner_10',    'Mesin Keberuntungan', 'Menangkan hadiah 10 kali.',                     '🍀', 'total_wins',     10,  40),
('jackpot',      'JACKPOT!',            'Menangkan hadiah 100+ koin dalam sekali main.', '💎', 'big_win',        100, 100),
('saver_100',    'Kolektor Koin',       'Miliki saldo 100 koin sekaligus.',              '🪙', 'coin_balance',   100, 25),
('first_redeem', 'Penukar Perdana',     'Tukarkan item pertamamu di Redeem Center.',     '🎁', 'redeem_count',   1,   20),
('recruiter_3',  'Perekrut Handal',     'Undang 3 teman bergabung lewat link referral.', '👥', 'referral_count', 3,   75);

INSERT INTO settings (`key`, `value`) VALUES
('weekly_reward_1', '500'),
('weekly_reward_2', '250'),
('weekly_reward_3', '100')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
