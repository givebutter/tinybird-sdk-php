<?php

declare(strict_types=1);

namespace Brd6\TinybirdSdk\HttpClient;

use Brd6\TinybirdSdk\ClientOptions;
use Brd6\TinybirdSdk\Constant\HttpStatusCode;
use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\Exception\RequestTimeoutException;
use Brd6\TinybirdSdk\Exception\TinybirdException;
use Brd6\TinybirdSdk\Util\UrlHelper;
use Http\Client\Common\HttpMethodsClientInterface;
use Http\Client\Exception as HttpClientException;
use Http\Client\Exception\HttpException;
use Http\Client\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

use function count;
use function in_array;
use function is_array;
use function json_encode;
use function ltrim;
use function str_contains;
use function usleep;

use const JSON_THROW_ON_ERROR;

class HttpRequestHandler
{
    private const MS_TO_MICROSECONDS = 1000;
    private const RETRY_AFTER_HEADER = 'retry-after';

    private HttpMethodsClientInterface $httpClient;
    private HttpClientFactory $httpClientFactory;
    private ResponseParser $responseParser;
    private string $apiVersion;
    private int $retryMaxRetries;
    private int $retryDelayMs;
    private int $retryBackoffMultiplier;

    public function __construct(ClientOptions $options, ?HttpClientFactory $httpClientFactory = null)
    {
        $this->httpClientFactory = $httpClientFactory ?? new HttpClientFactory();
        $this->httpClient = $this->httpClientFactory->create($options);
        $this->responseParser = new ResponseParser($options->getToken());
        $this->apiVersion = $options->getApiVersion();
        $this->retryMaxRetries = $options->getRetryMaxRetries();
        $this->retryDelayMs = $options->getRetryDelayMs();
        $this->retryBackoffMultiplier = $options->getRetryBackoffMultiplier();
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        array|string|null $body = null,
        array $headers = [],
    ): array {
        $path = $this->buildPath($path, $query);
        $bodyContent = $this->prepareBody($body, $headers);

        return $this->executeWithRetry(
            fn () => $this->httpClient->send($method, $path, $headers, $bodyContent),
        );
    }

    /**
     * @param array<int|string, BatchRequestItem> $requests
     * @return array<int|string, BatchResult<array<string, mixed>>>
     */
    public function batchRequest(array $requests): array
    {
        $asyncClient = $this->httpClientFactory->getAsyncClient();

        if ($asyncClient === null) {
            return $this->executeSequentially($requests);
        }

        $preparedRequests = $this->prepareBatchRequests($requests);

        $executor = new BatchRequestExecutor(
            $asyncClient,
            $this->httpClientFactory->getRequestFactory(),
            $this->httpClientFactory->getStreamFactory(),
            $this->responseParser,
        );

        $promises = $executor->dispatchAll($preparedRequests);

        return $executor->waitAll($promises);
    }

    /**
     * @param array<int|string, BatchRequestItem> $requests
     * @return array<int|string, PreparedBatchRequest>
     */
    private function prepareBatchRequests(array $requests): array
    {
        $prepared = [];

        foreach ($requests as $key => $request) {
            $headers = $request->headers;

            $prepared[$key] = new PreparedBatchRequest(
                $request->method,
                $this->buildPath($request->path, $request->query),
                $headers,
                $this->prepareBody($request->body, $headers),
            );
        }

        return $prepared;
    }

    /**
     * @param array<int|string, BatchRequestItem> $requests
     * @return array<int|string, BatchResult<array<string, mixed>>>
     */
    private function executeSequentially(array $requests): array
    {
        $results = [];

        foreach ($requests as $key => $request) {
            try {
                $data = $this->request(
                    $request->method,
                    $request->path,
                    $request->query,
                    $request->body,
                    $request->headers,
                );
                $results[$key] = BatchResult::success($data);
            } catch (TinybirdException $e) {
                $results[$key] = BatchResult::failure($e);
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function executeWithRetry(callable $httpCall): array
    {
        $delay = $this->retryDelayMs;

        for ($attempt = 0; $attempt < $this->retryMaxRetries; ++$attempt) {
            try {
                $response = $this->sendRequest($httpCall);

                if ($this->responseParser->isSuccess($response)) {
                    return $this->responseParser->parseBody($response);
                }

                if ($this->shouldRetry($response->getStatusCode(), $attempt)) {
                    $delay = $this->waitAndGetNextDelay($delay, $response);
                    continue;
                }

                throw $this->responseParser->createException($response);
            } catch (HttpClientException $e) {
                if ($this->canRetry($attempt)) {
                    $delay = $this->waitAndGetNextDelay($delay);
                    continue;
                }

                throw new RequestTimeoutException($e->getMessage());
            }
        }

        throw new ApiException(0, [], [], 'Max retries exceeded');
    }

    private function sendRequest(callable $httpCall): ResponseInterface
    {
        try {
            return $httpCall();
        } catch (RequestException $e) {
            if ($e instanceof HttpException) {
                throw $this->responseParser->createException($e->getResponse());
            }
            throw new RequestTimeoutException();
        }
    }

    private function shouldRetry(int $statusCode, int $attempt): bool
    {
        return $this->canRetry($attempt)
            && in_array($statusCode, HttpStatusCode::RETRYABLE_STATUS_CODES, true);
    }

    private function canRetry(int $attempt): bool
    {
        return $attempt < $this->retryMaxRetries - 1;
    }

    private function waitAndGetNextDelay(int $currentDelay, ?ResponseInterface $response = null): int
    {
        $nextDelay = $response !== null
            ? $this->calculateDelayFromResponse($currentDelay, $response)
            : $currentDelay * $this->retryBackoffMultiplier;

        usleep($nextDelay * self::MS_TO_MICROSECONDS);

        return $nextDelay;
    }

    private function calculateDelayFromResponse(int $currentDelay, ResponseInterface $response): int
    {
        $retryAfter = $this->getRetryAfterHeader($response);

        return $retryAfter > 0
            ? $retryAfter * self::MS_TO_MICROSECONDS
            : $currentDelay * $this->retryBackoffMultiplier;
    }

    private function getRetryAfterHeader(ResponseInterface $response): int
    {
        foreach ($response->getHeaders() as $name => $values) {
            if (strtolower((string) $name) === self::RETRY_AFTER_HEADER) {
                return (int) ($values[0] ?? 0);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildPath(string $path, array $query): string
    {
        $path = '/' . $this->apiVersion . '/' . ltrim($path, '/');

        if (count($query) === 0) {
            return $path;
        }

        $separator = str_contains($path, '?') ? '&' : '?';

        return $path . $separator . UrlHelper::buildQuery($query);
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param array<string, string> $headers
     */
    private function prepareBody(array|string|null $body, array &$headers): ?string
    {
        if ($body === null || $body === '' || (is_array($body) && count($body) === 0)) {
            return null;
        }

        if (is_string($body)) {
            return $body;
        }

        $headers['Content-Type'] = 'application/json';

        return json_encode($body, JSON_THROW_ON_ERROR);
    }
}
