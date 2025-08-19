<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\ValueObjects;

use IBMCloud\Services\AI\ValueObjects\Temperature;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TemperatureTest extends TestCase
{
    public function testCreateValidTemperature(): void
    {
        $temp = Temperature::from(0.5);
        
        $this->assertSame(0.5, $temp->toFloat());
    }

    public function testRejectsTooLowTemperature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');
        
        Temperature::from(-0.1);
    }

    public function testRejectsTooHighTemperature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0.0 and 2.0');
        
        Temperature::from(2.1);
    }

    public function testStaticFactoryMethods(): void
    {
        $this->assertSame(0.0, Temperature::precise()->toFloat());
        $this->assertSame(0.3, Temperature::focused()->toFloat());
        $this->assertSame(0.7, Temperature::balanced()->toFloat());
        $this->assertSame(1.0, Temperature::creative()->toFloat());
        $this->assertSame(1.5, Temperature::veryCreative()->toFloat());
        $this->assertSame(2.0, Temperature::maximum()->toFloat());
    }

    public function testIsDeterministic(): void
    {
        $this->assertTrue(Temperature::precise()->isDeterministic());
        $this->assertFalse(Temperature::focused()->isDeterministic());
    }

    public function testIsLow(): void
    {
        $this->assertTrue(Temperature::precise()->isLow());
        $this->assertTrue(Temperature::focused()->isLow());
        $this->assertFalse(Temperature::balanced()->isLow());
    }

    public function testIsModerate(): void
    {
        $this->assertFalse(Temperature::precise()->isModerate());
        $this->assertTrue(Temperature::balanced()->isModerate());
        $this->assertTrue(Temperature::creative()->isModerate());
        $this->assertFalse(Temperature::veryCreative()->isModerate());
    }

    public function testIsHigh(): void
    {
        $this->assertFalse(Temperature::balanced()->isHigh());
        $this->assertFalse(Temperature::creative()->isHigh());
        $this->assertTrue(Temperature::veryCreative()->isHigh());
        $this->assertTrue(Temperature::maximum()->isHigh());
    }

    public function testGetDescription(): void
    {
        $this->assertStringContainsString('Deterministic', Temperature::precise()->getDescription());
        $this->assertStringContainsString('Low creativity', Temperature::focused()->getDescription());
        $this->assertStringContainsString('Moderate creativity', Temperature::balanced()->getDescription());
        $this->assertStringContainsString('High creativity', Temperature::veryCreative()->getDescription());
    }

    public function testGetRecommendedUseCase(): void
    {
        $this->assertStringContainsString('Code generation', Temperature::precise()->getRecommendedUseCase());
        $this->assertStringContainsString('Technical writing', Temperature::focused()->getRecommendedUseCase());
        $this->assertStringContainsString('General conversation', Temperature::balanced()->getRecommendedUseCase());
        $this->assertStringContainsString('Creative writing', Temperature::veryCreative()->getRecommendedUseCase());
    }
}