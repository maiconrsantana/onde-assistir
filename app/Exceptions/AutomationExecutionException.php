<?php

namespace App\Exceptions;

use RuntimeException;

class AutomationExecutionException extends RuntimeException
{
    /** @param array<string, mixed> $result */
    public function __construct(
        string $message,
        private readonly array $result = [],
    ) {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function result(): array
    {
        return $this->result;
    }
}
