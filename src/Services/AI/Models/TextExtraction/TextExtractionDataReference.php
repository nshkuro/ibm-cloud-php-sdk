<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\TextExtraction;

use InvalidArgumentException;

/**
 * Reference to data for text extraction.
 */
final class TextExtractionDataReference
{
    public function __construct(
        private readonly string $type,
        private readonly array $connection,
        private readonly array $location
    ) {
        if (empty($type)) {
            throw new InvalidArgumentException('Type cannot be empty');
        }

        if (empty($connection)) {
            throw new InvalidArgumentException('Connection cannot be empty');
        }

        if (empty($location)) {
            throw new InvalidArgumentException('Location cannot be empty');
        }
    }

    /**
     * Create connection asset reference.
     */
    public static function connectionAsset(string $connectionId, string $fileName): self
    {
        return new self(
            type: 'connection_asset',
            connection: ['id' => $connectionId],
            location: ['file_name' => $fileName]
        );
    }

    /**
     * Create data asset reference.
     */
    public static function dataAsset(string $assetId): self
    {
        return new self(
            type: 'data_asset',
            connection: [],
            location: ['asset_id' => $assetId]
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConnection(): array
    {
        return $this->connection;
    }

    public function getLocation(): array
    {
        return $this->location;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'connection' => $this->connection,
            'location' => $this->location,
        ];
    }
}