<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class Event
{
    public function __construct(
        private int    $id,
        private string $type,
        private array  $payload = [],
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
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
