<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\ValueObjects;

use InvalidArgumentException;

final class ChatMessage
{
    private function __construct(
        private readonly ChatRole $role,
        private readonly ChatContent $content,
        private readonly ?string $name = null,
        private readonly ?array $toolCalls = null,
        private readonly ?string $toolCallId = null
    ) {
        $this->validate();
    }

    /**
     * Create a user message.
     */
    public static function user(string|array $content, ?string $name = null): self
    {
        $contentObj = is_string($content)
            ? ChatContent::fromText($content)
            : ChatContent::fromArray($content);

        return new self(ChatRole::USER, $contentObj, $name);
    }

    /**
     * Create an assistant message.
     */
    public static function assistant(string|array $content, ?array $toolCalls = null): self
    {
        $contentObj = is_string($content)
            ? ChatContent::fromText($content)
            : ChatContent::fromArray($content);

        return new self(ChatRole::ASSISTANT, $contentObj, null, $toolCalls);
    }

    /**
     * Create a system message.
     * Note: System messages are typically not allowed directly in the API.
     * They are handled via prompt templates.
     */
    public static function system(string $content): self
    {
        return new self(ChatRole::SYSTEM, ChatContent::fromText($content));
    }

    /**
     * Create a tool message (response from a function call).
     */
    public static function tool(string $content, string $toolCallId, ?string $name = null): self
    {
        return new self(
            ChatRole::TOOL,
            ChatContent::fromText($content),
            $name,
            null,
            $toolCallId
        );
    }

    /**
     * Create from array (for API responses).
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['role'])) {
            throw new InvalidArgumentException('Message must have a role.');
        }

        $role = ChatRole::from($data['role']);

        if (!isset($data['content'])) {
            // Tool calls might not have content.
            if ($role === ChatRole::ASSISTANT && isset($data['tool_calls'])) {
                $content = ChatContent::fromText('');
            } else {
                throw new InvalidArgumentException('Message must have content.');
            }
        } else {
            $content = is_string($data['content'])
                ? ChatContent::fromText($data['content'])
                : ChatContent::fromArray($data['content']);
        }

        return new self(
            $role,
            $content,
            $data['name'] ?? null,
            $data['tool_calls'] ?? null,
            $data['tool_call_id'] ?? null
        );
    }

    /**
     * Validate message structure.
     */
    private function validate(): void
    {
        // System role validation.
        if ($this->role === ChatRole::SYSTEM && !$this->role->isValidForRequest()) {
            // System messages might be used internally but not sent to API.
            // This is allowed for now, but will be filtered out when sending.
        }

        // Tool message validation.
        if ($this->role === ChatRole::TOOL && empty($this->toolCallId)) {
            throw new InvalidArgumentException('Tool messages must have a tool_call_id.');
        }

        // Tool calls are only valid for assistant messages.
        if ($this->toolCalls !== null && $this->role !== ChatRole::ASSISTANT) {
            throw new InvalidArgumentException('Only assistant messages can have tool calls.');
        }
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        $message = [
            'role' => $this->role->value,
        ];

        // Add content if not empty or if there are no tool calls.
        $contentArray = $this->content->toArray();
        if (!empty($contentArray) || $this->toolCalls === null) {
            $message['content'] = $contentArray;
        }

        if ($this->name !== null) {
            $message['name'] = $this->name;
        }

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $message['tool_call_id'] = $this->toolCallId;
        }

        return $message;
    }

    /**
     * Get the message role.
     */
    public function getRole(): ChatRole
    {
        return $this->role;
    }

    /**
     * Get the message content.
     */
    public function getContent(): ChatContent
    {
        return $this->content;
    }

    /**
     * Get the message name (if any).
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get tool calls (if any).
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    /**
     * Get tool call ID (for tool responses).
     */
    public function getToolCallId(): ?string
    {
        return $this->toolCallId;
    }

    /**
     * Check if this is a user message.
     */
    public function isUser(): bool
    {
        return $this->role === ChatRole::USER;
    }

    /**
     * Check if this is an assistant message.
     */
    public function isAssistant(): bool
    {
        return $this->role === ChatRole::ASSISTANT;
    }

    /**
     * Check if this is a system message.
     */
    public function isSystem(): bool
    {
        return $this->role === ChatRole::SYSTEM;
    }

    /**
     * Check if this is a tool message.
     */
    public function isTool(): bool
    {
        return $this->role === ChatRole::TOOL;
    }

    /**
     * Check if this message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== null && !empty($this->toolCalls);
    }

    /**
     * Get content as text.
     */
    public function getText(): ?string
    {
        return $this->content->getText();
    }

    /**
     * String representation of the message.
     */
    public function toString(): string
    {
        $roleStr = $this->role->getDisplayName();
        $contentStr = $this->content->toString();

        if ($this->name !== null) {
            return "{$roleStr} ({$this->name}): {$contentStr}";
        }

        return "{$roleStr}: {$contentStr}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}