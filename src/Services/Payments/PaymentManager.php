<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

use ChiperX\Core\Config;
use ChiperX\Core\Env;

/**
 * Factory + fasad tunggal untuk semua driver payment gateway.
 * Driver dipilih via PAYMENT_DRIVER di .env (tripay|midtrans|paydisini).
 */
final class PaymentManager
{
    public static function driver(): PaymentGatewayInterface
    {
        return match (strtolower((string) Config::secret('PAYMENT_DRIVER', 'tripay'))) {
            'midtrans'  => new MidtransGateway(),
            'paydisini' => new PaydisiniGateway(),
            'duitku'    => new DuitkuGateway(),
            'pakasir'   => new PakasirGateway(),
            default     => new TripayGateway(),
        };
    }

    /** Callback masuk ke URL spesifik — pilih driver berdasar nama route. */
    public static function driverByName(string $name): ?PaymentGatewayInterface
    {
        return match (strtolower($name)) {
            'tripay'    => new TripayGateway(),
            'midtrans'  => new MidtransGateway(),
            'paydisini' => new PaydisiniGateway(),
            'duitku'    => new DuitkuGateway(),
            'pakasir'   => new PakasirGateway(),
            default     => null,
        };
    }

    /** Metode pembayaran yang didukung di UI checkout (menyesuaikan driver aktif). @return array<string, string> */
    public static function availableMethods(): array
    {
        $driver = strtolower((string) Config::secret('PAYMENT_DRIVER', 'tripay'));
        return match ($driver) {
            'pakasir' => ['QRIS' => 'QRIS (Semua E-Wallet & M-Banking)'],
            'duitku'  => [
                'QRIS' => 'QRIS (Semua E-Wallet & M-Banking)',
                'VC'   => 'Kartu Kredit/Debit',
                'BT'   => 'Virtual Account Bank',
            ],
            default   => [
                'QRIS'  => 'QRIS (Semua E-Wallet & M-Banking)',
                'DANA'  => 'DANA',
                'OVO'   => 'OVO',
                'GOPAY' => 'GoPay',
            ],
        };
    }
}
