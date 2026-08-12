<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Request;
use ChiperX\Core\Response;
use ChiperX\Models\Notification;

/**
 * Pusat Notifikasi pengguna (lonceng 🔔).
 */
final class NotificationController extends Controller
{
    /** GET /notifikasi — halaman riwayat; sekaligus tandai semua telah dibaca. */
    public function index(Request $req): string
    {
        $uid = (int) auth_user()['id'];
        $items = Notification::latest($uid, 30);
        Notification::markAllRead($uid);
        Notification::prune(30); // bersih-bersih ringan, aman berkat try/catch internal
        return $this->view('notifications/index', [
            'title' => 'Notifikasi',
            'items' => $items,
        ]);
    }

    /** GET /api/notifikasi — jumlah belum dibaca (untuk badge lonceng, polling JS). */
    public function api(Request $req): Response
    {
        return Response::json(['count' => Notification::unreadCount((int) auth_user()['id'])]);
    }
}
