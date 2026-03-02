<?php

declare(strict_types=1);

namespace App\src\Contract;

use App\src\Event\Event;

interface EventStorageInterface
{
    /**
     * @param Event[] $events
     */
    public function save(string $sourceIdentifier, array $events, int $lastEventId): void;

    public function getLastEventId(string $sourceIdentifier): int;
}
