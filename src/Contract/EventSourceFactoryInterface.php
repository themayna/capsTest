<?php

declare(strict_types=1);

namespace App\src\Contract;

use App\src\Entity\EventSource;
interface EventSourceFactoryInterface
{
    public function create(EventSource $entity): EventSourceInterface;
}
