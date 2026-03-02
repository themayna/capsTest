<?php

declare(strict_types=1);

namespace App\src\Contract;

interface EventLoaderInterface
{
    public function run(): void;
}
