<?php

declare(strict_types=1);

namespace App\src\Contract;

interface EventSourceLoaderInterface
{
    public function load(EventSourceInterface $source): void;
}
