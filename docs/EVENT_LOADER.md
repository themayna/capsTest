# Event Loading Mechanism Design

## Overview

This system collects events from multiple remote sources (e.g., landing pages) into centralized storage. It supports multiple loader instances running in parallel on different servers without conflicts.

## Quick Start

```bash
# Install PHPUnit for testing
composer require --dev phpunit/phpunit

# Run tests
./vendor/bin/phpunit
```

## Project Structure

```
src/EventLoader/
├── Event.php                  # Event DTO (id, type, payload)
├── EventLoader.php            # Main loader implementation ✓
├── EventSourceInterface.php   # Interface for fetching events
├── EventSourceException.php   # Exception for source errors
├── EventStorageInterface.php  # Interface for storing events
└── SourceLockInterface.php    # Interface for distributed locking

tests/Unit/EventLoader/
└── EventLoaderTest.php        # Unit tests for EventLoader
```

## Interfaces

### 1. EventSourceInterface
Retrieves events from a remote source (e.g., landing page API).

```php
interface EventSourceInterface
{
    public function getName(): string;

    /**
     * @return Event[] Events with id > $afterId, sorted ascending, max 1000
     * @throws EventSourceException
     */
    public function fetch(int $afterId): array;
}
```

**Example implementation would call:**
```
GET https://landing-page1.com/api/events?after_id=123

Response:
[
  {"id": 124, "type": "lead_created", "payload": {"name": "John", "phone": "123"}},
  {"id": 125, "type": "lead_created", "payload": {"name": "Brian", "phone": "456"}}
]
```

### 2. EventStorageInterface
Stores events in a persistent database.

```php
interface EventStorageInterface
{
    /**
     * Stores events and updates cursor atomically.
     */
    public function store(string $sourceName, array $events, int $lastEventId): void;

    /**
     * Returns last processed event ID (cursor position).
     */
    public function getLastEventId(string $sourceName): int;
}
```

### 3. SourceLockInterface
Distributed lock for coordinating parallel instances.

```php
interface SourceLockInterface
{
    public const int MIN_INTERVAL_MS = 200;

    /**
     * Acquires lock if available AND 200ms has passed since last request.
     */
    public function acquire(string $sourceName): bool;

    /**
     * Releases lock and records timestamp for rate limiting.
     */
    public function release(string $sourceName): void;
}
```

## How It Works

### Event Loading Flow

```
┌─────────────────────────────────────────────────────────────┐
│                      EventLoader.run()                       │
│                                                              │
│  while (true) {                                              │
│      foreach (source in sources) {        // Round-robin    │
│          if (lock.acquire(source)) {      // Distributed    │
│              try {                                           │
│                  afterId = storage.getLastEventId(source)   │
│                  events = source.fetch(afterId)             │
│                  storage.store(source, events, lastId)      │
│              } catch (EventSourceException) {               │
│                  log("source unavailable")  // Skip & log   │
│              } finally {                                     │
│                  lock.release(source)                       │
│              }                                               │
│          }                                                   │
│      }                                                       │
│  }                                                           │
└─────────────────────────────────────────────────────────────┘
```

### Conflict Prevention

The system prevents the same event from being requested twice through:

1. **Cursor Tracking**: Each source has a cursor (last processed event ID). Events are fetched with `id > cursor`, so already-processed events are never re-requested.

2. **Distributed Locking**: Only one loader instance can process a source at a time. If another instance holds the lock, the source is skipped.

3. **200ms Rate Limiting**: The lock enforces a minimum 200ms interval between requests to the same source, regardless of which instance makes the request.

### Error Handling

- **Source unavailable**: Log error, skip source, continue to next source
- **Lock not available**: Skip source (another instance is processing it)
- **Lock always released**: Uses `try/finally` to ensure lock release even on errors

## Running Multiple Instances

```bash
# Server 1
php bin/console app:event-loader:run

# Server 2
php bin/console app:event-loader:run

# Server 3
php bin/console app:event-loader:run
```

All instances coordinate via the distributed lock. They will naturally distribute work across sources.

## Implementation Notes

### SourceLockInterface Implementation (Redis example)

```php
class RedisSourceLock implements SourceLockInterface
{
    public function acquire(string $sourceName): bool
    {
        $key = "event_loader:lock:{$sourceName}";
        $timestampKey = "event_loader:last_request:{$sourceName}";

        // Check 200ms interval
        $lastRequest = $this->redis->get($timestampKey);
        if ($lastRequest && (microtime(true) - $lastRequest) < 0.2) {
            return false;
        }

        // Try to acquire exclusive lock with TTL
        return $this->redis->set($key, '1', ['NX', 'PX' => 5000]);
    }

    public function release(string $sourceName): void
    {
        $key = "event_loader:lock:{$sourceName}";
        $timestampKey = "event_loader:last_request:{$sourceName}";

        // Record timestamp for rate limiting
        $this->redis->set($timestampKey, microtime(true));

        // Release lock
        $this->redis->del($key);
    }
}
```

### EventSourceInterface Implementation (HTTP example)

```php
class HttpEventSource implements EventSourceInterface
{
    public function __construct(
        private string $name,
        private string $endpoint,
        private HttpClientInterface $http,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function fetch(int $afterId): array
    {
        try {
            $response = $this->http->request('GET', $this->endpoint, [
                'query' => ['after_id' => $afterId],
                'timeout' => 5,
            ]);

            $data = $response->toArray();

            return array_map(
                fn($item) => new Event($item['id'], $item['type'], $item['payload']),
                $data
            );
        } catch (\Throwable $e) {
            throw EventSourceException::unavailable($this->name, $e);
        }
    }
}
```

## Tests

The test suite covers:

- ✅ Fetches events and stores them with correct cursor
- ✅ Skips source when lock not available
- ✅ Handles source unavailable gracefully (logs, continues)
- ✅ Always releases lock, even on errors
- ✅ Processes multiple sources in round-robin
- ✅ Skips storage when no new events
- ✅ Fetches events after last stored ID
- ✅ Continues processing when one source fails

Run tests:
```bash
./vendor/bin/phpunit
```
