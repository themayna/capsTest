<?php

declare(strict_types=1);

namespace App\Contract;

use RuntimeException;

final class EventSourceException extends RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
