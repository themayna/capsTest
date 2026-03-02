<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventSourceException;
use App\Contract\EventSourceInterface;
use App\Contract\EventStorageInterface;
use App\Contract\RateLimiterInterface;
use App\Contract\EventSourceProcessor;
use Psr\Log\LoggerInterface;

final readonly class EventFetcher implements EventSourceProcessor
{
    public function __construct(
        private EventStorageInterface $storage,
        private RateLimiterInterface  $rateLimiter,
        private LoggerInterface       $logger,
    )
    {
    }

    public function process(EventSourceInterface $source): void
    {
        $id = $source->getIdentifier();

        try {
            $lastId = $this->storage->getLastEventId($id);
            $this->rateLimiter->recordRequest($id);
            $events = $source->fetchAfter($lastId);

            if ($events === []) {
                return;
            }

            $newLastId = end($events)->getId();
            $this->storage->save($id, $events, $newLastId);
        } catch (EventSourceException $e) {
            $this->logger->error('Source unavailable', [
                'source' => $id,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
