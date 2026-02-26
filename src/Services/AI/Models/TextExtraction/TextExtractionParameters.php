<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\TextExtraction;

use InvalidArgumentException;

/**
 * Parameters for text extraction.
 */
final class TextExtractionParameters
{
    public const OUTPUT_ASSEMBLY = 'assembly';
    public const OUTPUT_MARKDOWN = 'md';
    public const OUTPUT_HTML = 'html';
    public const OUTPUT_PLAIN_TEXT = 'plain_text';
    public const OUTPUT_TABLES_JSON = 'tables_json';
    public const OUTPUT_PAGE_IMAGES = 'page_images';

    public const MODE_STANDARD = 'standard';
    public const MODE_HIGH_QUALITY = 'high_quality';
    public const MODE_FAST = 'fast';

    public const OCR_MODE_ENABLED = 'enabled';
    public const OCR_MODE_DISABLED = 'disabled';
    public const OCR_MODE_AUTO = 'auto';

    public const EMBEDDED_IMAGES_DISABLED = 'disabled';
    public const EMBEDDED_IMAGES_TEXT = 'enabled_text';
    public const EMBEDDED_IMAGES_VERBALIZATION_ALL = 'enabled_verbalization_all';

    private static array $validOutputs = [
        self::OUTPUT_ASSEMBLY,
        self::OUTPUT_MARKDOWN,
        self::OUTPUT_HTML,
        self::OUTPUT_PLAIN_TEXT,
        self::OUTPUT_TABLES_JSON,
        self::OUTPUT_PAGE_IMAGES,
    ];

    private static array $validModes = [
        self::MODE_STANDARD,
        self::MODE_HIGH_QUALITY,
        self::MODE_FAST,
    ];

    private static array $validOcrModes = [
        self::OCR_MODE_ENABLED,
        self::OCR_MODE_DISABLED,
        self::OCR_MODE_AUTO,
    ];

    private static array $validEmbeddedImagesModes = [
        self::EMBEDDED_IMAGES_DISABLED,
        self::EMBEDDED_IMAGES_TEXT,
        self::EMBEDDED_IMAGES_VERBALIZATION_ALL,
    ];

    public function __construct(
        private readonly ?array $requestedOutputs = null,
        private readonly ?string $mode = null,
        private readonly ?string $ocrMode = null,
        private readonly ?array $languages = null,
        private readonly ?bool $tablesProcessingEnabled = null,
        private readonly ?array $custom = null,
        private readonly ?string $createEmbeddedImages = null
    ) {
        if ($requestedOutputs !== null) {
            $this->validateRequestedOutputs($requestedOutputs);
        }

        if ($mode !== null && !in_array($mode, self::$validModes)) {
            throw new InvalidArgumentException("Invalid mode: {$mode}");
        }

        if ($ocrMode !== null && !in_array($ocrMode, self::$validOcrModes)) {
            throw new InvalidArgumentException("Invalid OCR mode: {$ocrMode}");
        }

        if ($languages !== null && empty($languages)) {
            throw new InvalidArgumentException('Languages cannot be empty array');
        }

        if ($createEmbeddedImages !== null && !in_array($createEmbeddedImages, self::$validEmbeddedImagesModes)) {
            throw new InvalidArgumentException("Invalid embedded images mode: {$createEmbeddedImages}");
        }
    }

    /**
     * Create parameters for basic text extraction.
     */
    public static function basic(): self
    {
        return new self(
            requestedOutputs: [self::OUTPUT_PLAIN_TEXT],
            mode: self::MODE_STANDARD
        );
    }

    /**
     * Create parameters for high quality extraction with multiple outputs.
     */
    public static function comprehensive(): self
    {
        return new self(
            requestedOutputs: [self::OUTPUT_ASSEMBLY, self::OUTPUT_MARKDOWN, self::OUTPUT_TABLES_JSON],
            mode: self::MODE_HIGH_QUALITY,
            ocrMode: self::OCR_MODE_ENABLED,
            tablesProcessingEnabled: true
        );
    }

    /**
     * Create parameters for OCR processing.
     */
    public static function withOcr(array $languages = ['en']): self
    {
        return new self(
            requestedOutputs: [self::OUTPUT_ASSEMBLY],
            mode: self::MODE_HIGH_QUALITY,
            ocrMode: self::OCR_MODE_ENABLED,
            languages: $languages
        );
    }

    /**
     * Create parameters for table extraction.
     */
    public static function tablesOnly(): self
    {
        return new self(
            requestedOutputs: [self::OUTPUT_TABLES_JSON],
            mode: self::MODE_STANDARD,
            tablesProcessingEnabled: true
        );
    }

    public function getRequestedOutputs(): ?array
    {
        return $this->requestedOutputs;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function getOcrMode(): ?string
    {
        return $this->ocrMode;
    }

    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    public function isTablesProcessingEnabled(): ?bool
    {
        return $this->tablesProcessingEnabled;
    }

    public function getCustom(): ?array
    {
        return $this->custom;
    }

    public function getCreateEmbeddedImages(): ?string
    {
        return $this->createEmbeddedImages;
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->requestedOutputs !== null) {
            $data['requested_outputs'] = $this->requestedOutputs;
        }

        if ($this->mode !== null) {
            $data['mode'] = $this->mode;
        }

        if ($this->ocrMode !== null) {
            $data['ocr_mode'] = $this->ocrMode;
        }

        if ($this->languages !== null) {
            $data['languages'] = $this->languages;
        }

        if ($this->tablesProcessingEnabled !== null) {
            $data['tables_processing'] = ['enabled' => $this->tablesProcessingEnabled];
        }

        if ($this->custom !== null) {
            $data['custom'] = $this->custom;
        }

        if ($this->createEmbeddedImages !== null) {
            $data['create_embedded_images'] = $this->createEmbeddedImages;
        }

        return $data;
    }

    private function validateRequestedOutputs(array $outputs): void
    {
        if (empty($outputs)) {
            throw new InvalidArgumentException('Requested outputs cannot be empty');
        }

        $invalidOutputs = array_diff($outputs, self::$validOutputs);
        if (!empty($invalidOutputs)) {
            throw new InvalidArgumentException(
                'Invalid outputs: ' . implode(', ', $invalidOutputs)
            );
        }
    }
}