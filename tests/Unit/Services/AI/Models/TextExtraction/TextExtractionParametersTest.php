<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\Models\TextExtraction;

use IBMCloud\Services\AI\Models\TextExtraction\TextExtractionParameters;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TextExtractionParametersTest extends TestCase
{
    public function testDefaultCreateEmbeddedImagesIsNull(): void
    {
        $params = new TextExtractionParameters();

        $this->assertNull($params->getCreateEmbeddedImages());
    }

    public function testSetCreateEmbeddedImagesDisabled(): void
    {
        $params = new TextExtractionParameters(
            createEmbeddedImages: TextExtractionParameters::EMBEDDED_IMAGES_DISABLED
        );

        $this->assertSame('disabled', $params->getCreateEmbeddedImages());
    }

    public function testSetCreateEmbeddedImagesText(): void
    {
        $params = new TextExtractionParameters(
            createEmbeddedImages: TextExtractionParameters::EMBEDDED_IMAGES_TEXT
        );

        $this->assertSame('enabled_text', $params->getCreateEmbeddedImages());
    }

    public function testSetCreateEmbeddedImagesVerbalizationAll(): void
    {
        $params = new TextExtractionParameters(
            createEmbeddedImages: TextExtractionParameters::EMBEDDED_IMAGES_VERBALIZATION_ALL
        );

        $this->assertSame('enabled_verbalization_all', $params->getCreateEmbeddedImages());
    }

    public function testRejectsInvalidCreateEmbeddedImagesValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid embedded images mode: invalid_mode');

        new TextExtractionParameters(
            createEmbeddedImages: 'invalid_mode'
        );
    }

    public function testToArrayIncludesCreateEmbeddedImages(): void
    {
        $params = new TextExtractionParameters(
            createEmbeddedImages: TextExtractionParameters::EMBEDDED_IMAGES_VERBALIZATION_ALL
        );

        $array = $params->toArray();

        $this->assertArrayHasKey('create_embedded_images', $array);
        $this->assertSame('enabled_verbalization_all', $array['create_embedded_images']);
    }

    public function testToArrayExcludesNullCreateEmbeddedImages(): void
    {
        $params = new TextExtractionParameters();

        $array = $params->toArray();

        $this->assertArrayNotHasKey('create_embedded_images', $array);
    }

    public function testCreateEmbeddedImagesWithOtherParameters(): void
    {
        $params = new TextExtractionParameters(
            requestedOutputs: [
                TextExtractionParameters::OUTPUT_ASSEMBLY,
                TextExtractionParameters::OUTPUT_MARKDOWN,
                TextExtractionParameters::OUTPUT_PAGE_IMAGES,
            ],
            mode: TextExtractionParameters::MODE_HIGH_QUALITY,
            ocrMode: TextExtractionParameters::OCR_MODE_ENABLED,
            createEmbeddedImages: TextExtractionParameters::EMBEDDED_IMAGES_VERBALIZATION_ALL
        );

        $array = $params->toArray();

        $this->assertSame(
            ['assembly', 'md', 'page_images'],
            $array['requested_outputs']
        );
        $this->assertSame('high_quality', $array['mode']);
        $this->assertSame('enabled', $array['ocr_mode']);
        $this->assertSame('enabled_verbalization_all', $array['create_embedded_images']);
    }

    public function testBackwardCompatibilityWithoutCreateEmbeddedImages(): void
    {
        // Existing code that does not pass createEmbeddedImages should work identically.
        $params = new TextExtractionParameters(
            requestedOutputs: [TextExtractionParameters::OUTPUT_MARKDOWN],
            mode: TextExtractionParameters::MODE_STANDARD
        );

        $array = $params->toArray();

        $this->assertSame(['md'], $array['requested_outputs']);
        $this->assertSame('standard', $array['mode']);
        $this->assertArrayNotHasKey('create_embedded_images', $array);
    }

    public function testConstantValues(): void
    {
        $this->assertSame('disabled', TextExtractionParameters::EMBEDDED_IMAGES_DISABLED);
        $this->assertSame('enabled_text', TextExtractionParameters::EMBEDDED_IMAGES_TEXT);
        $this->assertSame('enabled_verbalization_all', TextExtractionParameters::EMBEDDED_IMAGES_VERBALIZATION_ALL);
    }
}
