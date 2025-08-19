<?php

declare(strict_types=1);

namespace IBMCloud\Exceptions\Service;

use IBMCloud\Exceptions\IBMCloudException;

class ServiceException extends IBMCloudException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}