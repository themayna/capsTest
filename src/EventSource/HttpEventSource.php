<?php

declare(strict_types=1);

namespace App\EventSource;

use App\DTO\Event;
use App\Contract\EventSourceException;
use App\Contract\EventSourceInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
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

    /**
     * @throws EventSourceException
     */
    public function fetchAfter(int $lastEventId): array
    {
        try {
            $response = $this->httpClient->request('GET', '/api/events', [
                'query' => ['after_id' => $lastEventId],
            ]);

            $data = $response->toArray();

            return array_map(
                fn(array $item) => new Event($item['id'], $item['type'], $item['payload'] ?? []),
                $data
            );
        } catch (ExceptionInterface $e) {
            throw new EventSourceException($e->getMessage(), $e);
        }
    }
}
