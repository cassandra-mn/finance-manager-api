<?php

namespace App\Exceptions;

use RuntimeException;

final class ServiceException extends RuntimeException
{
    public function report(): bool
    {
        return false;
    }
}
