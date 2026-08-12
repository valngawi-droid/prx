<?php

declare(strict_types=1);

namespace ChiperX\Controllers;

use ChiperX\Core\Csrf;
use ChiperX\Core\Response;
use ChiperX\Core\View;

/**
 * Basis semua controller.
 */
abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'layouts/main'): string
    {
        return View::render($view, $data, $layout);
    }

    protected function panel(string $view, array $data = []): string
    {
        return View::render($view, $data, 'layouts/panel');
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Validasi CSRF untuk semua aksi tulis via form/browser. */
    protected function guardCsrf(): void
    {
        Csrf::abortIfInvalid();
    }
}
