<?php

declare(strict_types=1);

namespace Brd6\TinybirdSdk\HttpClient;

final class BatchRequestItem
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array|string|null $body = null,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function get(string $path, array $query = []): self
    {
        return new self('GET', $path, $query);
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public static function post(
        string $path,
        array|string|null $body = null,
        array $query = [],
        array $headers = [],
    ): self {
        return new self('POST', $path, $query, $body, $headers);
    }
}
