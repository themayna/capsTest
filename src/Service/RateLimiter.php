<?php

declare(strict_types=1);

namespace App\Service;


use App\Contract\RateLimiterInterface;

final class RateLimiter implements RateLimiterInterface
{
    private const int INTERVAL_MS = 200;

    public function __construct(
//        private readonly \Memcached $cache,
    )
    {
    }

    public function canRequest(string $sourceId): bool
    {
        $lastRequest = $this->cache->get($sourceId);

        if ($lastRequest === false) {
            return true;
        }

        $elapsedMs = (microtime(true) - (float)$lastRequest) * 1000;

        return $elapsedMs >= self::INTERVAL_MS;
    }

    public function recordRequest(string $sourceId): void
    {
        $this->cache->set($sourceId, (string)microtime(true));
    }
}
