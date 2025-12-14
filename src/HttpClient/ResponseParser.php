<?php

declare(strict_types=1);

namespace Brd6\TinybirdSdk\HttpClient;

use Brd6\TinybirdSdk\Constant\HttpStatusCode;
use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\Exception\AuthenticationException;
use Brd6\TinybirdSdk\Exception\RateLimitException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

use function in_array;
use function json_decode;
use function json_last_error;
use function sprintf;

use const JSON_ERROR_NONE;

/**
 * @internal
 */
final class ResponseParser
{
    public function __construct(
        private readonly string $token = '',
    ) {
    }

    public function isSuccess(ResponseInterface $response): bool
    {
        return $response->getStatusCode() < HttpStatusCode::MULTIPLE_CHOICES;
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(ResponseInterface $response): array
    {
        if (!$this->isSuccess($response)) {
            throw $this->createException($response);
        }

        return $this->parseBody($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function parseBody(ResponseInterface $response): array
    {
        $contents = $response->getBody()->getContents();

        if ($contents === '') {
            return [];
        }

        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                sprintf('Unable to parse response body into JSON: %s', json_last_error()),
            );
        }

        return $data;
    }

    public function createException(ResponseInterface $response): ApiException
    {
        $statusCode = $response->getStatusCode();
        $headers = $response->getHeaders();
        $rawData = $this->parseSafely($response);
        $message = $rawData['error'] ?? '';

        if (in_array($statusCode, HttpStatusCode::AUTHENTICATION_ERROR_STATUS_CODES, true)) {
            return new AuthenticationException($statusCode, $headers, $rawData, $message, $this->token);
        }

        if ($statusCode === HttpStatusCode::TOO_MANY_REQUESTS) {
            return new RateLimitException($statusCode, $headers, $rawData, $message);
        }

        return new ApiException($statusCode, $headers, $rawData, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSafely(ResponseInterface $response): array
    {
        try {
            return $this->parseBody($response);
        } catch (RuntimeException) {
            return [];
        }
    }
}
