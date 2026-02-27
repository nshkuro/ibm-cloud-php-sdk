<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

use InvalidArgumentException;

final class ChatContent
{
    private function __construct(
        private readonly string|array $content
    ) {
        $this->validate();
    }

    /**
     * Create from simple text content.
     */
    public static function fromText(string $text): self
    {
        // Allow empty text for assistant messages with tool calls.
        return new self($text);
    }

    /**
     * Create from structured content array.
     *
     * Accepts either a single structured content item (with 'type' key)
     * or an indexed array of content parts for multimodal messages.
     */
    public static function fromArray(array $content): self
    {
        if (empty($content)) {
            throw new InvalidArgumentException('Chat content array cannot be empty.');
        }

        // If no 'type' key at top level, treat as array of content parts.
        if (!isset($content['type'])) {
            if (array_is_list($content)) {
                return self::fromParts($content);
            }
            throw new InvalidArgumentException('Structured content must have a "type" field.');
        }

        if ($content['type'] === 'text' && !isset($content['text'])) {
            throw new InvalidArgumentException('Text content must have a "text" field.');
        }

        return new self($content);
    }

    /**
     * Create with multiple content parts.
     */
    public static function fromParts(array $parts): self
    {
        if (empty($parts)) {
            throw new InvalidArgumentException('Content parts cannot be empty.');
        }

        $validatedParts = [];
        foreach ($parts as $part) {
            if (is_string($part)) {
                $validatedParts[] = [
                    'type' => 'text',
                    'text' => $part
                ];
            } elseif (is_array($part)) {
                if (!isset($part['type'])) {
                    throw new InvalidArgumentException('Each content part must have a "type" field.');
                }
                $validatedParts[] = $part;
            } else {
                throw new InvalidArgumentException('Content parts must be strings or arrays.');
            }
        }

        return new self($validatedParts);
    }

    /**
     * Validate content structure.
     */
    private function validate(): void
    {
        if (is_string($this->content)) {
            // Allow empty strings for tool calls.
            return;
        }

        if (is_array($this->content)) {
            if (empty($this->content)) {
                throw new InvalidArgumentException('Content array cannot be empty.');
            }

            // Check if it's a single structured content or array of contents.
            if (isset($this->content['type'])) {
                // Single structured content.
                $this->validateStructuredContent($this->content);
            } else {
                // Array of contents.
                foreach ($this->content as $item) {
                    if (!is_array($item)) {
                        throw new InvalidArgumentException('Each content item must be an array.');
                    }
                    $this->validateStructuredContent($item);
                }
            }
        }
    }

    /**
     * Validate a single structured content item.
     */
    private function validateStructuredContent(array $content): void
    {
        if (!isset($content['type'])) {
            throw new InvalidArgumentException('Structured content must have a "type" field.');
        }

        switch ($content['type']) {
            case 'text':
                if (!isset($content['text']) || !is_string($content['text'])) {
                    throw new InvalidArgumentException('Text content must have a "text" string field.');
                }
                break;
            case 'image_url':
                if (!isset($content['image_url']) || !is_array($content['image_url'])) {
                    throw new InvalidArgumentException('Image content must have an "image_url" object.');
                }
                if (!isset($content['image_url']['url'])) {
                    throw new InvalidArgumentException('Image content must have a URL.');
                }
                break;
            // Add more content types as needed.
        }
    }

    /**
     * Check if content is simple text.
     */
    public function isText(): bool
    {
        return is_string($this->content);
    }

    /**
     * Check if content is structured.
     */
    public function isStructured(): bool
    {
        return is_array($this->content);
    }

    /**
     * Get content as text (if possible).
     */
    public function getText(): ?string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        if (is_array($this->content) && isset($this->content['type']) && $this->content['type'] === 'text') {
            return $this->content['text'] ?? null;
        }

        return null;
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): string|array
    {
        return $this->content;
    }

    /**
     * Convert to string representation.
     */
    public function toString(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        // Extract text from structured content.
        if (isset($this->content['type']) && $this->content['type'] === 'text') {
            return $this->content['text'] ?? '';
        }

        // For array of contents, concatenate text parts.
        if (is_array($this->content) && !isset($this->content['type'])) {
            $texts = [];
            foreach ($this->content as $item) {
                if (isset($item['type']) && $item['type'] === 'text' && isset($item['text'])) {
                    $texts[] = $item['text'];
                }
            }
            return implode(' ', $texts);
        }

        return json_encode($this->content);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}