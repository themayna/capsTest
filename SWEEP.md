# Symfony Application - Development Guidelines

## Project Overview
This is a Symfony application built with a focus on **quality** and **fault tolerance**.

---

## 🛠️ Common Commands

### Installation & Setup
```bash
# Install dependencies
composer install

# Create database
php bin/console doctrine:database:create

# Run migrations
php bin/console doctrine:migrations:migrate

# Load fixtures (dev only)
php bin/console doctrine:fixtures:load

# Clear cache
php bin/console cache:clear
```

### Development Server
```bash
# Start Symfony local server
symfony server:start

# Or using PHP built-in server
php -S localhost:8000 -t public/
```

### Code Quality Tools
```bash
# Run PHPStan (static analysis) - level 8 for maximum strictness
vendor/bin/phpstan analyse src tests --level=8

# Run PHP CS Fixer (code style)
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/php-cs-fixer fix  # Apply fixes

# Run Psalm (static analysis alternative)
vendor/bin/psalm

# Run all quality checks
composer run-script quality
```

### Testing
```bash
# Run all tests
php bin/phpunit

# Run with coverage
XDEBUG_MODE=coverage php bin/phpunit --coverage-html var/coverage

# Run specific test suite
php bin/phpunit --testsuite=unit
php bin/phpunit --testsuite=integration
php bin/phpunit --testsuite=functional

# Run specific test file
php bin/phpunit tests/Unit/Service/MyServiceTest.php

# Run specific test method
php bin/phpunit --filter=testMethodName
```

### Database
```bash
# Create migration
php bin/console make:migration

# Run migrations
php bin/console doctrine:migrations:migrate

# Rollback last migration
php bin/console doctrine:migrations:migrate prev

# Validate schema
php bin/console doctrine:schema:validate
```

---

## 📁 Project Structure

```
src/
├── Command/           # Console commands
├── Controller/        # HTTP controllers (thin, delegate to services)
├── DTO/               # Data Transfer Objects
├── Entity/            # Doctrine entities
├── Event/             # Domain events
├── EventSubscriber/   # Event subscribers
├── Exception/         # Custom exceptions
├── Message/           # Async message classes
├── MessageHandler/    # Async message handlers
├── Repository/        # Doctrine repositories
├── Service/           # Business logic services
├── Validator/         # Custom validators
└── ValueObject/       # Value objects

tests/
├── Unit/              # Unit tests (isolated, fast)
├── Integration/       # Integration tests (with dependencies)
└── Functional/        # End-to-end/API tests
```

---

## 🎯 Code Style & Conventions

### Naming Conventions
- **Classes**: PascalCase (`UserService`, `OrderRepository`)
- **Methods**: camelCase (`findActiveUsers`, `calculateTotal`)
- **Variables**: camelCase (`$userName`, `$orderItems`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_RETRY_ATTEMPTS`)
- **Interfaces**: Suffix with `Interface` (`UserRepositoryInterface`)
- **Abstract Classes**: Prefix with `Abstract` (`AbstractPaymentProcessor`)
- **Exceptions**: Suffix with `Exception` (`UserNotFoundException`)
- **Events**: Past tense (`UserCreatedEvent`, `OrderShippedEvent`)

### PHP Standards
- **PHP Version**: 8.2+
- **Strict Types**: Always declare `declare(strict_types=1);`
- **Type Hints**: Use parameter and return type hints everywhere
- **Readonly Properties**: Use `readonly` for immutable properties
- **Constructor Property Promotion**: Preferred for simple DTOs/Value Objects

### Example Class Structure
```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\UserNotFoundException;
use App\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UserNotFoundException
     */
    public function findUserOrFail(int $id): User
    {
        $user = $this->userRepository->find($id);

        if ($user === null) {
            $this->logger->warning('User not found', ['id' => $id]);
            throw new UserNotFoundException($id);
        }

        return $user;
    }
}
```

---

## 🛡️ Fault Tolerance Patterns

### 1. Circuit Breaker Pattern
Use for external service calls to prevent cascade failures:
```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\CircuitOpenException;
use App\Exception\ServiceUnavailableException;

