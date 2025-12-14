<?php

declare(strict_types=1);

namespace Brd6\Test\TinybirdSdk\Mock;

use Http\Client\HttpAsyncClient;
use Psr\Http\Client\ClientInterface;

interface MockAsyncHttpClient extends ClientInterface, HttpAsyncClient
{
}
