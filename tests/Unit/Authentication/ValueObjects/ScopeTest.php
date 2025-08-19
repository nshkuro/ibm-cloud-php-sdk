<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Authentication\ValueObjects;

use IBMCloud\Authentication\ValueObjects\Scope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    public function testCreateScope(): void
    {
        $scope = new Scope('test-service', 'us-east');
        
        $this->assertSame('test-service', $scope->service);
        $this->assertSame('us-east', $scope->region);
    }

    public function testCreateScopeWithDefaultRegion(): void
    {
        $scope = new Scope('test-service');
        
        $this->assertSame('test-service', $scope->service);
        $this->assertSame('global', $scope->region);
    }

    public function testRejectsEmptyService(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Service name cannot be empty.');
        
        new Scope('');
    }

    public function testRejectsEmptyRegion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Region cannot be empty.');
        
        new Scope('service', '');
    }

    public function testObjectStorageScope(): void
    {
        $scope = Scope::objectStorage('eu-gb');
        
        $this->assertSame('cloud-object-storage', $scope->service);
        $this->assertSame('eu-gb', $scope->region);
    }

    public function testWatsonxAiScope(): void
    {
        $scope = Scope::watsonxAi();
        
        $this->assertSame('watsonx-ai', $scope->service);
        $this->assertSame('us-south', $scope->region);
    }

    public function testDocumentIntelligenceScope(): void
    {
        $scope = Scope::documentIntelligence('us-east');
        
        $this->assertSame('document-intelligence', $scope->service);
        $this->assertSame('us-east', $scope->region);
    }

    public function testGlobalScope(): void
    {
        $scope = Scope::global();
        
        $this->assertSame('*', $scope->service);
        $this->assertSame('global', $scope->region);
    }

    public function testGetIdentifier(): void
    {
        $scope = new Scope('my-service', 'my-region');
        
        $this->assertSame('my-service:my-region', $scope->getIdentifier());
    }

    public function testMatches(): void
    {
        $scope1 = new Scope('service-a', 'us-south');
        $scope2 = new Scope('service-a', 'us-south');
        $scope3 = new Scope('service-a', 'us-east');
        $scope4 = new Scope('service-b', 'us-south');
        
        $this->assertTrue($scope1->matches($scope2));
        $this->assertFalse($scope1->matches($scope3));
        $this->assertFalse($scope1->matches($scope4));
    }

    public function testIsGlobal(): void
    {
        $globalByService = new Scope('*', 'us-south');
        $globalByRegion = new Scope('service', 'global');
        $fullyGlobal = Scope::global();
        $notGlobal = new Scope('service', 'us-south');
        
        $this->assertTrue($globalByService->isGlobal());
        $this->assertTrue($globalByRegion->isGlobal());
        $this->assertTrue($fullyGlobal->isGlobal());
        $this->assertFalse($notGlobal->isGlobal());
    }
}