<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Configuration\Providers;

use IBMCloud\Authentication\ValueObjects\ApiKey;
use IBMCloud\Configuration\Providers\ChainProvider;
use IBMCloud\Configuration\Providers\EnvironmentProvider;
use IBMCloud\Configuration\Providers\FileProvider;
use PHPUnit\Framework\TestCase;

class ChainProviderTest extends TestCase
{
    public function testDefaultChainCreatesEnvironmentProvider(): void
    {
        $chain = ChainProvider::defaultChain();
        
        $providers = $chain->getProviders();
        $this->assertCount(1, $providers);
        $this->assertInstanceOf(EnvironmentProvider::class, $providers[0]);
    }

    public function testWithFileCreatesChainWithFileProvider(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode(['test' => 'value']));
        
        try {
            $chain = ChainProvider::withFile($tempFile);
            
            $providers = $chain->getProviders();
            $this->assertCount(2, $providers);
            $this->assertInstanceOf(EnvironmentProvider::class, $providers[0]);
            $this->assertInstanceOf(FileProvider::class, $providers[1]);
        } finally {
            unlink($tempFile);
        }
    }

    public function testAddProviderAddsToChain(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode(['test' => 'value']));
        
        try {
            $chain = new ChainProvider();
            $fileProvider = new FileProvider($tempFile);
            
            $chain->addProvider($fileProvider);
            
            $providers = $chain->getProviders();
            $this->assertCount(1, $providers);
            $this->assertSame($fileProvider, $providers[0]);
        } finally {
            unlink($tempFile);
        }
    }

    public function testGetReturnsFromFirstAvailableProvider(): void
    {
        // Setup environment variable.
        $_ENV['TEST_KEY'] = 'env_value';
        
        // Create file with different value.
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode(['test_key' => 'file_value']));
        
        try {
            $chain = new ChainProvider([
                new EnvironmentProvider(),
                new FileProvider($tempFile)
            ]);
            
            // Should return environment value (first provider).
            $value = $chain->get('test_key');
            $this->assertEquals('env_value', $value);
        } finally {
            unset($_ENV['TEST_KEY']);
            unlink($tempFile);
        }
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $chain = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $value = $chain->get('nonexistent_key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $_ENV['TEST_KEY'] = 'test_value';
        
        try {
            $chain = ChainProvider::defaultChain();
            
            $this->assertTrue($chain->has('test_key'));
            $this->assertFalse($chain->has('nonexistent_key'));
        } finally {
            unset($_ENV['TEST_KEY']);
        }
    }

    public function testGetConfigurationMergesFromAllProviders(): void
    {
        $_ENV['ENV_KEY'] = 'env_value';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode([
            'file_key' => 'file_value',
            'env_key' => 'file_override' // Should be overridden by env.
        ]));
        
        try {
            $chain = new ChainProvider([
                new EnvironmentProvider(),
                new FileProvider($tempFile)
            ]);
            
            $config = $chain->getConfiguration();
            
            $this->assertEquals('env_value', $config['env_key']); // Environment wins.
            $this->assertEquals('file_value', $config['file_key']); // File provides additional.
        } finally {
            unset($_ENV['ENV_KEY']);
            unlink($tempFile);
        }
    }

    public function testGetWatsonXConfigReturnsFromFirstAvailableProvider(): void
    {
        $_ENV['IBM_WATSONX_URL'] = 'https://env.watsonx.com';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode([
            'ibm_watsonx_url' => 'https://file.watsonx.com',
            'ibm_watsonx_project_id' => 'file-project'
        ]));
        
        try {
            $chain = new ChainProvider([
                new EnvironmentProvider(),
                new FileProvider($tempFile)
            ]);
            
            $config = $chain->getWatsonXConfig();
            
            $this->assertEquals('https://env.watsonx.com', $config['url']); // Environment wins.
            $this->assertEquals('file-project', $config['project_id']); // File provides additional.
        } finally {
            unset($_ENV['IBM_WATSONX_URL']);
            unlink($tempFile);
        }
    }

    public function testGetWatsonXConfigReturnsDefaultsWhenEmpty(): void
    {
        $chain = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $config = $chain->getWatsonXConfig();
        
        $this->assertEquals('https://us-south.ml.cloud.ibm.com', $config['url']);
        $this->assertNull($config['project_id']);
        $this->assertNull($config['document_connection_id']);
        $this->assertNull($config['results_connection_id']);
    }

    public function testGetCOSConfigReturnsFromFirstAvailableProvider(): void
    {
        $_ENV['IBM_COS_ENDPOINT'] = 'https://env.cos.com';
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode([
            'ibm_cos_endpoint' => 'https://file.cos.com',
            'ibm_cos_service_instance_id' => 'file-instance'
        ]));
        
        try {
            $chain = new ChainProvider([
                new EnvironmentProvider(),
                new FileProvider($tempFile)
            ]);
            
            $config = $chain->getCOSConfig();
            
            $this->assertEquals('https://env.cos.com', $config['endpoint']); // Environment wins.
            $this->assertEquals('file-instance', $config['service_instance_id']); // File provides additional.
        } finally {
            unset($_ENV['IBM_COS_ENDPOINT']);
            unlink($tempFile);
        }
    }

    public function testGetCOSConfigReturnsDefaultsWhenEmpty(): void
    {
        $chain = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $config = $chain->getCOSConfig();
        
        $this->assertEquals('https://s3.us-south.cloud-object-storage.appdomain.cloud', $config['endpoint']);
        $this->assertNull($config['service_instance_id']);
    }

    public function testGetAuthConfigReturnsFromFirstAvailableProvider(): void
    {
        $_ENV['IBM_API_KEY'] = 'env-api-key';
        $_ENV['IBM_REGION'] = 'env-region';
        
        try {
            $chain = ChainProvider::defaultChain();
            
            $config = $chain->getAuthConfig();
            
            $this->assertEquals('env-api-key', $config['api_key']);
            $this->assertEquals('env-region', $config['region']);
        } finally {
            unset($_ENV['IBM_API_KEY'], $_ENV['IBM_REGION']);
        }
    }

    public function testGetAuthConfigReturnsDefaultsWhenEmpty(): void
    {
        $chain = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $config = $chain->getAuthConfig();
        
        $this->assertNull($config['api_key']);
        $this->assertEquals('us-south', $config['region']);
    }

    public function testGetLoggingConfigReturnsFromFirstAvailableProvider(): void
    {
        $_ENV['IBM_LOG_LEVEL'] = 'DEBUG';
        $_ENV['IBM_ENABLE_REQUEST_LOGGING'] = 'true';
        
        try {
            $chain = ChainProvider::defaultChain();
            
            $config = $chain->getLoggingConfig();
            
            $this->assertEquals('DEBUG', $config['level']);
            $this->assertTrue($config['enable_request_logging']);
        } finally {
            unset($_ENV['IBM_LOG_LEVEL'], $_ENV['IBM_ENABLE_REQUEST_LOGGING']);
        }
    }

    public function testGetLoggingConfigReturnsDefaultsWhenEmpty(): void
    {
        $chain = new ChainProvider([
            new EnvironmentProvider()
        ]);
        
        $config = $chain->getLoggingConfig();
        
        $this->assertEquals('INFO', $config['level']);
        $this->assertFalse($config['enable_request_logging']);
    }

    public function testCountReturnsNumberOfProviders(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_config');
        file_put_contents($tempFile, json_encode(['test' => 'value']));
        
        try {
            $chain = new ChainProvider([
                new EnvironmentProvider(),
                new FileProvider($tempFile)
            ]);
            
            $this->assertEquals(2, $chain->count());
        } finally {
            unlink($tempFile);
        }
    }
}