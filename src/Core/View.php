<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * View engine PHP-native dengan layout + output buffering.
 * Semua output user WAJIB lewat e() (XSS protection).
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): string
    {
        $content = self::partial($view, $data);
        if ($layout === '') {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/src/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
