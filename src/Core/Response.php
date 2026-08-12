<?php

declare(strict_types=1);

namespace ChiperX\Core;

/**
 * Response HTTP: HTML, JSON, atau Redirect.
 */
final class Response
{
    public function __construct(
        private string $body,
        private int $status = 200,
        private string $contentType = 'text/html; charset=utf-8',
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', $status, 'application/json; charset=utf-8');
    }

    public static function redirect(string $to): self
    {
        $r = new self('', 302);
        header('Location: ' . $to, true, 302);
        return $r;
    }

    public function send(): void
    {
        http_response_code($this->status);
        if ($this->body !== '' || $this->status !== 302) {
            header('Content-Type: ' . $this->contentType);
            echo $this->body;
        }
    }
}
