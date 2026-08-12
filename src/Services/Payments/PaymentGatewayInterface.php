<?php

declare(strict_types=1);

namespace ChiperX\Services\Payments;

/**
 * Kontrak driver payment gateway.
 */
interface PaymentGatewayInterface
{
    /**
     * Buat tagihan baru di gateway.
     * @return array{ref:string, checkout_url:?string, qr_url:?string, expired_at:?string, method_label:string}
     */
    public function createInvoice(array $transaction, array $product, array $user, string $method): array;

    /**
     * Verifikasi signature webhook. Mengembalikan ref + status baku, atau null jika signature tidak valid.
     * @return array{ref:string, status:'paid'|'failed'|'expired'}|null
     */
    public function verifyCallback(string $rawBody, array $headers): ?array;

    /**
     * Uji koneksi/kredensial secara LANGSUNG dari panel web.
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array;
}