final class CircuitBreaker
{
    private const int FAILURE_THRESHOLD = 5;
    private const int RECOVERY_TIMEOUT = 30; // seconds

    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    private bool $isOpen = false;

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     * @throws CircuitOpenException
     */
    public function execute(callable $operation): mixed
    {
        if ($this->isOpen && !$this->shouldAttemptReset()) {
            throw new CircuitOpenException('Circuit is open, service unavailable');
        }

        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Throwable $e) {
            $this->onFailure();
            throw $e;
        }
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->isOpen = false;
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= self::FAILURE_THRESHOLD) {
            $this->isOpen = true;
        }
    }

    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null
            && (time() - $this->lastFailureTime) >= self::RECOVERY_TIMEOUT;
    }
}
```

### 2. Retry Pattern with Exponential Backoff
```php
<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;

final class RetryHandler
{
    private const int MAX_ATTEMPTS = 3;
    private const int BASE_DELAY_MS = 100;
    private const float MULTIPLIER = 2.0;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @param array<class-string<\Throwable>> $retryableExceptions
     * @return T
     */
    public function executeWithRetry(
        callable $operation,
        array $retryableExceptions = [\Exception::class],
        int $maxAttempts = self::MAX_ATTEMPTS,
    ): mixed {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if (!$this->isRetryable($e, $retryableExceptions)) {
                    throw $e;
                }

                $attempt++;

                if ($attempt < $maxAttempts) {
                    $delay = $this->calculateDelay($attempt);
                    $this->logger->warning('Operation failed, retrying', [
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'delay_ms' => $delay,
                        'exception' => $e->getMessage(),
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        throw $lastException;
    }

    private function isRetryable(\Throwable $e, array $retryableExceptions): bool
    {
        foreach ($retryableExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }
        return false;
    }

    private function calculateDelay(int $attempt): int
    {
        return (int) (self::BASE_DELAY_MS * (self::MULTIPLIER ** ($attempt - 1)));
    }
}
```

### 3. Graceful Degradation
```php
<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class ProductService
{
    public function __construct(
        private readonly ExternalPricingApi $pricingApi,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getProductPrice(string $productId): float
    {
        try {
            // Try to get fresh price from external API
            $price = $this->pricingApi->fetchPrice($productId);

            // Cache the successful response
            $this->cache->set("price_{$productId}", $price, 3600);

            return $price;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch price from API', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            // Fallback to cached price
            $cachedPrice = $this->cache->get("price_{$productId}");

            if ($cachedPrice !== null) {
                $this->logger->info('Using cached price as fallback', [
                    'product_id' => $productId,
                ]);
                return $cachedPrice;
            }

            // Ultimate fallback: return default price
            $this->logger->warning('Using default price as ultimate fallback', [
                'product_id' => $productId,
            ]);
            return $this->getDefaultPrice($productId);
        }
    }

    private function getDefaultPrice(string $productId): float
    {
        // Return a sensible default or throw if not acceptable
        return 0.0;
    }
}
```

### 4. Timeout Handling
```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TimeoutException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExternalApiClient
{
    private const float DEFAULT_TIMEOUT = 5.0; // seconds

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws TimeoutException
     */
    public function fetchData(string $endpoint, float $timeout = self::DEFAULT_TIMEOUT): array
    {
        try {
            $response = $this->httpClient->request('GET', $endpoint, [
                'timeout' => $timeout,
            ]);

            return $response->toArray();
        } catch (\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
            throw new TimeoutException(
                "Request to {$endpoint} timed out after {$timeout} seconds",
                previous: $e
            );
        }
    }
}
```

---

## ✅ Testing Guidelines

### Test Structure (AAA Pattern)
```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\UserService;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    public function testFindUserReturnsUserWhenExists(): void
    {
        // Arrange
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('find')->willReturn(new User(id: 1, name: 'John'));

        $service = new UserService($repository);

        // Act
        $result = $service->findUser(1);

        // Assert
        $this->assertSame(1, $result->getId());
        $this->assertSame('John', $result->getName());
    }

    public function testFindUserThrowsExceptionWhenNotFound(): void
    {
        // Arrange
        $repository = $this->createMock(UserRepositoryInterface::class);
        $repository->method('find')->willReturn(null);

        $service = new UserService($repository);

        // Assert & Act
        $this->expectException(UserNotFoundException::class);
        $service->findUserOrFail(999);
    }
}
```

### Test Naming Convention
- `test[MethodName][Scenario][ExpectedResult]`
- Example: `testCalculateTotalReturnsZeroWhenCartIsEmpty`

### Test Categories
- **Unit Tests**: Fast, isolated, mock all dependencies
- **Integration Tests**: Test with real database/services
- **Functional Tests**: Full HTTP request/response cycle

---

## 🔒 Exception Handling

### Custom Exception Hierarchy
```php
<?php

declare(strict_types=1);

namespace App\Exception;

// Base domain exception
abstract class DomainException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

// Specific exceptions
final class EntityNotFoundException extends DomainException
{
    public static function forEntity(string $entityClass, int|string $id): self
    {
        return new self(
            message: sprintf('%s with ID "%s" not found', $entityClass, $id),
            context: ['entity' => $entityClass, 'id' => $id],
        );
    }
}

final class ValidationException extends DomainException
{
    public static function withErrors(array $errors): self
    {
        return new self(
            message: 'Validation failed',
            context: ['errors' => $errors],
        );
    }
}
```

### Global Exception Handler
```php
<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\DomainException;
use App\Exception\EntityNotFoundException;
use App\Exception\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $statusCode = match (true) {
            $exception instanceof EntityNotFoundException => 404,
            $exception instanceof ValidationException => 422,
            $exception instanceof DomainException => 400,
            default => 500,
        };

        $this->logger->error('Exception occurred', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $response = new JsonResponse([
            'error' => [
                'message' => $exception->getMessage(),
                'code' => $statusCode,
                'details' => $exception instanceof DomainException
                    ? $exception->context
                    : [],
            ],
        ], $statusCode);

        $event->setResponse($response);
    }
}
```

---

## 📊 Logging Best Practices

### Structured Logging
```php
// ✅ Good - structured context
$this->logger->info('Order created', [
    'order_id' => $order->getId(),
    'user_id' => $user->getId(),
    'total' => $order->getTotal(),
    'items_count' => count($order->getItems()),
]);

// ❌ Bad - string interpolation
$this->logger->info("Order {$order->getId()} created for user {$user->getId()}");
```

### Log Levels
- **DEBUG**: Detailed debug information
- **INFO**: Interesting events (user login, order created)
- **NOTICE**: Normal but significant events
- **WARNING**: Exceptional occurrences that are not errors
- **ERROR**: Runtime errors that don't require immediate action
- **CRITICAL**: Critical conditions (component unavailable)
- **ALERT**: Action must be taken immediately
- **EMERGENCY**: System is unusable

---

## 🔧 Recommended Packages

### Quality & Testing
```json
{
    "require-dev": {
        "phpstan/phpstan": "^1.10",
        "phpstan/phpstan-symfony": "^1.3",
        "phpstan/phpstan-doctrine": "^1.3",
        "friendsofphp/php-cs-fixer": "^3.0",
        "phpunit/phpunit": "^10.0",
        "symfony/test-pack": "^1.0",
        "dama/doctrine-test-bundle": "^8.0",
        "zenstruck/foundry": "^1.0"
    }
}
```

### Fault Tolerance
```json
{
    "require": {
        "symfony/lock": "^7.0",
        "symfony/rate-limiter": "^7.0",
        "symfony/messenger": "^7.0",
        "symfony/scheduler": "^7.0"
    }
}
```

---

## 🚀 CI/CD Quality Gates

### Minimum Requirements
- [ ] PHPStan level 8 passes with no errors
- [ ] PHP CS Fixer reports no violations
- [ ] All tests pass
- [ ] Code coverage >= 80%
- [ ] No security vulnerabilities (`composer audit`)

### Pre-commit Checklist
```bash
# Run before committing
composer run-script quality && php bin/phpunit
```

---

## 📝 Notes

- Always use dependency injection via constructor
- Prefer composition over inheritance
- Keep controllers thin - delegate to services
- Use DTOs for data transfer between layers
- Validate input at the boundary (controllers)
- Use transactions for multi-step database operations
- Implement idempotency for critical operations
- Use async processing (Messenger) for non-critical tasks
