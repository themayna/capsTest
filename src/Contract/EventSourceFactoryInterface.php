<?php

declare(strict_types=1);

namespace App\Contract;

use App\Entity\EventSource;
interface EventSourceFactoryInterface
{
    public function create(EventSource $entity): EventSourceInterface;
}
