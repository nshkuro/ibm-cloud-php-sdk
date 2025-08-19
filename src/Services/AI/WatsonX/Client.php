<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\WatsonX;

use IBMCloud\Contracts\Services\AIModelInterface;
use IBMCloud\Contracts\TransportInterface;
use IBMCloud\Services\AI\Models\Requests\CompletionRequest;
use IBMCloud\Services\AI\Models\Requests\EmbeddingRequest;
use IBMCloud\Services\AI\Models\Requests\StreamRequest;
use IBMCloud\Services\AI\Models\Requests\TextExtractionRequest;
use IBMCloud\Services\AI\Models\Results\CompletionResult;
use IBMCloud\Services\AI\Models\Results\EmbeddingResult;
use IBMCloud\Services\AI\Models\Results\StreamResult;
use IBMCloud\Services\AI\Models\Results\TextExtractionResult;
use IBMCloud\Services\AI\Models\ModelCapabilities;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Exceptions\Service\ServiceException;
use IBMCloud\Exceptions\Service\ValidationException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Client implements AIModelInterface
{
    private const API_VERSION = '2023-10-25';
    private const BASE_PATH = '/ml/v1';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $baseUrl
    ) {}

    /**
     * Generate text completion.
     */
    public function complete(CompletionRequest $request): CompletionResult
    {
        $httpRequest = $this->buildCompletionRequest($request);
        $response = $this->transport->send($httpRequest);
        
        $data = $this->parseResponse($response);
        
        return CompletionResult::fromApiResponse($data);
    }

    /**
     * Generate text completion with streaming.
     */
    public function stream(StreamRequest $request): StreamResult
    {
        $httpRequest = $this->buildStreamRequest($request);
        $response = $this->transport->send($httpRequest);
        
        return new StreamResult(
            $request->getModelId(),
            $response->getBody(),
            $request->shouldIncludeStopReason(),
            $request->shouldIncludeTokenInfo()
        );
    }

    /**
     * Generate text embeddings.
     */
    public function embed(EmbeddingRequest $request): EmbeddingResult
    {
        $httpRequest = $this->buildEmbeddingRequest($request);
        $response = $this->transport->send($httpRequest);
        
        $data = $this->parseResponse($response);
        
        return EmbeddingResult::fromApiResponse($data);
    }

    /**
     * Get model capabilities and information.
     */
    public function capabilities(): ModelCapabilities
    {
        $models = $this->listModels();
        
        if (empty($models)) {
            throw new ServiceException('No models available or accessible.');
        }

        // Return capabilities for the first available model
        return ModelCapabilities::fromApiResponse($models[0]);
    }

    /**
     * List available foundation models.
     */
    public function listModels(?string $filters = null, ?bool $techPreview = null): array
    {
        $query = ['version' => self::API_VERSION];
        
        if ($filters !== null) {
            $query['filters'] = $filters;
        }
        
        if ($techPreview !== null) {
            $query['tech_preview'] = $techPreview ? 'true' : 'false';
        }

        $httpRequest = $this->transport->createRequest(
            'GET',
            $this->buildUrl('/foundation_model_specs', $query)
        );

        $response = $this->transport->send($httpRequest);
        $data = $this->parseResponse($response);

        return $data['resources'] ?? [];
    }

    /**
     * Get information about a specific model.
     */
    public function getModel(string $modelId): array
    {
        $models = $this->listModels("modelid_$modelId");
        
        foreach ($models as $model) {
            if ($model['model_id'] === $modelId) {
                return $model;
            }
        }

        throw new ValidationException("Model '$modelId' not found or not accessible.");
    }

    /**
     * Get model capabilities for a specific model.
     */
    public function getModelCapabilities(string $modelId): ModelCapabilities
    {
        $model = $this->getModel($modelId);
        return ModelCapabilities::fromApiResponse($model);
    }

    /**
     * List supported foundation model tasks.
     */
    public function listTasks(): array
    {
        $httpRequest = $this->transport->createRequest(
            'GET',
            $this->buildUrl('/foundation_model_tasks', ['version' => self::API_VERSION])
        );

        $response = $this->transport->send($httpRequest);
        $data = $this->parseResponse($response);

        return $data['resources'] ?? [];
    }

    /**
     * Filter models by task.
     */
    public function getModelsByTask(string $taskId): array
    {
        return $this->listModels("task_$taskId");
    }

    /**
     * Filter models by provider.
     */
    public function getModelsByProvider(string $provider): array
    {
        return $this->listModels("provider_$provider");
    }

    /**
     * Get IBM Granite models only.
     */
    public function getGraniteModels(): array
    {
        return $this->getModelsByProvider('IBM');
    }

    /**
     * Get code-specialized models.
     */
    public function getCodeModels(): array
    {
        return $this->getModelsByTask('code');
    }

    /**
     * Start text extraction from a document.
     */
    public function extractText(TextExtractionRequest $request): TextExtractionResult
    {
        $httpRequest = $this->buildTextExtractionRequest($request);
        $response = $this->transport->send($httpRequest);
        
        $data = $this->parseResponse($response);
        
        return TextExtractionResult::fromApiResponse($data);
    }

    /**
     * List text extraction requests.
     */
    public function listTextExtractions(
        ?string $projectId = null,
        ?string $spaceId = null,
        ?int $limit = 100,
        ?string $start = null
    ): array {
        $query = ['version' => self::API_VERSION];
        
        if ($projectId !== null) {
            $query['project_id'] = $projectId;
        } elseif ($spaceId !== null) {
            $query['space_id'] = $spaceId;
        } else {
            throw new \InvalidArgumentException('Either project_id or space_id must be provided');
        }

        if ($limit !== null) {
            $query['limit'] = $limit;
        }

        if ($start !== null) {
            $query['start'] = $start;
        }

        $url = $this->buildUrl('/text/extractions', $query);

        $httpRequest = $this->transport->createRequest('GET', $url);
        $response = $this->transport->send($httpRequest);
        
        $data = $this->parseResponse($response);
        
        return $data['resources'] ?? [];
    }

    /**
     * Get text extraction request by ID.
     */
    public function getTextExtraction(
        string $id,
        ?string $projectId = null,
        ?string $spaceId = null
    ): TextExtractionResult {
        if (empty($id)) {
            throw new \InvalidArgumentException('Extraction ID cannot be empty');
        }

        $query = ['version' => self::API_VERSION];
        
        if ($projectId !== null) {
            $query['project_id'] = $projectId;
        } elseif ($spaceId !== null) {
            $query['space_id'] = $spaceId;
        } else {
            throw new \InvalidArgumentException('Either project_id or space_id must be provided');
        }

        $url = $this->buildUrl("/text/extractions/{$id}", $query);

        $httpRequest = $this->transport->createRequest('GET', $url);
        $response = $this->transport->send($httpRequest);
        
        $data = $this->parseResponse($response);
        
        return TextExtractionResult::fromApiResponse($data);
    }

    /**
     * Delete text extraction request.
     */
    public function deleteTextExtraction(
        string $id,
        ?string $projectId = null,
        ?string $spaceId = null,
        bool $hardDelete = false
    ): bool {
        if (empty($id)) {
            throw new \InvalidArgumentException('Extraction ID cannot be empty');
        }

        $query = ['version' => self::API_VERSION];
        
        if ($projectId !== null) {
            $query['project_id'] = $projectId;
        } elseif ($spaceId !== null) {
            $query['space_id'] = $spaceId;
        } else {
            throw new \InvalidArgumentException('Either project_id or space_id must be provided');
        }

        if ($hardDelete) {
            $query['hard_delete'] = 'true';
        }

        $url = $this->buildUrl("/text/extractions/{$id}", $query);

        $httpRequest = $this->transport->createRequest('DELETE', $url);
        $response = $this->transport->send($httpRequest);
        
        return $response->getStatusCode() === 204;
    }

    /**
     * Build HTTP request for text completion.
     */
    private function buildCompletionRequest(CompletionRequest $request): RequestInterface
    {
        $url = $this->buildUrl('/text/generation', ['version' => self::API_VERSION]);
        
        return $this->transport->createRequest('POST', $url, [
            'Content-Type' => 'application/json',
        ], json_encode($request->toArray()));
    }

    /**
     * Build HTTP request for streaming completion.
     */
    private function buildStreamRequest(StreamRequest $request): RequestInterface
    {
        $url = $this->buildUrl('/text/generation_stream', ['version' => self::API_VERSION]);
        
        return $this->transport->createRequest('POST', $url, [
            'Content-Type' => 'application/json',
            'Accept' => 'text/event-stream',
        ], json_encode($request->toArray()));
    }

    /**
     * Build HTTP request for embeddings.
     */
    private function buildEmbeddingRequest(EmbeddingRequest $request): RequestInterface
    {
        $url = $this->buildUrl('/text/embeddings', ['version' => self::API_VERSION]);
        
        return $this->transport->createRequest('POST', $url, [
            'Content-Type' => 'application/json',
        ], json_encode($request->toArray()));
    }

    /**
     * Build HTTP request for text extraction.
     */
    private function buildTextExtractionRequest(TextExtractionRequest $request): RequestInterface
    {
        $url = $this->buildUrl('/text/extractions', ['version' => self::API_VERSION]);
        
        return $this->transport->createRequest('POST', $url, [
            'Content-Type' => 'application/json',
        ], json_encode($request->toArray()));
    }

    /**
     * Build full URL with query parameters.
     */
    private function buildUrl(string $path, array $query = []): string
    {
        $url = rtrim($this->baseUrl, '/') . self::BASE_PATH . $path;
        
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        
        return $url;
    }

    /**
     * Parse HTTP response and handle errors.
     */
    private function parseResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ServiceException(
                "WatsonX API request failed with status $statusCode: $body",
                $statusCode
            );
        }

        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ServiceException(
                'Invalid JSON response from WatsonX API: ' . json_last_error_msg()
            );
        }

        return $data;
    }

    /**
     * Get service information.
     */
    public function getServiceInfo(): array
    {
        return [
            'service' => 'IBM WatsonX.ai Foundation Models',
            'api_version' => self::API_VERSION,
            'base_url' => $this->baseUrl,
            'endpoints' => [
                'text_generation' => self::BASE_PATH . '/text/generation',
                'text_generation_stream' => self::BASE_PATH . '/text/generation_stream',
                'text_embeddings' => self::BASE_PATH . '/text/embeddings',
                'foundation_models' => self::BASE_PATH . '/foundation_model_specs',
                'model_tasks' => self::BASE_PATH . '/foundation_model_tasks',
            ],
        ];
    }

    /**
     * Configure the service URL.
     */
    public function withBaseUrl(string $baseUrl): self
    {
        return new self($this->transport, $baseUrl);
    }

    /**
     * Get the transport instance.
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * Get the configured base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}