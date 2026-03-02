<?php

declare(strict_types=1);

namespace App\Contract;

use App\Event\Event;

interface EventStorageInterface
{
    /**
     * @param Event[] $events
     */
    public function save(string $sourceIdentifier, array $events, int $lastEventId): void;

    public function getLastEventId(string $sourceIdentifier): int;
}
