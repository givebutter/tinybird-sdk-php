# Batch Queries Example

Demonstrates concurrent query execution using `batchSql()` and `batchQuery()` for improved performance when running multiple independent queries.

## Features Covered

| Method | Description |
|--------|-------------|
| `batchSql()` | Execute multiple SQL queries concurrently |
| `batchQuery()` | Query multiple Pipe endpoints concurrently |
| `BatchResult` | Handle success/failure for individual queries |

## Setup

1. Copy environment file:

```bash
cp env.example .env
```

2. Edit `.env` with your Tinybird token:

```bash
TINYBIRD_TOKEN=p.eyJ...
TINYBIRD_LOCAL=false
```

3. Install dependencies:

```bash
composer install
```

4. Run the example:

```bash
php index.php
```

## Usage Examples

### Batch SQL Queries

Execute multiple SQL queries in parallel:

```php
$results = $client->query()->batchSql([
    'total_users' => 'SELECT count() FROM users',
    'active_today' => 'SELECT count() FROM events WHERE date = today()',
    'revenue' => 'SELECT sum(amount) FROM orders',
]);

// Process results
foreach ($results as $key => $result) {
    if ($result->isSuccess()) {
        $data = $result->getData();
        echo "{$key}: {$data->rows} rows";
    } else {
        echo "{$key} failed: " . $result->getException()->getMessage();
    }
}
```

### Batch Pipe Queries

Query multiple Pipe endpoints concurrently:

```php
// Simple: key is pipe name
$results = $client->pipes()->batchQuery([
    'user_analytics' => ['date' => '2025-01-01'],
    'event_counts' => ['type' => 'click'],
]);

// Query same pipe multiple times using # alias
$results = $client->pipes()->batchQuery([
    'user_analytics#jan' => ['date' => '2025-01-01'],
    'user_analytics#feb' => ['date' => '2025-02-01'],
    'user_analytics#mar' => ['date' => '2025-03-01'],
]);

foreach ($results as $key => $result) {
    if ($result->isSuccess()) {
        $data = $result->getData();
        echo "{$key}: {$data->rows} rows";
    }
}
```

### Error Isolation

Individual query failures don't affect other queries:

```php
$results = $client->query()->batchSql([
    'valid' => 'SELECT 1',
    'invalid' => 'SELECT * FROM nonexistent_table',
    'also_valid' => 'SELECT 2',
]);

// 'valid' and 'also_valid' succeed even though 'invalid' fails
$results['valid']->isSuccess();      // true
$results['invalid']->isFailure();    // true
$results['also_valid']->isSuccess(); // true
```

### Accessing Results

```php
// Safe access (returns null on failure)
$data = $results['key']->getDataOrNull();
$rows = $data?->rows ?? 0;

// Direct access (throws exception on failure)
try {
    $data = $results['key']->getData();
    $rows = $data->rows;
} catch (TinybirdException $e) {
    // Handle error
}

// Check status first
if ($results['key']->isSuccess()) {
    $data = $results['key']->getData();
}

// Get the exception
if ($results['key']->isFailure()) {
    $exception = $results['key']->getException();
}
```

## BatchResult API

| Method | Description |
|--------|-------------|
| `isSuccess()` | Returns `true` if query succeeded |
| `isFailure()` | Returns `true` if query failed |
| `getData()` | Returns `QueryResult`, throws on failure |
| `getDataOrNull()` | Returns `QueryResult` or `null` on failure |
| `getException()` | Returns the exception or `null` on success |

## Performance Benefits

Batch queries are especially useful for:

- **Dashboards**: Load multiple metrics concurrently
- **Reports**: Generate multiple aggregations in parallel
- **Comparisons**: Query different time periods simultaneously

```php
// Dashboard example: 5 queries, ~100ms each
// Sequential: ~500ms total
// Batch: ~100ms total (5x faster)

$results = $client->query()->batchSql([
    'total_users' => 'SELECT count() FROM users',
    'new_users' => 'SELECT count() FROM users WHERE created_at > today()',
    'active_users' => 'SELECT count() FROM events WHERE date = today()',
    'revenue' => 'SELECT sum(amount) FROM orders WHERE date = today()',
    'avg_order' => 'SELECT avg(amount) FROM orders WHERE date = today()',
]);
```

## Async Support

For concurrent executions, use an HTTP client that supports async requests (like Symfony HTTP Client with `HttplugClient`):

```bash
composer require symfony/http-client nyholm/psr7 guzzlehttp/promises
```

The SDK automatically detects async support and executes queries in parallel.


