<?php

declare(strict_types=1);

namespace App\src\Service;

use App\src\Contract\EventSourceException;
use App\src\Contract\EventSourceFactoryInterface;
use App\src\Contract\EventSourceInterface;
use App\src\Contract\EventStorageInterface;
use App\src\Contract\RateLimiterInterface;
use App\src\Repository\EventSourceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

final class EventLoader
{
    public function __construct(
        private readonly EventSourceFactoryInterface $sourceFactory,
        private readonly EventStorageInterface       $storage,
        private readonly LockFactory                 $lockFactory,
        private readonly RateLimiterInterface        $rateLimiter,
        private readonly LoggerInterface             $logger,
        private readonly EventSourceRepository       $eventSourceRepository,
    )
    {
    }

    public function run(): void
    {
        while (true) {
            $eventSources = $this->eventSourceRepository->findAll();

            foreach ($eventSources as $eventSource) {
                $this->processSource($this->sourceFactory->create($eventSource));
            }
        }
    }


    private function processSource(EventSourceInterface $source): void
    {
        $id = $source->getIdentifier();

        if (!$this->rateLimiter->canRequest($id)) {
            return;
        }

        $lock = $this->lockFactory->createLock("event_source_{$id}");

        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->loadEvents($source);
        } finally {
            $lock->release();
        }
    }

    private function loadEvents(EventSourceInterface $source): void
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
