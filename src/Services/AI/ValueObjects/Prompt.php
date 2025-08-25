<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

use InvalidArgumentException;

final class Prompt
{
    private function __construct(
        public string $content
    ) {
        if (empty($this->content)) {
            throw new InvalidArgumentException('Prompt content cannot be empty.');
        }

        if (strlen($this->content) > 32000) {
            throw new InvalidArgumentException('Prompt content cannot exceed 32,000 characters.');
        }
    }

    public static function from(string $content): self
    {
        return new self($content);
    }

    public static function systemMessage(string $content): self
    {
        return new self("System: {$content}");
    }

    public static function userMessage(string $content): self
    {
        return new self("User: {$content}");
    }

    public static function assistantMessage(string $content): self
    {
        return new self("Assistant: {$content}");
    }

    /**
     * Create a chat-formatted prompt.
     */
    public static function chat(array $messages): self
    {
        $formattedMessages = [];
        
        foreach ($messages as $message) {
            if (!isset($message['role'], $message['content'])) {
                throw new InvalidArgumentException(
                    'Chat messages must have "role" and "content" keys.'
                );
            }
            
            $role = ucfirst(strtolower($message['role']));
            $formattedMessages[] = "{$role}: {$message['content']}";
        }
        
        return new self(implode("\n\n", $formattedMessages));
    }

    /**
     * Create a prompt with context and question format.
     */
    public static function withContext(string $context, string $question): self
    {
        return new self(
            "Context: {$context}\n\nQuestion: {$question}\n\nAnswer:"
        );
    }

    /**
     * Create a few-shot prompt with examples.
     */
    public static function fewShot(string $task, array $examples, string $input): self
    {
        $prompt = "Task: {$task}\n\n";
        
        foreach ($examples as $i => $example) {
            $exampleNum = $i + 1;
            $prompt .= "Example {$exampleNum}:\n";
            $prompt .= "Input: {$example['input']}\n";
            $prompt .= "Output: {$example['output']}\n\n";
        }
        
        $prompt .= "Now complete:\n";
        $prompt .= "Input: {$input}\n";
        $prompt .= "Output:";
        
        return new self($prompt);
    }

    /**
     * Get word count of the prompt.
     */
    public function getWordCount(): int
    {
        return str_word_count($this->content);
    }

    /**
     * Get character count of the prompt.
     */
    public function getCharacterCount(): int
    {
        return strlen($this->content);
    }

    /**
     * Estimate token count (rough approximation).
     */
    public function getEstimatedTokenCount(): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        return (int) ceil(strlen($this->content) / 4);
    }

    /**
     * Check if prompt contains potentially sensitive content.
     */
    public function hasSensitiveContent(): bool
    {
        $sensitivePatterns = [
            '/\b(?:password|api_key|secret|token|credential)\b/i',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', // Credit card pattern
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN pattern
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $this->content)) {
                return true;
            }
        }
        
        return false;
    }

    public function toString(): string
    {
        return $this->content;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}