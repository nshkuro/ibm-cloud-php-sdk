<?php

declare(strict_types=1);

namespace IBMCloud\Transport\Middleware;

use IBMCloud\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Middleware that logs HTTP requests and responses.
 */
final class LoggingMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        // Log the outgoing request.
        $this->logger->info('HTTP Request', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $this->sanitizeHeaders($request->getHeaders()),
        ]);
        
        $startTime = microtime(true);
        
        // Process the request.
        $response = $next($request);
        
        $duration = microtime(true) - $startTime;
        
        // Log the incoming response.
        $this->logger->info('HTTP Response', [
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
            'headers' => $this->sanitizeHeaders($response->getHeaders()),
        ]);
        
        return $response;
    }

    /**
     * Remove sensitive information from headers.
     * 
     * @param array<string, array<string>> $headers
     * @return array<string, array<string>>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];
        
        $sanitized = [];
        foreach ($headers as $name => $values) {
            if (in_array(strtolower($name), $sensitive, true)) {
                $sanitized[$name] = ['***REDACTED***'];
            } else {
                $sanitized[$name] = $values;
            }
        }
        
        return $sanitized;
    }
}