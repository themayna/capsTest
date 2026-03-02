# Event Loading Mechanism

Collects events from multiple remote sources into centralized storage with support for parallel execution.

## Structure

```
src/Service/EventLoader/
├── Event.php
├── EventLoader.php
├── EventSourceInterface.php
├── EventStorageInterface.php
├── EventSourceException.php
└── SourceLockInterface.php
```

## Running Tests

```bash
composer require --dev phpunit/phpunit
./vendor/bin/phpunit
```
