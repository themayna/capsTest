<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EventLoaderInterface;
use App\Contract\EventSourceFactoryInterface;
use App\Contract\EventSourceLoaderInterface;
use App\Repository\EventSourceRepository;

final readonly class EventLoader implements EventLoaderInterface
{
    public function __construct(
        private EventSourceFactoryInterface $sourceFactory,
        private EventSourceRepository       $eventSourceRepository,
        private EventSourceLoaderInterface  $eventSourceLoader,
    )
    {
    }

    public function run(): void
    {
        while (true) {
            $eventSources = $this->eventSourceRepository->findAll();

            foreach ($eventSources as $eventSource) {
                $source = $this->sourceFactory->create($eventSource);
                $this->eventSourceLoader->load($source);
            }
        }
    }
}
