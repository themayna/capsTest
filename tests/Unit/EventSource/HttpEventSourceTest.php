<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSource;

use App\Contract\EventSourceException;
use App\DTO\Event;
use App\EventSource\HttpEventSource;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpEventSourceTest extends TestCase
{
    public function testGetIdentifierReturnsIdentifier(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $source = new HttpEventSource('my-source', $httpClient);

        $this->assertSame('my-source', $source->getIdentifier());
    }

    public function testFetchAfterReturnsEvents(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 1, 'type' => 'user.created', 'payload' => ['name' => 'John']],
            ['id' => 2, 'type' => 'user.updated', 'payload' => ['name' => 'Jane']],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $source = new HttpEventSource('test-source', $httpClient);

        $events = $source->fetchAfter(0);

        $this->assertCount(2, $events);
        $this->assertInstanceOf(Event::class, $events[0]);
        $this->assertSame(1, $events[0]->getId());
        $this->assertSame('user.created', $events[0]->getType());
        $this->assertSame(['name' => 'John'], $events[0]->getPayload());
    }

    public function testFetchAfterReturnsEmptyArrayWhenNoEvents(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $source = new HttpEventSource('test-source', $httpClient);

        $events = $source->fetchAfter(100);

        $this->assertSame([], $events);
    }

    public function testFetchAfterThrowsEventSourceExceptionOnNetworkFailure(): void
    {
        $transportException = new class extends \Exception implements TransportExceptionInterface {};

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException($transportException);

        $source = new HttpEventSource('test-source', $httpClient);

        $this->expectException(EventSourceException::class);
        $source->fetchAfter(0);
    }

    public function testFetchAfterThrowsEventSourceExceptionOnServerError(): void
    {
        $serverException = new class('Internal Server Error') extends \Exception implements ServerExceptionInterface {
            public function getResponse(): ResponseInterface
            {
                throw new \RuntimeException('Not implemented');
            }
        };

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException($serverException);

        $source = new HttpEventSource('test-source', $httpClient);

        $this->expectException(EventSourceException::class);
        $source->fetchAfter(0);
    }

    public function testFetchAfterPreservesOriginalExceptionAsPrevious(): void
    {

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException($originalException);

        $source = new HttpEventSource('test-source', $httpClient);

        // Act
        try {
            $source->fetchAfter(0);
            $this->fail('Expected EventSourceException to be thrown');
        } catch (EventSourceException $e) {
            // Assert
            $this->assertSame('Connection refused', $e->getMessage());
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    public function testFetchAfterPassesCorrectQueryParameter(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', '/api/events', ['query' => ['after_id' => 42]])
            ->willReturn($response);

        $source = new HttpEventSource('test-source', $httpClient);

        // Act
        $source->fetchAfter(42);
    }

    public function testFetchAfterHandlesEventsWithoutPayload(): void
    {
        // Arrange
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            ['id' => 1, 'type' => 'user.deleted'],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $source = new HttpEventSource('test-source', $httpClient);

        // Act
        $events = $source->fetchAfter(0);

        // Assert
        $this->assertCount(1, $events);
        $this->assertSame([], $events[0]->getPayload());
    }
}
