<?php

declare(strict_types=1);

namespace App\src\Contract;

interface RateLimiterInterface
{
    public function canRequest(string $sourceId): bool;

    public function recordRequest(string $sourceId): void;
}
