<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Brd6\TinybirdSdk\Client;
use Brd6\TinybirdSdk\Enum\Region;
use Brd6\TinybirdSdk\Exception\ApiException;
use Brd6\TinybirdSdk\Exception\AuthenticationException;
use Dotenv\Dotenv;

function loadEnv(): void
{
    if (!file_exists(__DIR__ . '/.env')) {
        exit("Error: .env file not found. Run: cp env.example .env\n");
    }
    Dotenv::createImmutable(__DIR__)->load();
    if (empty($_ENV['TINYBIRD_TOKEN'])) {
        exit("Error: TINYBIRD_TOKEN required.\n");
    }
}

function createClient(): Client
{
    $isLocal = in_array(strtolower($_ENV['TINYBIRD_LOCAL'] ?? ''), ['true', '1', 'yes'], true);
    if ($isLocal) {
        $port = (int) ($_ENV['TINYBIRD_PORT'] ?? 7181);
        echo "[INFO] Connecting to Tinybird Local (localhost:$port)\n";
        return Client::local($_ENV['TINYBIRD_TOKEN'], $port);
    }
    $region = Region::tryFrom($_ENV['TINYBIRD_REGION'] ?? '') ?? Region::GCP_EUROPE_WEST3;
    echo "[INFO] Connecting to Tinybird Cloud ($region->value)\n";
    return Client::forRegion($_ENV['TINYBIRD_TOKEN'], $region);
}

function main(): void
{
    echo "\n=== Tinybird SDK - Batch Queries Examples ===\n\n";

    try {
        loadEnv();
        $client = createClient();

        // 1. Batch SQL Queries
        echo "\n--- batchSql() ---\n";
        echo "Running multiple SQL queries concurrently...\n\n";

        $startTime = microtime(true);
        $results = $client->query()->batchSql([
            'datasources_count' => 'SELECT count() as total FROM tinybird.datasources_ops_log',
            'pipes_count' => 'SELECT count() as total FROM tinybird.pipe_stats',
            'recent_queries' => 'SELECT count() as total FROM tinybird.pipe_stats WHERE start_datetime > now() - interval 1 hour',
        ]);
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        echo "Completed 3 queries in {$totalTime}ms\n\n";

        foreach ($results as $key => $result) {
            if ($result->isSuccess()) {
                $data = $result->getData();
                $value = $data->data[0]['total'] ?? 'N/A';
                echo "  {$key}: {$value} (elapsed: {$data->getElapsedTime()}s)\n";
            } else {
                $error = $result->getException();
                echo "  {$key}: FAILED - " . ($error?->getMessage() ?? 'Unknown error') . "\n";
            }
        }

        // 2. Batch Pipe Queries
        echo "\n--- batchQuery() ---\n";

        // Find published pipe endpoints
        $pipes = $client->pipes()->list();
        $endpoints = array_filter($pipes, fn($p) => $p->isApiEndpoint());

        if (count($endpoints) >= 1) {
            $firstEndpoint = array_values($endpoints)[0]->name;

            echo "Querying same pipe multiple times using # alias...\n\n";

            // Query same pipe multiple times using # alias
            $startTime = microtime(true);
            $results = $client->pipes()->batchQuery([
                "{$firstEndpoint}#q1" => [],
                "{$firstEndpoint}#q2" => [],
                "{$firstEndpoint}#q3" => [],
            ]);
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);

            echo "Completed 3 queries to '{$firstEndpoint}' in {$totalTime}ms\n\n";

            foreach ($results as $key => $result) {
                if ($result->isSuccess()) {
                    $data = $result->getData();
                    echo "  {$key}: {$data->rows} rows (elapsed: {$data->getElapsedTime()}s)\n";
                } else {
                    echo "  {$key}: FAILED - " . ($result->getException()?->getMessage() ?? 'Unknown') . "\n";
                }
            }
        } else {
            echo "[INFO] No published pipe endpoints found. Skipping batchQuery demo.\n";
        }

        // 3. Error Isolation Demo
        echo "\n--- Error Isolation ---\n";
        echo "Demonstrating that one failed query doesn't affect others...\n\n";

        $results = $client->query()->batchSql([
            'valid_query' => 'SELECT 1 as result',
            'invalid_query' => 'SELECT * FROM this_table_does_not_exist_xyz',
            'another_valid' => 'SELECT 2 as result',
        ]);

        foreach ($results as $key => $result) {
            if ($result->isSuccess()) {
                echo "  ✓ {$key}: Success\n";
            } else {
                echo "  ✗ {$key}: Failed (isolated, other queries unaffected)\n";
            }
        }

        // 4. Access data directly
        echo "\n--- Accessing Results ---\n";

        $results = $client->query()->batchSql([
            'count' => 'SELECT 42 as answer',
        ]);

        // Safe access with null coalescing
        $answer = $results['count']->getDataOrNull()?->data[0]['answer'] ?? 'N/A';
        echo "  Answer (safe): {$answer}\n";

        // Direct access (throws on failure)
        try {
            $answer = $results['count']->getData()->data[0]['answer'];
            echo "  Answer (direct): {$answer}\n";
        } catch (ApiException $e) {
            echo "  Failed to get answer: {$e->getMessage()}\n";
        }

        echo "\n=== Done! ===\n";
    } catch (AuthenticationException $e) {
        exit("[ERROR] Authentication: " . $e->getMessage() . "\n");
    } catch (ApiException $e) {
        exit("[ERROR] API: " . $e->getMessage() . "\n");
    }
}

main();

