# capsTest Overview

This project is a Symfony-based worker that collects domain events from external sources and persists them in a local Doctrine-managed store. The ingestion loop is designed to run continuously, ensuring that every configured source is polled, throttled, and processed safely.

## Core Concepts

### Event Sources
- Defined by the `EventSource` Doctrine entity (`src/Entity/EventSource.php`).
- Each record references a `Source` entity (`src/Entity/Source.php`) and contains the type (`http`, `csv`, …) plus configuration payload.
- Concrete adapters live in `src/EventSource/`, such as `HttpEventSource` and `CsvEventSource`, both implementing `App\src\Contract\EventSourceInterface`.

### Events and Storage
- Persisted events are represented by `src/Entity/Event.php`.
- `EventStorage` (`src/Service/EventStorage.php`) handles saving new events and tracking the latest processed event ID per source.

### Orchestration Services
- `EventLoader` (`src/Service/EventLoader.php`) is the main loop. It fetches all `EventSource` records, instantiates the correct adapter via `EventSourceFactory`, and delegates processing.
- `EventSourceLoader` (`src/Service/EventSourceLoader.php`) protects each source with a Symfony lock and rate limiter before handing control to the processor.
- `EventFetcher` (`src/Service/EventFetcher.php`) orchestrates the fetch/persist workflow: loading the last event ID, recording rate-limit usage, fetching new events from the adapter, and saving them. Errors raised by the source layer are logged without breaking the loop.

### Supporting Contracts
Located in `src/Contract/`, these interfaces describe the system boundaries: loaders, factories, processors, storage, rate limiting, and the source adapters themselves. Each service depends on interfaces to keep the architecture composable and testable.

## Processing Flow
1. **Discover** – `EventLoader` obtains all `EventSource` entities from the repository.
2. **Instantiate** – `EventSourceFactory` builds the appropriate `EventSourceInterface` implementation using the entity’s configuration.
3. **Guard** – `EventSourceLoader` enforces rate limiting (`RateLimiter`) and locks the source to avoid concurrent fetches.
4. **Process** – `EventFetcher` retrieves the latest events from the adapter, persists them via `EventStorage`, and updates the source’s `lastEventId`.
5. **Repeat** – The loop continues indefinitely, enabling near-real-time synchronization with external systems.

## Running the Pipeline
- The `EventLoader::run()` method is intended to execute inside a long-lived Symfony console command or worker process (e.g., `php bin/console app:event-loader:run`).
- Ensure required infrastructure is available: database for Doctrine entities, Memcached (or compatible backend) for `RateLimiter`, and any external dependencies needed by the source adapters (HTTP endpoints, CSV files, etc.).

## Testing & Quality
- Unit and integration tests can be executed with `php bin/phpunit`.
- Additional quality tooling (PHPStan, PHP CS Fixer, Psalm) can be configured through Composer scripts as needed.

This README captures the high-level architecture. Refer directly to the service and contract classes for implementation details and extension points.
