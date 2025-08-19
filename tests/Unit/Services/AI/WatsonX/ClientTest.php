<?php

declare(strict_types=1);

namespace IBMCloud\Tests\Unit\Services\AI\WatsonX;

use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\AI\WatsonX\Client;
use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Prompt;
use IBMCloud\Exceptions\Service\ServiceException;
use IBMCloud\Exceptions\Service\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class ClientTest extends TestCase
{
    private TransportInterface $transport;
    private Client $client;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->client = new Client($this->transport, 'https://us-south.ml.cloud.ibm.com');
    }

    public function testGetServiceInfo(): void
    {
        $info = $this->client->getServiceInfo();

        $this->assertSame('IBM WatsonX.ai Foundation Models', $info['service']);
        $this->assertSame('2023-05-02', $info['api_version']);
        $this->assertSame('https://us-south.ml.cloud.ibm.com', $info['base_url']);
        $this->assertArrayHasKey('endpoints', $info);
    }

    public function testWithBaseUrl(): void
    {
        $newClient = $this->client->withBaseUrl('https://eu-gb.ml.cloud.ibm.com');

        $this->assertNotSame($this->client, $newClient);
        $this->assertSame('https://eu-gb.ml.cloud.ibm.com', $newClient->getBaseUrl());
    }

    public function testComplete(): void
    {
        $request = CompletionRequest::create(
            ModelId::granite3BCode(),
            Prompt::from('Hello')
        )->withProjectId('test-project');

        $mockResponseBody = $this->createMockStreamInterface(json_encode([
            'model_id' => 'ibm/granite-3b-code-instruct',
            'created_at' => '2023-05-02T10:00:00Z',
            'results' => [[
                'generated_text' => 'Hello there!',
                'stop_reason' => 'eos_token',
                'generated_token_count' => 2,
                'input_token_count' => 1,
            ]],
        ]));

        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $result = $this->client->complete($request);

        $this->assertSame('Hello there!', $result->getText());
        $this->assertSame('eos_token', $result->getStopReason());
        $this->assertSame(2, $result->getGeneratedTokenCount());
        $this->assertSame(1, $result->getInputTokenCount());
    }

    public function testCompleteHandlesApiError(): void
    {
        $request = CompletionRequest::create(
            ModelId::granite3BCode(),
            Prompt::from('Hello')
        )->withProjectId('test-project');

        $mockResponseBody = $this->createMockStreamInterface('Bad Request');
        $mockResponse = $this->createMockResponse(400, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('WatsonX API request failed with status 400');

        $this->client->complete($request);
    }

    public function testListModels(): void
    {
        $mockResponseBody = $this->createMockStreamInterface(json_encode([
            'resources' => [
                [
                    'model_id' => 'ibm/granite-3b-code-instruct',
                    'label' => 'Granite 3B Code',
                    'provider' => 'IBM',
                    'source' => 'IBM',
                    'short_description' => 'Code generation model',
                    'tasks' => [['id' => 'code']],
                ],
            ],
        ]));

        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $models = $this->client->listModels();

        $this->assertCount(1, $models);
        $this->assertSame('ibm/granite-3b-code-instruct', $models[0]['model_id']);
        $this->assertSame('IBM', $models[0]['provider']);
    }

    public function testGetModel(): void
    {
        $modelId = 'ibm/granite-3b-code-instruct';

        $mockResponseBody = $this->createMockStreamInterface(json_encode([
            'resources' => [
                [
                    'model_id' => $modelId,
                    'label' => 'Granite 3B Code',
                    'provider' => 'IBM',
                ],
            ],
        ]));

        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $model = $this->client->getModel($modelId);

        $this->assertSame($modelId, $model['model_id']);
        $this->assertSame('IBM', $model['provider']);
    }

    public function testGetModelNotFound(): void
    {
        $mockResponseBody = $this->createMockStreamInterface(json_encode(['resources' => []]));
        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Model \'nonexistent\' not found or not accessible.');

        $this->client->getModel('nonexistent');
    }

    public function testCapabilities(): void
    {
        $mockResponseBody = $this->createMockStreamInterface(json_encode([
            'resources' => [
                [
                    'model_id' => 'ibm/granite-3b-code-instruct',
                    'label' => 'Granite 3B Code',
                    'provider' => 'IBM',
                    'source' => 'IBM',
                    'tasks' => [['id' => 'code']],
                ],
            ],
        ]));

        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $capabilities = $this->client->capabilities();

        $this->assertSame('ibm/granite-3b-code-instruct', $capabilities->getModelId()->toString());
        $this->assertSame('IBM', $capabilities->getProvider());
        $this->assertTrue($capabilities->supportsTask('code'));
    }

    public function testCapabilitiesWithNoModels(): void
    {
        $mockResponseBody = $this->createMockStreamInterface(json_encode(['resources' => []]));
        $mockResponse = $this->createMockResponse(200, $mockResponseBody);
        $this->setupTransportMock($mockResponse);

        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('No models available or accessible.');

        $this->client->capabilities();
    }

    private function createMockStreamInterface(string $content): StreamInterface
    {
        $mock = $this->createMock(StreamInterface::class);
        $mock->method('__toString')->willReturn($content);
        return $mock;
    }

    private function createMockResponse(int $statusCode, StreamInterface $body): ResponseInterface
    {
        $mock = $this->createMock(ResponseInterface::class);
        $mock->method('getStatusCode')->willReturn($statusCode);
        $mock->method('getBody')->willReturn($body);
        return $mock;
    }

    private function setupTransportMock(ResponseInterface $response): void
    {
        $this->transport
            ->method('createRequest')
            ->willReturn($this->createMock(RequestInterface::class));
        
        $this->transport
            ->method('send')
            ->willReturn($response);
    }
}