-- ============================================================
-- ChiperX — Data Awal (Seed)
-- GANTI email owner di bawah dengan email Gmail Anda, lalu
-- login pertama kali melalui mekanisme OTP seperti biasa.
-- ============================================================
USE chiperx;

-- Akun OWNER (login via OTP, tidak ada password)
INSERT INTO users (name, email, role, status, coin_balance, play_tickets)
VALUES ('Owner ChiperX', 'owner@gmail.com', 'owner', 'active', 9999, 3);

-- Link sosial media (halaman All Link ChiperX)
INSERT INTO links (title, url, icon_class, color, order_num) VALUES
('Discord Community', 'https://discord.gg/chiperx', 'discord',   '#5865F2', 1),
('Telegram Channel',  'https://t.me/chiperx',       'telegram',  '#29A9EB', 2),
('Instagram',         'https://instagram.com/chiperx','instagram','#E1306C', 3),
('GitHub',            'https://github.com/chiperx',  'github',    '#c9d1d9', 4),
('Website Resmi',     'https://chiperx.example.com', 'web',       '#22d3ee', 5);

-- Produk Redeem Center (ditukar dengan ChiperX Coin)
INSERT INTO products (name, slug, type, price, stock, file_url, description, is_featured) VALUES
('Template Web Portfolio Dark', 'template-portfolio-dark', 'coin_redeem', 150, NULL, 'https://github.com/chiperx/template-portfolio', 'Source code template portfolio dark-mode dengan Tailwind CSS.', 1),
('Source Code Bot Discord JS', 'sc-bot-discord-js', 'coin_redeem', 300, NULL, 'https://github.com/chiperx/bot-discord', 'Bot Discord serbaguna: moderation, music, ticket system.', 0),
('UI Kit Dashboard Admin', 'ui-kit-dashboard', 'coin_redeem', 500, 20, 'https://github.com/chiperx/ui-kit', 'Komponen dashboard admin siap pakai (HTML + Tailwind).', 0);

-- Produk Premium Store (dibayar via payment gateway)
INSERT INTO products (name, slug, type, price, stock, file_url, description, is_featured) VALUES
('ChiperX Panel Pro — Full Source', 'panel-pro-full', 'premium_store', 75000, NULL, 'https://github.com/chiperx/panel-pro', 'Source code lengkap panel premium + dokumentasi instalasi.', 1),
('Bot WhatsApp Multi-Device', 'bot-wa-md', 'premium_store', 45000, 10, 'https://github.com/chiperx/bot-wa', 'Bot WhatsApp MD dengan 200+ fitur, siap deploy.', 0);

-- Pengaturan RTP Mini Games (persentase, total per game harus 100)
INSERT INTO settings (`key`, `value`) VALUES
('site_tagline',        'Platform Digital Komunitas Generasi Baru'),
('register_bonus',      '25'),
('daily_tickets',       '3'),
('daily_bonus',         '15'),
('referral_bonus',      '50'),
('gacha_zonk',          '60'),
('gacha_coin_10',       '25'),
('gacha_coin_50',       '10'),
('gacha_coin_100',      '5'),
('weekly_reward_1',     '500'),
('weekly_reward_2',     '250'),
('weekly_reward_3',     '100'),
('box_zonk',            '50'),
('box_coin_25',         '30'),
('box_coin_75',         '15'),
('box_coin_150',        '5'),
('card_zonk',           '40'),
('card_coin_20',        '35'),
('card_coin_60',        '20'),
('card_coin_120',       '5');

-- Timeline & Changelog (halaman Informasi ChiperX)
INSERT INTO changelogs (version, title, body, released_at) VALUES
('v1.0.0', 'Rilis Perdana ChiperX', 'Landing page 3D, sistem OTP login, dan halaman All Link diluncurkan.', '2025-01-10'),
('v1.1.0', 'Mini Games & ChiperX Coin', 'Gacha Spin, Mystery Box, dan Card Flip dirilis. Sistem tiket harian 3x/hari.', '2025-03-02'),
('v1.2.0', 'Redeem Center', 'Koin kini bisa ditukar source code & project eksklusif.', '2025-05-18'),
('v2.0.0', 'ChiperX Store + Auto Payment', 'Integrasi payment gateway (QRIS/E-Wallet) dengan webhook otomatis.', '2025-09-01'),
('v2.1.0', 'Owner God Mode', 'File manager berbasis Monaco Editor, Discord audit log, dan pengaturan RTP game.', '2026-02-14');

-- Pengumuman contoh
INSERT INTO announcements (title, body, level, created_by) VALUES
('Selamat datang di ChiperX!', 'Mainkan mini games setiap hari, kumpulkan ChiperX Coin, dan tukarkan dengan project eksklusif di Redeem Center.', 'success', 1);

-- Achievement / Badge bawaan
INSERT INTO achievements (code, name, description, icon, condition_type, condition_value, reward_coins) VALUES
('first_play',   'Langkah Pertama',     'Mainkan mini game pertamamu.',                              '🎮', 'total_plays',     1,   10),
('grinder_50',   'Sang Penggiling',     'Selesaikan 50 permainan.',                                  '⚙️', 'total_plays',     50,  50),
('winner_10',    'Mesin Keberuntungan', 'Menangkan hadiah 10 kali.',                                 '🍀', 'total_wins',      10,  40),
('jackpot',      'JACKPOT!',            'Menangkan hadiah 100+ koin dalam sekali main.',             '💎', 'big_win',         100, 100),
('saver_100',    'Kolektor Koin',       'Miliki saldo 100 koin sekaligus.',                          '🪙', 'coin_balance',    100, 25),
('first_redeem', 'Penukar Perdana',     'Tukarkan item pertamamu di Redeem Center.',                 '🎁', 'redeem_count',    1,   20),
('recruiter_3',  'Perekrut Handal',     'Undang 3 teman bergabung lewat link referral.',             '👥', 'referral_count',  3,   75);
