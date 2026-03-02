<?php

declare(strict_types=1);

namespace App\src\Service;

use App\src\Contract\EventSourceInterface;
use App\src\Contract\RateLimiterInterface;
use App\src\Contract\EventSourceLoaderInterface;
use App\src\Contract\EventSourceProcessor;
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
