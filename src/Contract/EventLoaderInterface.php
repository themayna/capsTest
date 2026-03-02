<?php

declare(strict_types=1);

namespace App\Contract;

interface EventLoaderInterface
{
    public function run(): void;
}
