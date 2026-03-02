<?php

declare(strict_types=1);

namespace App\Contract;

use App\DTO\Event;

interface EventSourceInterface
{
    public function getIdentifier(): string;

    /**
     * @return Event[]
     */
    public function fetchAfter(int $lastEventId): array;
}
