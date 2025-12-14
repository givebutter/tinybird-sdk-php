<?php

declare(strict_types=1);

namespace Brd6\Test\TinybirdSdk\HttpClient;

use Brd6\Test\TinybirdSdk\TestCase;
use Brd6\TinybirdSdk\HttpClient\BatchRequestItem;

class BatchRequestItemTest extends TestCase
{
    public function testConstructor(): void
    {
        $item = new BatchRequestItem(
            'POST',
            '/v0/test',
            ['q' => '1'],
            ['data' => 'value'],
            ['X-Custom' => 'header'],
        );

        $this->assertSame('POST', $item->method);
        $this->assertSame('/v0/test', $item->path);
        $this->assertSame(['q' => '1'], $item->query);
        $this->assertSame(['data' => 'value'], $item->body);
        $this->assertSame(['X-Custom' => 'header'], $item->headers);
    }

    public function testGetFactory(): void
    {
        $item = BatchRequestItem::get('/v0/pipes/my_pipe.json');

        $this->assertSame('GET', $item->method);
        $this->assertSame('/v0/pipes/my_pipe.json', $item->path);
        $this->assertSame([], $item->query);
        $this->assertNull($item->body);
        $this->assertSame([], $item->headers);
    }

    public function testGetFactoryWithQuery(): void
    {
        $item = BatchRequestItem::get('/v0/pipes/my_pipe.json', ['date' => '2025-01-01']);

        $this->assertSame('GET', $item->method);
        $this->assertSame(['date' => '2025-01-01'], $item->query);
    }

    public function testPostFactory(): void
    {
        $item = BatchRequestItem::post('/v0/events');

        $this->assertSame('POST', $item->method);
        $this->assertSame('/v0/events', $item->path);
        $this->assertNull($item->body);
    }

    public function testPostFactoryWithArrayBody(): void
    {
        $body = ['event' => 'click'];
        $item = BatchRequestItem::post('/v0/events', $body);

        $this->assertSame(['event' => 'click'], $item->body);
    }

    public function testPostFactoryWithStringBody(): void
    {
        $item = BatchRequestItem::post('/v0/events', '{"ndjson":"line"}');

        $this->assertSame('{"ndjson":"line"}', $item->body);
    }

    public function testPostFactoryWithAllParams(): void
    {
        $item = BatchRequestItem::post(
            '/v0/events',
            ['data' => 1],
            ['name' => 'events_table'],
            ['Content-Type' => 'application/json'],
        );

        $this->assertSame('POST', $item->method);
        $this->assertSame('/v0/events', $item->path);
        $this->assertSame(['name' => 'events_table'], $item->query);
        $this->assertSame(['data' => 1], $item->body);
        $this->assertSame(['Content-Type' => 'application/json'], $item->headers);
    }
}
