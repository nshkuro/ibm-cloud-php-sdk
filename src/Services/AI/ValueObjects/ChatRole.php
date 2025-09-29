<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

enum ChatRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
    case SYSTEM = 'system';
    case TOOL = 'tool';

    /**
     * Check if the role is valid for request messages.
     */
    public function isValidForRequest(): bool
    {
        // System role cannot be directly specified in messages according to API docs.
        // It's handled via prompt templates.
        return $this !== self::SYSTEM;
    }

    /**
     * Get display name for the role.
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ASSISTANT => 'Assistant',
            self::SYSTEM => 'System',
            self::TOOL => 'Tool',
        };
    }
}