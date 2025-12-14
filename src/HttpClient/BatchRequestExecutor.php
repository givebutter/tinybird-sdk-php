<?php

declare(strict_types=1);

namespace Brd6\TinybirdSdk\HttpClient;

use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\Exception\RequestTimeoutException;
use Http\Client\Exception as HttpClientException;
use Http\Client\HttpAsyncClient;
use Http\Promise\Promise;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @internal
 */
final class BatchRequestExecutor
{
    public function __construct(
        private readonly HttpAsyncClient $asyncClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly ResponseParser $responseParser,
    ) {
    }

    /**
     * @param array<int|string, PreparedBatchRequest> $requests
     * @return array<int|string, Promise>
     */
    public function dispatchAll(array $requests): array
    {
        $promises = [];

        foreach ($requests as $key => $request) {
            $promises[$key] = $this->dispatch($request);
        }

        return $promises;
    }

    /**
     * Wait for all promises and return results. Each result is wrapped in BatchResult
     * to isolate failures - one failed request won't affect others.
     *
     * @param array<int|string, Promise> $promises
     * @return array<int|string, BatchResult<array<string, mixed>>>
     */
    public function waitAll(array $promises): array
    {
        $results = [];

        foreach ($promises as $key => $promise) {
            $results[$key] = $this->resolvePromise($promise);
        }

        return $results;
    }

    /**
     * @return BatchResult<array<string, mixed>>
     */
    private function resolvePromise(Promise $promise): BatchResult
    {
        try {
            $response = $promise->wait();

            return BatchResult::success($this->responseParser->parse($response));
        } catch (ApiException $e) {
            return BatchResult::failure($e);
        } catch (HttpClientException $e) {
            return BatchResult::failure(new RequestTimeoutException($e->getMessage()));
        }
    }

    private function dispatch(PreparedBatchRequest $request): Promise
    {
        $psrRequest = $this->requestFactory->createRequest($request->method, $request->path);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->body !== null && $this->streamFactory !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        return $this->asyncClient->sendAsyncRequest($psrRequest);
    }
}
