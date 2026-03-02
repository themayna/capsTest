<?php

declare(strict_types=1);

namespace App\src\Event;

final readonly class Event
{
    public function __construct(
        private int    $sourceEventId,
        private string $type,
        private array  $payload = [],
    )
    {
    }

    public function getSourceEventId(): int
    {
        return $this->sourceEventId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }
}
