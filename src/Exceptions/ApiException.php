<?php

namespace Microblink\IdImageUpload\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * Create a new API exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
