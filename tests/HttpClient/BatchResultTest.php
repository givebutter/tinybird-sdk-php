<?php

declare(strict_types=1);

namespace Brd6\Test\TinybirdSdk\HttpClient;

use Brd6\Test\TinybirdSdk\TestCase;
use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\HttpClient\BatchResult;

class BatchResultTest extends TestCase
{
    public function testSuccessResult(): void
    {
        $data = ['foo' => 'bar'];
        $result = BatchResult::success($data);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame($data, $result->getData());
        $this->assertSame($data, $result->getDataOrNull());
        $this->assertNull($result->getException());
    }

    public function testFailureResult(): void
    {
        $exception = new ApiException(400, [], [], 'Test error');
        /** @var BatchResult<array<string, string>> $result */
        $result = BatchResult::failure($exception);

        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isFailure());
        $this->assertNull($result->getDataOrNull());
        $this->assertSame($exception, $result->getException());
    }

    public function testGetDataOnFailureThrows(): void
    {
        $exception = new ApiException(400, [], [], 'Test error');
        /** @var BatchResult<array<string, string>> $result */
        $result = BatchResult::failure($exception);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Test error');

        $result->getData();
    }

    public function testSuccessWithNullData(): void
    {
        $result = BatchResult::success(null);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getData());
        $this->assertNull($result->getDataOrNull());
    }

    public function testSuccessWithScalarData(): void
    {
        $result = BatchResult::success(42);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(42, $result->getData());
    }

    public function testSuccessWithObjectData(): void
    {
        $object = new \stdClass();
        $object->name = 'test';
        $result = BatchResult::success($object);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($object, $result->getData());
    }
}
