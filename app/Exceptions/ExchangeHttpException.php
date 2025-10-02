<?php

namespace App\Exceptions;

use RuntimeException;

// HTTP 오류/429/5xx
class ExchangeHttpException extends RuntimeException
{
    public function __construct(public int $status, string $message, public mixed $response = null)
    {
        parent::__construct($message, $status);
    }
}
