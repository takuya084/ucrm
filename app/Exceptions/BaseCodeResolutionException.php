<?php

namespace App\Exceptions;

class BaseCodeResolutionException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
