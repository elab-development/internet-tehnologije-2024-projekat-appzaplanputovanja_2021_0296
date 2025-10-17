<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessRuleException extends HttpException
{
    public string $appCode;

    public function __construct(string $message, string $appCode = 'BUSINESS_RULE', int $status = 422)
    {
        $this->appCode = $appCode;
        parent::__construct($status, $message);
    }
}
