<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventSourceInterface;
use App\Contract\RateLimiterInterface;
use App\Contract\EventSourceLoaderInterface;
use App\Contract\EventSourceProcessor;
use Symfony\Component\Lock\LockFactory;

final readonly class EventSourceLoader implements EventSourceLoaderInterface
{
    public function __construct(
        private LockFactory          $lockFactory,
        private RateLimiterInterface $rateLimiter,
        private EventSourceProcessor $eventSourceProcessor,
    )
    {
    }

    public function load(EventSourceInterface $source): void
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
            $this->eventSourceProcessor->process($source);
        } finally {
            $lock->release();
        }
    }
}
