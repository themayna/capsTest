<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Contract\EventSourceException;
use App\Contract\EventSourceInterface;
use App\Contract\EventStorageInterface;
use App\Contract\RateLimiterInterface;
use App\DTO\Event;
use App\Service\EventFetcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EventFetcherTest extends TestCase
{
    private EventStorageInterface&MockObject $storage;
    private RateLimiterInterface&MockObject $rateLimiter;
    private LoggerInterface&MockObject $logger;
    private EventFetcher $fetcher;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(EventStorageInterface::class);
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->fetcher = new EventFetcher(
            $this->storage,
            $this->rateLimiter,
            $this->logger,
        );
    }

    public function testProcessFetchesAndStoresEvents(): void
    {
        // Arrange
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('test-source');
        $source->method('fetchAfter')->willReturn([
            new Event(1, 'user.created', ['name' => 'John']),
            new Event(2, 'user.updated', ['name' => 'Jane']),
        ]);

        $this->storage->method('getLastEventId')->willReturn(0);
        $this->storage->expects($this->once())
            ->method('save')
            ->with(
                $this->equalTo('test-source'),
                $this->callback(fn(array $events) => count($events) === 2),
                $this->equalTo(2)
            );

        $this->rateLimiter->expects($this->once())
            ->method('recordRequest')
            ->with('test-source');

        $this->fetcher->process($source);
    }

    public function testProcessDoesNotStoreWhenNoEvents(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('test-source');
        $source->method('fetchAfter')->willReturn([]);

        $this->storage->method('getLastEventId')->willReturn(0);
        $this->storage->expects($this->never())->method('save');

        $this->fetcher->process($source);
    }

    public function testProcessLogsErrorOnEventSourceException(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('failing-source');
        $source->method('fetchAfter')
            ->willThrowException(new EventSourceException('Connection timeout'));

        $this->storage->method('getLastEventId')->willReturn(0);
        $this->storage->expects($this->never())->method('save');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Source unavailable', [
                'source' => 'failing-source',
                'error' => 'Connection timeout',
            ]);

        $this->fetcher->process($source);
    }

    public function testProcessLogsErrorOnNetworkFailure(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('http-source');
        $source->method('fetchAfter')
            ->willThrowException(new EventSourceException('Could not resolve host'));

        $this->storage->method('getLastEventId')->willReturn(0);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Source unavailable', $this->callback(
                fn(array $context) => $context['source'] === 'http-source'
                    && str_contains($context['error'], 'Could not resolve host')
            ));

        $this->fetcher->process($source);
    }

    public function testProcessLogsErrorOnServerError(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('http-source');
        $source->method('fetchAfter')
            ->willThrowException(new EventSourceException('HTTP 500 Internal Server Error'));

        $this->storage->method('getLastEventId')->willReturn(0);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Source unavailable', $this->callback(
                fn(array $context) => str_contains($context['error'], '500')
            ));

        $this->fetcher->process($source);
    }

    public function testProcessContinuesAfterError(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getIdentifier')->willReturn('test-source');
        $source->method('fetchAfter')
            ->willThrowException(new EventSourceException('Temporary failure'));

        $this->storage->method('getLastEventId')->willReturn(0);

        $this->fetcher->process($source);
        $this->assertTrue(true);
    }
}
