<?php

declare(strict_types=1);

namespace App\src\EventSource;

use App\DTO\Event;
use App\src\Contract\EventSourceInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpEventSource implements EventSourceInterface
{
    public function __construct(
        private string              $identifier,
        private HttpClientInterface $httpClient,
    )
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function fetchAfter(int $lastEventId): array
    {
        $response = $this->httpClient->request('GET', '/api/events', [
            'query' => ['after_id' => $lastEventId],
        ]);

        $data = $response->toArray();

        return array_map(
            fn(array $item) => new Event($item['id'], $item['type'], $item['payload'] ?? []),
            $data
        );
    }
}
