<?php

declare(strict_types=1);

use ChiperX\Controllers\AdminController;
use ChiperX\Controllers\AuthController;
use ChiperX\Controllers\DashboardController;
use ChiperX\Controllers\DownloadController;
use ChiperX\Controllers\GameController;
use ChiperX\Controllers\GateController;
use ChiperX\Controllers\HomeController;
use ChiperX\Controllers\NotificationController;
use ChiperX\Controllers\MessageController;
use ChiperX\Controllers\OwnerController;
use ChiperX\Controllers\ProfileController;
use ChiperX\Controllers\SocialController;
use ChiperX\Controllers\RedeemController;
use ChiperX\Controllers\StoreController;
use ChiperX\Controllers\WebhookController;
use ChiperX\Core\Router;
use ChiperX\Middleware\AdminMiddleware;
use ChiperX\Middleware\AuthMiddleware;
use ChiperX\Middleware\GuestMiddleware;
use ChiperX\Middleware\OwnerMiddleware;

/**
 * Peta rute aplikasi ChiperX.
 */

return static function (Router $r): void {
    // ---------------- PUBLIK ----------------
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/info', [HomeController::class, 'info']);
    $r->get('/links', [HomeController::class, 'links']);
    $r->post('/feedback', [HomeController::class, 'sendFeedback']);
    $r->get('/robots.txt', [HomeController::class, 'robots']);
    $r->get('/sitemap.xml', [HomeController::class, 'sitemap']);
    $r->get('/members', [HomeController::class, 'members']);
    $r->get('/status', [HomeController::class, 'status']);

    // ---------------- AUTH v2 ----------------
    // LOGIN: Username & Password → magic link email (tanpa ketik OTP)
    $r->get('/login', [AuthController::class, 'showLogin'], GuestMiddleware::class);
    $r->post('/auth/login', [AuthController::class, 'loginPassword'], GuestMiddleware::class);
    $r->get('/login/cek-email', [AuthController::class, 'checkEmail'], GuestMiddleware::class);
    $r->post('/auth/login/resend', [AuthController::class, 'resendMagic'], GuestMiddleware::class);
    $r->get('/auth/login-status', [AuthController::class, 'loginStatus']);
    $r->get('/auth/magic/{token}', [AuthController::class, 'magic']);

    // REGISTER: Gmail → OTP → Username & Password → Selesai
    $r->get('/register', [AuthController::class, 'showRegister'], GuestMiddleware::class);
    $r->post('/auth/register/send', [AuthController::class, 'registerSend'], GuestMiddleware::class);
    $r->get('/register/verify', [AuthController::class, 'showRegisterVerify'], GuestMiddleware::class);
    $r->post('/auth/register/verify', [AuthController::class, 'registerVerify'], GuestMiddleware::class);
    $r->get('/register/lengkapi', [AuthController::class, 'completeForm']);
    $r->post('/auth/register/complete', [AuthController::class, 'completeSave']);

    // Jalur OTP klasik (akun lama / lupa password)
    $r->get('/login/otp', [AuthController::class, 'showLoginOtp'], GuestMiddleware::class);
    $r->post('/auth/otp/send', [AuthController::class, 'sendOtp'], GuestMiddleware::class);
    $r->get('/verify', [AuthController::class, 'showVerify'], GuestMiddleware::class);
    $r->post('/auth/otp/verify', [AuthController::class, 'verifyOtp'], GuestMiddleware::class);
    $r->post('/auth/otp/resend', [AuthController::class, 'resendOtp'], GuestMiddleware::class);

    // Lengkapi kredensial untuk akun yang sudah login
    $r->get('/akun/lengkapi', [AuthController::class, 'accountCompleteForm'], AuthMiddleware::class);
    $r->post('/akun/lengkapi', [AuthController::class, 'accountCompleteSave'], AuthMiddleware::class);

    // ---------------- PROFIL (tag role + centang biru + tags kustom) ----------------
    $r->get('/profil', [ProfileController::class, 'show'], AuthMiddleware::class);
    $r->post('/profil', [ProfileController::class, 'update'], AuthMiddleware::class);
    // Format sosial: /profil/@username (ala sosmed), kompatibel tanpa '@'
    $r->post('/profil/@{username}/follow', [ProfileController::class, 'follow'], AuthMiddleware::class);
    $r->get('/profil/@{username}', [ProfileController::class, 'publicShow']);
    $r->get('/profil/{username}', [ProfileController::class, 'publicShow']);
    $r->get('/u/{username}', [ProfileController::class, 'legacyRedirect']); // format lama → dialihkan

    // ---------------- KOMUNITAS v2.2 (feed ala Instagram/Facebook) ----------------
    $r->get('/komunitas', [SocialController::class, 'feed']);
    $r->get('/media/social/{file}', [SocialController::class, 'media']);
    $r->post('/komunitas/post', [SocialController::class, 'store'], AuthMiddleware::class);
    $r->post('/komunitas/{id}/like', [SocialController::class, 'like'], AuthMiddleware::class);
    $r->post('/komunitas/{id}/comment', [SocialController::class, 'comment'], AuthMiddleware::class);
    $r->post('/komunitas/{id}/delete', [SocialController::class, 'deletePost'], AuthMiddleware::class);
    $r->post('/komunitas/komentar/{id}/delete', [SocialController::class, 'deleteComment'], AuthMiddleware::class);

    // ---------------- STORIES 24 JAM v2.3 (ala WA Status / IG Story) ----------------
    $r->post('/komunitas/story', [SocialController::class, 'storyStore'], AuthMiddleware::class);
    $r->get('/story/{username}', [SocialController::class, 'storyShow']);
    $r->post('/story/{id}/delete', [SocialController::class, 'storyDelete'], AuthMiddleware::class);

    // ---------------- PESAN PRIBADI v2.2 (DM ala WhatsApp/Telegram) ----------------
    $r->get('/pesan', [MessageController::class, 'index'], AuthMiddleware::class);
    $r->get('/pesan/{username}', [MessageController::class, 'thread'], AuthMiddleware::class);
    $r->post('/pesan/{username}', [MessageController::class, 'send'], AuthMiddleware::class);
    $r->get('/pesan/{username}/json', [MessageController::class, 'poll'], AuthMiddleware::class);

    // ---------------- NOTIFIKASI (lonceng 🔔) ----------------
    $r->get('/notifikasi', [NotificationController::class, 'index'], AuthMiddleware::class);
    $r->get('/api/notifikasi', [NotificationController::class, 'api'], AuthMiddleware::class);

    $r->post('/logout', [AuthController::class, 'logout'], AuthMiddleware::class);

    // ---------- GERBANG RAHASIA PANEL (lapis ke-2 setelah OTP) ----------
    $r->get('/zszdgj/login', [GateController::class, 'show']);
    $r->post('/zszdgj/login', [GateController::class, 'login']);
    $r->post('/zszdgj/logout', [GateController::class, 'logout'], AuthMiddleware::class);

    // ---------------- USER (wajib login) ----------------
    $r->get('/dashboard', [DashboardController::class, 'index'], AuthMiddleware::class);
    $r->post('/dashboard/claim-daily', [DashboardController::class, 'claimDaily'], AuthMiddleware::class);
    $r->post('/dashboard/quest-claim', [DashboardController::class, 'questClaim'], AuthMiddleware::class);
    $r->post('/dashboard/shout', [DashboardController::class, 'shout'], AuthMiddleware::class);
    $r->get('/dashboard/shouts.json', [DashboardController::class, 'shoutsApi'], AuthMiddleware::class);
    $r->post('/dashboard/shouts/{id}/delete', [DashboardController::class, 'shoutDelete'], AuthMiddleware::class);
    $r->post('/dashboard/transfer', [DashboardController::class, 'transfer'], AuthMiddleware::class);
    $r->post('/dashboard/profile', [DashboardController::class, 'updateProfile'], AuthMiddleware::class);
    $r->post('/dashboard/tickets', [DashboardController::class, 'createTicket'], AuthMiddleware::class);
    $r->post('/dashboard/tickets/{id}/reply', [DashboardController::class, 'replyTicket'], AuthMiddleware::class);

    $r->get('/games', [GameController::class, 'index'], AuthMiddleware::class);
    $r->post('/games/play', [GameController::class, 'play'], AuthMiddleware::class);

    $r->get('/redeem', [RedeemController::class, 'index'], AuthMiddleware::class);
    $r->post('/redeem-code', [RedeemController::class, 'claimCode'], AuthMiddleware::class);
    $r->post('/redeem/{id}', [RedeemController::class, 'redeem'], AuthMiddleware::class);

    $r->get('/store', [StoreController::class, 'index'], AuthMiddleware::class);
    $r->post('/store/buy/{id}', [StoreController::class, 'buy'], AuthMiddleware::class);
    $r->get('/store/checkout/{ref}', [StoreController::class, 'checkout'], AuthMiddleware::class);
    $r->get('/store/status/{ref}', [StoreController::class, 'status'], AuthMiddleware::class);
    $r->post('/store/cancel/{ref}', [StoreController::class, 'cancel'], AuthMiddleware::class);

    $r->get('/download/{id}', [DownloadController::class, 'show'], AuthMiddleware::class);

    // ---------------- WEBHOOK PAYMENT (tanpa auth; diamankan signature) ----------------
    $r->post('/webhook/{driver}', [WebhookController::class, 'handle']);

    // ---------------- PUBLIC API v1 (Bearer token — untuk bot Discord dsb.) ----------------
    $r->get('/api/v1/stats', [\ChiperX\Controllers\ApiController::class, 'stats'], \ChiperX\Middleware\ApiTokenMiddleware::class);
    $r->get('/api/v1/leaderboard', [\ChiperX\Controllers\ApiController::class, 'leaderboard'], \ChiperX\Middleware\ApiTokenMiddleware::class);
    $r->get('/api/v1/user/{email}', [\ChiperX\Controllers\ApiController::class, 'userShow'], \ChiperX\Middleware\ApiTokenMiddleware::class);

    // ---------------- ADMIN PANEL (admin + owner) ----------------
    $r->get('/admin', [AdminController::class, 'index'], AdminMiddleware::class);

    $r->get('/admin/products', [AdminController::class, 'products'], AdminMiddleware::class);
    $r->post('/admin/products', [AdminController::class, 'storeProduct'], AdminMiddleware::class);
    $r->post('/admin/products/{id}/update', [AdminController::class, 'updateProduct'], AdminMiddleware::class);
    $r->post('/admin/products/{id}/delete', [AdminController::class, 'deleteProduct'], AdminMiddleware::class);

    $r->get('/admin/users', [AdminController::class, 'users'], AdminMiddleware::class);
    $r->post('/admin/users', [AdminController::class, 'storeUser'], AdminMiddleware::class);
    $r->post('/admin/users/{id}/update', [AdminController::class, 'updateUser'], AdminMiddleware::class);

    $r->get('/admin/transactions', [AdminController::class, 'transactions'], AdminMiddleware::class);

    $r->get('/admin/tickets', [AdminController::class, 'tickets'], AdminMiddleware::class);
    $r->post('/admin/tickets/{id}/reply', [AdminController::class, 'replyTicket'], AdminMiddleware::class);
    $r->post('/admin/tickets/{id}/status', [AdminController::class, 'closeTicket'], AdminMiddleware::class);

    $r->get('/admin/announcements', [AdminController::class, 'announcements'], AdminMiddleware::class);
    $r->post('/admin/announcements', [AdminController::class, 'storeAnnouncement'], AdminMiddleware::class);
    $r->post('/admin/announcements/{id}/toggle', [AdminController::class, 'toggleAnnouncement'], AdminMiddleware::class);
    $r->post('/admin/announcements/{id}/delete', [AdminController::class, 'deleteAnnouncement'], AdminMiddleware::class);

    // Kode redeem kustom + moderasi shoutbox
    $r->get('/admin/codes', [AdminController::class, 'codes'], AdminMiddleware::class);
    $r->post('/admin/codes', [AdminController::class, 'saveCode'], AdminMiddleware::class);
    $r->post('/admin/codes/{id}/toggle', [AdminController::class, 'toggleCode'], AdminMiddleware::class);
    $r->post('/admin/shouts/{id}/delete', [AdminController::class, 'shoutDelete'], AdminMiddleware::class);

    $r->get('/admin/feedback', [AdminController::class, 'feedback'], AdminMiddleware::class);
    $r->post('/admin/feedback/{id}/approve', [AdminController::class, 'approveFeedback'], AdminMiddleware::class);
    $r->post('/admin/feedback/{id}/delete', [AdminController::class, 'deleteFeedback'], AdminMiddleware::class);

    // ---------------- OWNER PANEL (owner only / god mode) ----------------
    $r->get('/owner', [OwnerController::class, 'index'], OwnerMiddleware::class);
    $r->post('/owner/discord/test', [OwnerController::class, 'testDiscord'], OwnerMiddleware::class);
    $r->post('/owner/broadcast', [OwnerController::class, 'broadcast'], OwnerMiddleware::class);
    $r->get('/owner/backups', [OwnerController::class, 'backups'], OwnerMiddleware::class);
    $r->post('/owner/backups/create', [OwnerController::class, 'backupCreate'], OwnerMiddleware::class);
    $r->get('/owner/backups/{file}/download', [OwnerController::class, 'backupDownload'], OwnerMiddleware::class);

    // 🧯 Firewall (anti-deface/hack)
    $r->get('/owner/firewall', [OwnerController::class, 'firewall'], OwnerMiddleware::class);
    $r->post('/owner/firewall/ban', [OwnerController::class, 'firewallBan'], OwnerMiddleware::class);
    $r->post('/owner/firewall/unban', [OwnerController::class, 'firewallUnban'], OwnerMiddleware::class);

    $r->get('/owner/users', [OwnerController::class, 'users'], OwnerMiddleware::class);
    $r->get('/owner/integrations', [OwnerController::class, 'integrations'], OwnerMiddleware::class);
    $r->post('/owner/integrations', [OwnerController::class, 'saveIntegrations'], OwnerMiddleware::class);
    $r->post('/owner/integrations/test', [OwnerController::class, 'testIntegration'], OwnerMiddleware::class);
    $r->post('/owner/users', [OwnerController::class, 'storeUser'], OwnerMiddleware::class);
    $r->post('/owner/users/{id}/update', [OwnerController::class, 'updateUser'], OwnerMiddleware::class);
    $r->post('/owner/users/{id}/ban', [OwnerController::class, 'banUser'], OwnerMiddleware::class);
    $r->post('/owner/users/{id}/verify', [OwnerController::class, 'verifyUser'], OwnerMiddleware::class);
    $r->post('/owner/users/{id}/badges', [OwnerController::class, 'badgesUser'], OwnerMiddleware::class);
    $r->post('/owner/users/{id}/delete', [OwnerController::class, 'deleteUser'], OwnerMiddleware::class);

    $r->get('/owner/filemanager', [OwnerController::class, 'fileManager'], OwnerMiddleware::class);
    $r->get('/owner/api/files/tree', [OwnerController::class, 'fmTree'], OwnerMiddleware::class);
    $r->get('/owner/api/files/read', [OwnerController::class, 'fmRead'], OwnerMiddleware::class);
    $r->post('/owner/api/files/save', [OwnerController::class, 'fmSave'], OwnerMiddleware::class);
    $r->post('/owner/api/files/create', [OwnerController::class, 'fmCreate'], OwnerMiddleware::class);
    $r->post('/owner/api/files/delete', [OwnerController::class, 'fmDelete'], OwnerMiddleware::class);

    $r->get('/owner/links', [OwnerController::class, 'links'], OwnerMiddleware::class);
    $r->post('/owner/links', [OwnerController::class, 'storeLink'], OwnerMiddleware::class);
    $r->post('/owner/links/{id}/update', [OwnerController::class, 'updateLink'], OwnerMiddleware::class);
    $r->post('/owner/links/{id}/delete', [OwnerController::class, 'deleteLink'], OwnerMiddleware::class);

    $r->get('/owner/settings', [OwnerController::class, 'settings'], OwnerMiddleware::class);
    $r->post('/owner/settings', [OwnerController::class, 'saveSettings'], OwnerMiddleware::class);

    $r->get('/owner/logs', [OwnerController::class, 'logs'], OwnerMiddleware::class);

    $r->get('/owner/api-tokens', [OwnerController::class, 'apiTokens'], OwnerMiddleware::class);
    $r->post('/owner/api-tokens', [OwnerController::class, 'storeApiToken'], OwnerMiddleware::class);
    $r->post('/owner/api-tokens/{id}/revoke', [OwnerController::class, 'revokeApiToken'], OwnerMiddleware::class);
};
