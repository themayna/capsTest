<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventSourceFactoryInterface;
use App\Contract\EventSourceInterface;
use App\Entity\EventSource;
use App\EventSource\CsvEventSource;
use App\EventSource\HttpEventSource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class EventSourceFactory implements EventSourceFactoryInterface
{
    public function __construct(
        private HttpClientInterface   $httpClient,
    )
    {
    }

    public function create(EventSource $entity): EventSourceInterface
    {
        return match ($entity->getType()) {
            'http' => $this->createHttpSource($entity),
            'csv' => $this->createCsvSource($entity),
            default => throw new \InvalidArgumentException("Unknown event source type: {$entity->getType()}"),
        };
    }

    private function createHttpSource(EventSource $entity): HttpEventSource
    {
        $config = $entity->getConfig();
        $baseUri = $config['base_uri'] ?? throw new \InvalidArgumentException("Missing 'base_uri' in config");

        $scopedClient = $this->httpClient->withOptions(['base_uri' => $baseUri]);

        return new HttpEventSource($entity->getSource()->getIdentifier(), $scopedClient);
    }

    private function createCsvSource(EventSource $entity): CsvEventSource
    {
        $config = $entity->getConfig();
        $filePath = $config['file_path'] ?? throw new \InvalidArgumentException("Missing 'file_path' in config");

        return new CsvEventSource($entity->getSource()->getIdentifier(), $filePath);
    }
}
