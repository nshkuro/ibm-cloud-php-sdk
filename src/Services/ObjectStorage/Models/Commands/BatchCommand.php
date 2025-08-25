<?php

declare(strict_types=1);

namespace IBMCloud\Services\ObjectStorage\Models\Commands;

/**
 * Command for batch operations on multiple objects.
 */
final class BatchCommand
{
    /** @var ObjectCommand[] */
    public array $commands;

    public function __construct(ObjectCommand ...$commands)
    {
        if (empty($commands)) {
            throw new \InvalidArgumentException('Batch command must contain at least one operation.');
        }
        
        $this->commands = array_values($commands);
    }

    /**
     * Get the number of commands in this batch.
     */
    public function count(): int
    {
        return count($this->commands);
    }

    /**
     * Get all store commands.
     * 
     * @return ObjectCommand[]
     */
    public function getStoreCommands(): array
    {
        return array_filter($this->commands, fn($cmd) => $cmd->isStore());
    }

    /**
     * Get all delete commands.
     * 
     * @return ObjectCommand[]
     */
    public function getDeleteCommands(): array
    {
        return array_filter($this->commands, fn($cmd) => $cmd->isDelete());
    }
}