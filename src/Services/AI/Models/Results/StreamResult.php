<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Results;

use IBMCloud\Services\AI\ValueObjects\ModelId;
use Generator;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamResult
{
    public function __construct(
        private readonly ModelId $modelId,
        private readonly StreamInterface $stream,
        private readonly bool $includeStopReason = true,
        private readonly bool $includeTokenInfo = false
    ) {}

    /**
     * Get the streaming generator for server-sent events.
     */
    public function getStream(): Generator
    {
        $buffer = '';
        
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read(8192);
            $buffer .= $chunk;
            
            // Process complete lines
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                if ($event = $this->parseServerSentEvent($line)) {
                    yield $event;
                }
            }
        }
        
        // Process any remaining buffer
        if (!empty($buffer)) {
            if ($event = $this->parseServerSentEvent(rtrim($buffer))) {
                yield $event;
            }
        }
    }

    /**
     * Collect all streaming events into a complete result.
     */
    public function collect(): CompletionResult
    {
        $results = [];
        $generatedText = '';
        $lastEvent = null;
        
        foreach ($this->getStream() as $event) {
            $lastEvent = $event;
            
            if (isset($event['data']['results'][0]['generated_text'])) {
                $generatedText .= $event['data']['results'][0]['generated_text'];
            }
        }
        
        if ($lastEvent === null) {
            throw new RuntimeException('No streaming events received.');
        }
        
        // Build final result from last event
        $finalResult = $lastEvent['data']['results'][0] ?? [];
        $finalResult['generated_text'] = $generatedText;
        
        return new CompletionResult(
            $this->modelId,
            new \DateTimeImmutable($lastEvent['data']['created_at']),
            [$finalResult],
            $lastEvent['data']['system'] ?? null
        );
    }

    /**
     * Get only the text content as it streams.
     */
    public function getTextStream(): Generator
    {
        foreach ($this->getStream() as $event) {
            if (isset($event['data']['results'][0]['generated_text'])) {
                yield $event['data']['results'][0]['generated_text'];
            }
        }
    }

    /**
     * Get streaming events with metadata.
     */
    public function getDetailedStream(): Generator
    {
        foreach ($this->getStream() as $event) {
            $result = $event['data']['results'][0] ?? [];
            
            yield [
                'text' => $result['generated_text'] ?? '',
                'stop_reason' => $this->includeStopReason ? ($result['stop_reason'] ?? null) : null,
                'token_count' => $this->includeTokenInfo ? ($result['generated_token_count'] ?? null) : null,
                'finished' => isset($result['stop_reason']),
                'event_data' => $event,
            ];
        }
    }

    /**
     * Parse a server-sent event line.
     */
    private function parseServerSentEvent(string $line): ?array
    {
        $line = trim($line);
        
        if (empty($line)) {
            return null;
        }
        
        // Handle SSE format
        if (str_starts_with($line, 'data: ')) {
            $data = substr($line, 6);
            
            if ($data === '[DONE]') {
                return ['type' => 'done', 'data' => null];
            }
            
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            
            return ['type' => 'data', 'data' => $decoded];
        }
        
        if (str_starts_with($line, 'event: ')) {
            return ['type' => 'event', 'event' => substr($line, 7)];
        }
        
        if (str_starts_with($line, 'id: ')) {
            return ['type' => 'id', 'id' => substr($line, 4)];
        }
        
        return null;
    }

    /**
     * Close the stream.
     */
    public function close(): void
    {
        $this->stream->close();
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getUnderlyingStream(): StreamInterface
    {
        return $this->stream;
    }

    public function shouldIncludeStopReason(): bool
    {
        return $this->includeStopReason;
    }

    public function shouldIncludeTokenInfo(): bool
    {
        return $this->includeTokenInfo;
    }
}