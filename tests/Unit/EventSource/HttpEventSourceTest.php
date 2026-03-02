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
}
