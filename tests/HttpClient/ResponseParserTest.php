<?php

declare(strict_types=1);

namespace Brd6\Test\TinybirdSdk\HttpClient;

use Brd6\Test\TinybirdSdk\TestCase;
use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\Exception\AuthenticationException;
use Brd6\TinybirdSdk\Exception\RateLimitException;
use Brd6\TinybirdSdk\HttpClient\ResponseParser;
use Nyholm\Psr7\Response;
use RuntimeException;

class ResponseParserTest extends TestCase
{
    public function testIsSuccessWithOkStatus(): void
    {
        $parser = new ResponseParser();
        $response = new Response(200);

        $this->assertTrue($parser->isSuccess($response));
    }

    public function testIsSuccessWithCreatedStatus(): void
    {
        $parser = new ResponseParser();
        $response = new Response(201);

        $this->assertTrue($parser->isSuccess($response));
    }

    public function testIsSuccessWithNoContentStatus(): void
    {
        $parser = new ResponseParser();
        $response = new Response(204);

        $this->assertTrue($parser->isSuccess($response));
    }

    public function testIsSuccessWithErrorStatus(): void
    {
        $parser = new ResponseParser();
        $response = new Response(400);

        $this->assertFalse($parser->isSuccess($response));
    }

    public function testParseSuccessfulResponse(): void
    {
        $parser = new ResponseParser();
        $response = new Response(200, [], '{"data": "test"}');

        $result = $parser->parse($response);

        $this->assertSame(['data' => 'test'], $result);
    }

    public function testParseEmptyBody(): void
    {
        $parser = new ResponseParser();
        $response = new Response(204, [], '');

        $result = $parser->parse($response);

        $this->assertSame([], $result);
    }

    public function testParseThrowsOnErrorResponse(): void
    {
        $parser = new ResponseParser();
        $response = new Response(400, [], '{"error": "Bad request"}');

        $this->expectException(ApiException::class);
        $parser->parse($response);
    }

    public function testParseBodyWithValidJson(): void
    {
        $parser = new ResponseParser();
        $response = new Response(200, [], '{"nested": {"key": "value"}}');

        $result = $parser->parseBody($response);

        $this->assertSame(['nested' => ['key' => 'value']], $result);
    }

    public function testParseBodyWithInvalidJson(): void
    {
        $parser = new ResponseParser();
        $response = new Response(200, [], 'not-json');

        $this->expectException(RuntimeException::class);
        $parser->parseBody($response);
    }

    public function testCreateExceptionForBadRequest(): void
    {
        $parser = new ResponseParser();
        $response = new Response(400, [], '{"error": "Invalid params"}');

        $exception = $parser->createException($response);

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertNotInstanceOf(AuthenticationException::class, $exception);
        $this->assertSame(400, $exception->getCode());
        $this->assertSame('Invalid params', $exception->getMessage());
    }

    public function testCreateExceptionForUnauthorized(): void
    {
        $parser = new ResponseParser('my-token');
        $response = new Response(401, [], '{"error": "Unauthorized"}');

        $exception = $parser->createException($response);

        $this->assertInstanceOf(AuthenticationException::class, $exception);
        $this->assertSame(401, $exception->getCode());
    }

    public function testCreateExceptionForForbidden(): void
    {
        $parser = new ResponseParser();
        $response = new Response(403, [], '{"error": "Forbidden"}');

        $exception = $parser->createException($response);

        $this->assertInstanceOf(AuthenticationException::class, $exception);
        $this->assertSame(403, $exception->getCode());
    }

    public function testCreateExceptionForRateLimit(): void
    {
        $parser = new ResponseParser();
        $response = new Response(429, [], '{"error": "Rate limited"}');

        $exception = $parser->createException($response);

        $this->assertInstanceOf(RateLimitException::class, $exception);
        $this->assertSame(429, $exception->getCode());
    }

    public function testCreateExceptionWithInvalidJsonBody(): void
    {
        $parser = new ResponseParser();
        $response = new Response(500, [], 'Internal Server Error');

        $exception = $parser->createException($response);

        $this->assertInstanceOf(ApiException::class, $exception);
        $this->assertSame(500, $exception->getCode());
        $this->assertSame('Request to Tinybird API failed with status: 500', $exception->getMessage());
    }

    public function testCreateExceptionPreservesHeaders(): void
    {
        $parser = new ResponseParser();
        $response = new Response(400, ['X-Request-Id' => 'abc123'], '{"error": "test"}');

        $exception = $parser->createException($response);

        $headers = $exception->getHeaders();
        $this->assertArrayHasKey('X-Request-Id', $headers);
    }
}
