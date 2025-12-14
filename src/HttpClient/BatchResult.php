<?php

declare(strict_types=1);

namespace Brd6\TinybirdSdk\HttpClient;

use Brd6\TinybirdSdk\Exception\TinybirdException;

/**
 * @template T
 */
final class BatchResult
{
    /**
     * @param T|null $data
     */
    private function __construct(
        private readonly bool $success,
        private readonly mixed $data = null,
        private readonly ?TinybirdException $exception = null,
    ) {
    }

    /**
     * @template TData
     * @param TData $data
     * @return self<TData>
     */
    public static function success(mixed $data): self
    {
        return new self(true, $data);
    }

    /**
     * @template TFail
     * @psalm-return self<TFail>
     * @phpstan-return self<TFail>
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     * @phpstan-ignore-next-line Template type TFail is not referenced in parameter (by design for Result type)
     */
    public static function failure(TinybirdException $exception): self
    {
        /** @var self<TFail> */
        return new self(false, null, $exception);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * @return T
     * @throws TinybirdException if the result is a failure
     *
     * @phpstan-return T
     * @phpstan-impure
     */
    public function getData(): mixed
    {
        if (!$this->success && $this->exception !== null) {
            throw $this->exception;
        }

        /** @var T */
        return $this->data;
    }

    /**
     * @return T|null
     */
    public function getDataOrNull(): mixed
    {
        return $this->success ? $this->data : null;
    }

    public function getException(): ?TinybirdException
    {
        return $this->exception;
    }
}
