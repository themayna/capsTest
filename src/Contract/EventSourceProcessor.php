<?php

declare(strict_types=1);

namespace App\src\Contract;

interface EventSourceProcessor
{
    public function process(EventSourceInterface $source): void;
}
