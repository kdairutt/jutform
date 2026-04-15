<?php

namespace JutForm\Core;

class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $server,
        private array $headers,
        private ?string $rawBody,
    ) {
    }

    /**
     * Build a request without relying on php://input (for CLI integration tests).
     *
     * @param array<string, string> $headers Header name => value
     * @param array<string, mixed> $server Extra $_SERVER keys
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        ?string $rawBody = null,
        array $headers = [],
        array $server = [],
    ): self {
        $path = $path === '' ? '/' : (str_starts_with($path, '/') ? $path : '/' . $path);
        $queryString = $query === [] ? '' : '?' . http_build_query($query);
        $uri = $path . $queryString;
        $server = array_merge([
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], $server);
        return new self(
            strtoupper($method),
            $path,
            $query,
            [],
            $server,
            $headers,
            $rawBody,
        );
    }

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = getallheaders() ?: [];
        } else {
            foreach ($_SERVER as $k => $v) {
                if (str_starts_with($k, 'HTTP_')) {
                    $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
                    $headers[$name] = $v;
                }
            }
        }
        $raw = file_get_contents('php://input');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
            $_SERVER,
            $headers,
            $raw === false ? null : $raw,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path === '' ? '/' : $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function body(): ?string
    {
        return $this->rawBody;
    }

    public function jsonBody(): array
    {
        if ($this->rawBody === null || $this->rawBody === '') {
            return [];
        }
        $decoded = json_decode($this->rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name, ?string $default = null): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return is_array($v) ? ($v[0] ?? $default) : $v;
            }
        }
        return $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $_COOKIE[$name] ?? $default;
    }
}
