<?php

declare(strict_types=1);

namespace IBMCloud\Exceptions\Transport;

use Exception;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Exception thrown when network-related errors occur during HTTP requests.
 * 
 * This exception provides additional context about the HTTP request that failed,
 * making it easier to debug network-related issues.
 */
class NetworkException extends Exception
{
    private ?RequestInterface $request = null;

    /**
     * Create a new NetworkException instance.
     * 
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Throwable|null $previous The previous throwable for chaining
     * @param RequestInterface|null $request The HTTP request that caused the exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?RequestInterface $request = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->request = $request;
    }

    /**
     * Create a NetworkException from an HTTP request.
     * 
     * Factory method for creating exceptions with request context.
     * 
     * @param RequestInterface $request The failed HTTP request
     * @param string $message The error message
     * @param int $code Optional error code
     * @param Throwable|null $previous Optional previous exception
     * @return self New NetworkException instance
     */
    public static function fromRequest(
        RequestInterface $request,
        string $message,
        int $code = 0,
        ?Throwable $previous = null
    ): self {
        return new self($message, $code, $previous, $request);
    }

    /**
     * Get the request that caused this exception.
     * 
     * @return RequestInterface|null The HTTP request or null if not available
     */
    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    /**
     * Check if this exception has an associated request.
     * 
     * @return bool True if a request is associated with this exception
     */
    public function hasRequest(): bool
    {
        return $this->request !== null;
    }
}