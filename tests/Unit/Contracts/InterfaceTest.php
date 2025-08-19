<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Contracts;

use IBMCloud\Contracts\AuthenticatorInterface;
use IBMCloud\Contracts\ClientInterface;
use IBMCloud\Contracts\MiddlewareInterface;
use IBMCloud\Contracts\TransportInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test that all contract interfaces can be loaded and have expected methods.
 */
class InterfaceTest extends TestCase
{
    public function testTransportInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(TransportInterface::class));
        
        $reflection = new \ReflectionClass(TransportInterface::class);
        $methods = $reflection->getMethods();
        $methodNames = array_column($methods, 'name');
        
        $this->assertContains('send', $methodNames);
        $this->assertContains('withMiddleware', $methodNames);
        $this->assertContains('withConfig', $methodNames);
    }

    public function testMiddlewareInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(MiddlewareInterface::class));
        
        $reflection = new \ReflectionClass(MiddlewareInterface::class);
        $methods = $reflection->getMethods();
        $methodNames = array_column($methods, 'name');
        
        $this->assertContains('process', $methodNames);
    }

    public function testAuthenticatorInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(AuthenticatorInterface::class));
        
        $reflection = new \ReflectionClass(AuthenticatorInterface::class);
        $methods = $reflection->getMethods();
        $methodNames = array_column($methods, 'name');
        
        $this->assertContains('authenticate', $methodNames);
        $this->assertContains('isExpired', $methodNames);
        $this->assertContains('refresh', $methodNames);
    }

    public function testClientInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ClientInterface::class));
        
        $reflection = new \ReflectionClass(ClientInterface::class);
        $methods = $reflection->getMethods();
        $methodNames = array_column($methods, 'name');
        
        $this->assertContains('getServiceName', $methodNames);
        $this->assertContains('getServiceVersion', $methodNames);
        $this->assertContains('getServiceUrl', $methodNames);
        $this->assertContains('setServiceUrl', $methodNames);
    }
}