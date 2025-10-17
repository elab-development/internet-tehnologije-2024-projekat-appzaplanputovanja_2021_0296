<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ConflictException extends HttpException
{
    public string $appCode;

    public function __construct(string $message, string $appCode = 'CONFLICT')
    {
        $this->appCode = $appCode;
        parent::__construct(409, $message);
    }
}
