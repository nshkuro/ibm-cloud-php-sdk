# Техническое задание на разработку IBM Cloud PHP SDK

## 1. Общие положения

### 1.1 Цель проекта
Создание современной PHP библиотеки-клиента для работы с IBM Cloud Services с акцентом на производительность, типобезопасность и удобство использования в enterprise-приложениях.

### 1.2 Ключевые отличия и инновации
- **Контрактно-ориентированная архитектура** с использованием интерфейсов и DTO
- **Полная типизация** с использованием PHP 8.1+ features (enums, readonly properties, union types)
- **Middleware pipeline** для обработки запросов/ответов
- **Reactive programming** поддержка через ReactPHP/Amp
- **Service discovery** и автоконфигурация
- **Circuit breaker** паттерн для отказоустойчивости

### 1.3 Философия разработки
- Domain-Driven Design для организации кода
- SOLID принципы
- Минимальные зависимости
- Расширяемость через плагины

## 2. Архитектура библиотеки

### 2.1 Структура пакета
```
ibm-cloud-php-sdk/
├── src/
│   ├── Contracts/
│   │   ├── ClientInterface.php
│   │   ├── TransportInterface.php
│   │   ├── AuthenticatorInterface.php
│   │   ├── SerializerInterface.php
│   │   └── Services/
│   │       ├── ObjectStorageInterface.php
│   │       ├── AIModelInterface.php
│   │       └── DocumentProcessorInterface.php
│   ├── Transport/
│   │   ├── HttpTransport.php
│   │   ├── StreamingTransport.php
│   │   ├── Middleware/
│   │   │   ├── MiddlewareInterface.php
│   │   │   ├── AuthenticationMiddleware.php
│   │   │   ├── RetryMiddleware.php
│   │   │   ├── LoggingMiddleware.php
│   │   │   ├── CircuitBreakerMiddleware.php
│   │   │   └── RateLimitMiddleware.php
│   │   └── Pool/
│   │       └── ConnectionPool.php
│   ├── Authentication/
│   │   ├── Strategies/
│   │   │   ├── IamStrategy.php
│   │   │   ├── ApiKeyStrategy.php
│   │   │   └── TokenStrategy.php
│   │   └── TokenManager.php
│   ├── Services/
│   │   ├── ObjectStorage/
│   │   │   ├── Client.php
│   │   │   ├── Operations/
│   │   │   │   ├── ObjectOperations.php
│   │   │   │   ├── BucketOperations.php
│   │   │   │   └── MultipartOperations.php
│   │   │   └── ValueObjects/
│   │   │       ├── BucketName.php
│   │   │       ├── ObjectKey.php
│   │   │       └── StorageClass.php
│   │   ├── AI/
│   │   │   ├── WatsonX/
│   │   │   │   ├── Client.php
│   │   │   │   ├── Prompts/
│   │   │   │   │   ├── PromptBuilder.php
│   │   │   │   │   └── Templates/
│   │   │   │   ├── Models/
│   │   │   │   │   ├── ModelRegistry.php
│   │   │   │   │   └── ModelCapabilities.php
│   │   │   │   └── Responses/
│   │   │   │       ├── CompletionResponse.php
│   │   │   │       └── StreamResponse.php
│   │   │   └── DocumentIntelligence/
│   │   │       ├── Client.php
│   │   │       ├── Extractors/
│   │   │       │   ├── TextExtractor.php
│   │   │       │   ├── TableExtractor.php
│   │   │       │   └── MetadataExtractor.php
│   │   │       └── Processors/
│   │   │           ├── PdfProcessor.php
│   │   │           └── ImageProcessor.php
│   │   └── Common/
│   │       ├── Pagination/
│   │       │   ├── Paginator.php
│   │       │   └── Cursor.php
│   │       └── Results/
│   │           ├── Result.php
│   │           ├── ResultSet.php
│   │           └── AsyncResult.php
│   ├── Configuration/
│   │   ├── ConfigurationBuilder.php
│   │   ├── Providers/
│   │   │   ├── EnvironmentProvider.php
│   │   │   ├── FileProvider.php
│   │   │   └── ChainProvider.php
│   │   └── ServiceDiscovery.php
│   └── Exceptions/
│       ├── Contracts/
│       │   ├── RetryableExceptionInterface.php
│       │   └── DetailedExceptionInterface.php
│       ├── Transport/
│       │   ├── NetworkException.php
│       │   └── TimeoutException.php
│       ├── Service/
│       │   ├── QuotaExceededException.php
│       │   ├── ResourceNotFoundException.php
│       │   └── ValidationException.php
│       └── ExceptionFactory.php
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Fixtures/
├── docs/
├── examples/
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.php
├── README.md
├── CONTRIBUTING.md
└── LICENSE
```

### 2.2 Основные компоненты

#### 2.2.1 Transport Layer
- **Middleware Pipeline**: Цепочка обработчиков для модификации запросов/ответов
- **Connection Pooling**: Переиспользование соединений для оптимизации
- **Circuit Breaker**: Автоматическое отключение проблемных endpoint'ов
- **Async Support**: Нативная поддержка Promise/Future паттернов

#### 2.2.2 Domain Layer
- **Value Objects**: Иммутабельные объекты для представления данных
- **Domain Events**: События для отслеживания изменений состояния
- **Repositories**: Абстракция для работы с данными
- **Specifications**: Паттерн для сложных запросов

#### 2.2.3 Принципы проектирования
- **Ports and Adapters**: Изоляция бизнес-логики от инфраструктуры
- **Command Query Separation**: Разделение операций чтения и записи
- **Event Sourcing** (опционально): Для аудита операций
- **Dependency Inversion**: Зависимость от абстракций, а не конкретных реализаций

## 3. Реализация сервисов

### 3.1 Object Storage Service

#### 3.1.1 Контракт сервиса
```php
namespace IBMCloud\Contracts\Services;

interface ObjectStorageInterface
{
    public function store(ObjectCommand $command): ObjectResult;
    public function retrieve(ObjectQuery $query): ObjectResult;
    public function delete(ObjectCommand $command): void;
    public function exists(ObjectQuery $query): bool;
    public function stream(ObjectQuery $query): StreamInterface;
    public function batch(BatchCommand $commands): BatchResult;
}
```

#### 3.1.2 Реализация с паттернами
```php
namespace IBMCloud\Services\ObjectStorage;

final class Client implements ObjectStorageInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly SerializerInterface $serializer,
        private readonly MetricsCollector $metrics,
        private readonly EventDispatcher $events
    ) {}
    
    public function store(ObjectCommand $command): ObjectResult
    {
        $this->events->dispatch(new ObjectStorageEvent($command));
        
        return $this->metrics->measure('object.store', function() use ($command) {
            return $this->transport
                ->withMiddleware(new CompressionMiddleware())
                ->withMiddleware(new EncryptionMiddleware())
                ->send($command->toRequest());
        });
    }
    
    public function streamLarge(LargeFileCommand $command): Generator
    {
        $chunks = $command->splitIntoChunks();
        
        foreach ($chunks as $chunk) {
            yield $this->processChunk($chunk);
        }
    }
}
```

#### 3.1.2 Дополнительные возможности
- Поддержка потоковой передачи для больших файлов
- Presigned URLs для временного доступа
- Метаданные объектов
- Версионирование
- Lifecycle policies

### 3.2 AI Model Service

#### 3.2.1 Унифицированный интерфейс для AI моделей
```php
namespace IBMCloud\Services\AI;

interface AIModelInterface
{
    public function complete(CompletionRequest $request): CompletionResult;
    public function stream(StreamRequest $request): AsyncStream;
    public function embed(EmbeddingRequest $request): EmbeddingResult;
    public function capabilities(): ModelCapabilities;
}

final class WatsonXClient implements AIModelInterface
{
    private readonly ModelRegistry $models;
    private readonly PromptOptimizer $optimizer;
    private readonly ResponseCache $cache;
    
    public function complete(CompletionRequest $request): CompletionResult
    {
        // Оптимизация промпта
        $optimized = $this->optimizer->optimize(
            $request->prompt,
            $this->models->get($request->modelId)
        );
        
        // Проверка кеша
        if ($cached = $this->cache->get($optimized->hash())) {
            return $cached;
        }
        
        // Выполнение запроса с автоматическим fallback
        $result = $this->executeWithFallback($optimized);
        
        $this->cache->set($optimized->hash(), $result);
        
        return $result;
    }
    
    private function executeWithFallback(Request $request): CompletionResult
    {
        $models = $this->models->getCompatible($request);
        
        foreach ($models as $model) {
            try {
                return $this->transport->send(
                    $model->buildRequest($request)
                );
            } catch (QuotaExceededException $e) {
                continue; // Try next model
            }
        }
        
        throw new NoAvailableModelException();
    }
}
```

#### 3.2.2 Request Builder с валидацией
```php
namespace IBMCloud\Services\AI\WatsonX;

final class CompletionRequestBuilder
{
    private array $parameters = [];
    private array $constraints = [];
    
    public static function create(): self
    {
        return new self();
    }
    
    public function model(ModelId $modelId): self
    {
        $this->parameters['model'] = $modelId;
        return $this;
    }
    
    public function prompt(Prompt $prompt): self
    {
        $this->parameters['prompt'] = $prompt;
        return $this;
    }
    
    public function temperature(Temperature $temp): self
    {
        Assert::range($temp->value, 0.0, 2.0);
        $this->parameters['temperature'] = $temp;
        return $this;
    }
    
    public function withConstraints(Constraints ...$constraints): self
    {
        $this->constraints = $constraints;
        return $this;
    }
    
    public function build(): CompletionRequest
    {
        $this->validate();
        
        return new CompletionRequest(
            parameters: $this->parameters,
            constraints: $this->constraints,
            metadata: $this->buildMetadata()
        );
    }
    
    private function validate(): void
    {
        $validator = new RequestValidator($this->constraints);
        $validator->validate($this->parameters);
    }
}
```

#### 3.2.3 Параметры генерации
```php
namespace IBMCloud\WatsonX\FoundationModels\Models;

class GenerationParameters
{
    private ?string $decodingMethod = null; // 'greedy' or 'sample'
    private ?int $maxNewTokens = null;
    private ?int $minNewTokens = null;
    private ?float $temperature = null;
    private ?int $topK = null;
    private ?float $topP = null;
    private ?int $randomSeed = null;
    private ?float $repetitionPenalty = null;
    private ?array $stopSequences = null;
    private ?int $timeLimit = null;
    private ?ReturnOptions $returnOptions = null;
    
    // Getters, setters, and builder methods
}
```

### 3.3 Document Intelligence Service

#### 3.3.1 Модульная архитектура обработки
```php
namespace IBMCloud\Services\DocumentIntelligence;

interface DocumentProcessorInterface
{
    public function process(Document $document): ProcessingResult;
    public function supports(DocumentType $type): bool;
}

final class DocumentIntelligenceClient
{
    private readonly ProcessorChain $processors;
    private readonly JobManager $jobs;
    
    public function extract(ExtractionCommand $command): ExtractionResult
    {
        $document = $this->loadDocument($command->source);
        
        // Выбор процессора на основе типа документа
        $processor = $this->processors->resolve($document->type());
        
        // Применение pipeline обработки
        $pipeline = Pipeline::create()
            ->pipe(new ValidationStage())
            ->pipe(new PreprocessingStage())
            ->pipe($processor)
            ->pipe(new EnrichmentStage())
            ->pipe(new QualityCheckStage());
            
        return $pipeline->process($document);
    }
    
    public function extractAsync(AsyncExtractionCommand $command): Job
    {
        $job = $this->jobs->create($command);
        
        // Обработка в фоне с уведомлениями
        $this->dispatcher->dispatch(
            new ProcessDocumentJob($job),
            [new WebhookNotifier($command->webhook)]
        );
        
        return $job;
    }
    
    public function extractBatch(BatchCommand $command): BatchResult
    {
        $results = [];
        $pool = new WorkerPool($this->config->workers);
        
        foreach ($command->documents as $document) {
            $pool->submit(new ExtractionTask($document));
        }
        
        return $pool->wait();
    }
}
```

#### 3.3.2 Модель ExtractionJobRequest
```php
namespace IBMCloud\WatsonX\TextExtraction\Models;

class ExtractionJobRequest
{
    private DataConnection $dataConnection;
    private array $extractionOptions = [];
    private ?string $outputFormat = null;
    
    public function __construct(DataConnection $dataConnection);
    
    // Configuration methods
    public function withOCR(bool $enable = true): self;
    public function withTables(bool $extract = true): self;
    public function withMetadata(bool $extract = true): self;
    public function outputFormat(string $format): self; // 'json', 'text', 'markdown'
    public function pages(array $pageNumbers): self;
}
```

#### 3.3.3 DataConnection для COS
```php
namespace IBMCloud\WatsonX\TextExtraction\Models;

class DataConnection
{
    private string $type = 'cos';
    private string $bucket;
    private string $key;
    private ?string $region = null;
    private ?string $endpoint = null;
    
    public static function fromCOS(string $bucket, string $key): self;
    public static function fromUrl(string $url): self;
    public static function fromBase64(string $data, string $mimeType): self;
}
```

### 3.4 Authentication System

#### 3.4.1 Стратегия аутентификации
```php
namespace IBMCloud\Authentication;

interface AuthenticationStrategy
{
    public function authenticate(Request $request): AuthenticatedRequest;
    public function refresh(): void;
    public function isExpired(): bool;
}

final class TokenManager
{
    private readonly TokenStorage $storage;
    private readonly TokenValidator $validator;
    private readonly RefreshStrategy $refresher;
    
    public function getToken(Scope $scope): Token
    {
        $token = $this->storage->get($scope);
        
        if ($token === null || $this->validator->isExpired($token)) {
            $token = $this->refresher->refresh($scope);
            $this->storage->store($scope, $token);
        }
        
        return $token;
    }
}

final class IamStrategy implements AuthenticationStrategy
{
    private readonly TokenManager $tokens;
    private readonly ApiKey $apiKey;
    
    public function authenticate(Request $request): AuthenticatedRequest
    {
        $token = $this->tokens->getToken($request->requiredScope());
        
        return $request->withHeader(
            'Authorization',
            sprintf('Bearer %s', $token->value)
        );
    }
    
    public function refresh(): void
    {
        $response = $this->client->post('/identity/token', [
            'grant_type' => 'urn:ibm:params:oauth:grant-type:apikey',
            'apikey' => $this->apiKey->value,
        ]);
        
        $this->tokens->store(
            Token::fromResponse($response)
        );
    }
}
```

## 4. Конфигурация и инициализация

### 4.1 Configuration Builder Pattern
```php
use IBMCloud\Configuration\ConfigurationBuilder;

// Fluent configuration
$config = ConfigurationBuilder::create()
    ->withServiceDiscovery()
    ->withEnvironment('production')
    ->withRegion('us-south')
    ->withCredentials(
        CredentialsProvider::chain([
            new EnvironmentCredentialsProvider(),
            new ProfileCredentialsProvider('default'),
            new ContainerCredentialsProvider(),
        ])
    )
    ->withMiddleware([
        new TracingMiddleware(),
        new MetricsMiddleware(),
        new CachingMiddleware(ttl: 300),
    ])
    ->withRetryPolicy(
        RetryPolicy::exponentialBackoff()
            ->maxAttempts(3)
            ->maxDelay(10)
            ->retryableExceptions([NetworkException::class])
    )
    ->build();
```

### 4.2 Service Container и Dependency Injection
```php
use IBMCloud\ServiceContainer;
use IBMCloud\Services\AI\WatsonXClient;
use IBMCloud\Services\ObjectStorage\Client as StorageClient;

// Регистрация сервисов
$container = new ServiceContainer();

$container->register('transport', function($c) {
    return new HttpTransport(
        $c->get('config'),
        $c->get('middleware.pipeline')
    );
});

$container->register('ai.watsonx', function($c) {
    return new WatsonXClient(
        transport: $c->get('transport'),
        config: $c->get('config.ai'),
        cache: $c->get('cache'),
        metrics: $c->get('metrics')
    );
});

$container->register('storage', function($c) {
    return new StorageClient(
        transport: $c->get('transport'),
        config: $c->get('config.storage')
    );
});

// Использование
$ai = $container->get('ai.watsonx');
$storage = $container->get('storage');

// Или через фасад
IBMCloud::ai()->complete($request);
IBMCloud::storage()->store($command);
```

## 5. Обработка ошибок

### 5.1 Расширенная система исключений
```php
// Базовая иерархия с контекстом
interface DetailedExceptionInterface extends Throwable
{
    public function getErrorCode(): string;
    public function getContext(): array;
    public function getSuggestions(): array;
    public function isRetryable(): bool;
}

// Специализированные исключения
final class QuotaExceededException extends ServiceException implements RetryableExceptionInterface
{
    public function __construct(
        private readonly QuotaInfo $quota,
        private readonly DateTime $resetTime
    ) {
        parent::__construct(
            sprintf(
                'Quota exceeded: %d/%d requests. Resets at %s',
                $quota->used,
                $quota->limit,
                $resetTime->format('H:i:s')
            )
        );
    }
    
    public function getRetryAfter(): int
    {
        return $this->resetTime->getTimestamp() - time();
    }
    
    public function getSuggestions(): array
    {
        return [
            'Wait until quota resets',
            'Upgrade your plan for higher limits',
            'Use batch operations to reduce requests',
        ];
    }
}
```

### 5.2 Error Handler с восстановлением
```php
use IBMCloud\ErrorHandling\ErrorHandler;
use IBMCloud\ErrorHandling\RecoveryStrategies;

$handler = new ErrorHandler();

$handler
    ->when(QuotaExceededException::class)
    ->then(RecoveryStrategies::waitAndRetry())
    ->withFallback(RecoveryStrategies::useAlternativeModel());

$handler
    ->when(NetworkException::class)
    ->then(RecoveryStrategies::exponentialBackoff())
    ->withMaxAttempts(5);

$handler
    ->when(ValidationException::class)
    ->then(RecoveryStrategies::logAndFail())
    ->withNotification($alertManager);

// Использование
$result = $handler->execute(function() use ($ai, $request) {
    return $ai->complete($request);
});

// Или через декоратор
$resilientAi = new ResilientService($ai, $handler);
$result = $resilientAi->complete($request);
```

## 6. Логирование и мониторинг

### 6.1 Интеграция с PSR-3 Logger
```php
use Psr\Log\LoggerInterface;

$watsonx = new ModelInference($authenticator, $url, $projectId);
$watsonx->setLogger($logger);
$watsonx->setLogLevel(LogLevel::DEBUG);
```

### 6.2 User-Agent и телеметрия
```php
// Автоматически добавляется в заголовки
User-Agent: ibm-cloud-php-sdk/1.0.0 PHP/8.1.0 OS/Linux
```

## 7. Тестирование

### 7.1 Unit тесты
- Покрытие кода минимум 80%
- Использование PHPUnit 10+
- Mocking для HTTP запросов
- Тестирование всех публичных методов

### 7.2 Integration тесты
- Тестирование с реальными сервисами IBM Cloud
- Использование тестовых учетных данных
- Проверка всех операций CRUD
- Тестирование обработки ошибок

### 7.3 Примеры тестов
```php
namespace IBMCloud\Tests\Unit\WatsonX;

use PHPUnit\Framework\TestCase;
use IBMCloud\WatsonX\FoundationModels\ModelInference;

class ModelInferenceTest extends TestCase
{
    public function testGenerateText(): void
    {
        $mock = $this->createMock(HttpClient::class);
        $mock->expects($this->once())
            ->method('post')
            ->willReturn($this->getFixture('generation_response.json'));
            
        $service = new ModelInference($authenticator, $url, $projectId);
        $service->setHttpClient($mock);
        
        $response = $service->generate($request);
        
        $this->assertInstanceOf(GenerationResponse::class, $response);
        $this->assertNotEmpty($response->getGeneratedText());
    }
}
```

## 8. Документация

### 8.1 README.md
- Быстрый старт
- Требования
- Установка через Composer
- Базовые примеры использования
- Ссылки на полную документацию

### 8.2 Примеры кода
```php
// examples/watsonx_generation.php
use IBMCloud\Core\Authentication\IamAuthenticator;
use IBMCloud\WatsonX\FoundationModels\ModelInference;
use IBMCloud\WatsonX\FoundationModels\Models\GenerationRequest;

$authenticator = new IamAuthenticator($_ENV['IBM_API_KEY']);

$watsonx = new ModelInference(
    $authenticator,
    'https://us-south.ml.cloud.ibm.com',
    $_ENV['WX_PROJECT_ID']
);

$request = (new GenerationRequest('meta-llama/llama-3-3-70b-instruct', 'What is PHP?'))
    ->temperature(0.7)
    ->maxNewTokens(500)
    ->topP(0.9);

$response = $watsonx->generate($request);

echo $response->getGeneratedText();
```

### 8.3 API документация
- Генерация через phpDocumentor
- Подробное описание всех классов и методов
- Примеры использования в комментариях
- Описание параметров и возвращаемых значений

## 9. Производительность

### 9.1 Оптимизации
- Connection pooling для HTTP клиента
- Кеширование токенов аутентификации
- Lazy loading для зависимостей
- Streaming для больших файлов
- Batch операции где возможно

### 9.2 Асинхронные операции
```php
use React\Promise\Promise;

// Асинхронная генерация
$promise = $watsonx->generateAsync($request);
$promise->then(
    function (GenerationResponse $response) {
        echo $response->getGeneratedText();
    },
    function (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
);
```

## 10. Безопасность

### 10.1 Требования
- Безопасное хранение учетных данных
- Валидация всех входных параметров
- Санитизация данных перед отправкой
- SSL/TLS для всех соединений
- Регулярное обновление зависимостей

### 10.2 Лучшие практики
```php
// Никогда не логировать чувствительные данные
$logger->info('API call', [
    'endpoint' => $endpoint,
    'method' => $method,
    // НЕ логировать api_key, токены и т.д.
]);

// Использование переменных окружения
$apiKey = $_ENV['IBM_API_KEY'] ?? throw new Exception('API key not configured');
```

## 11. Совместимость

### 11.1 Версии PHP
- PHP 8.1+ (минимальная версия)
- PHP 8.2 (рекомендуемая)
- PHP 8.3 (полная поддержка)

### 11.2 Зависимости
```json
{
    "require": {
        "php": "^8.1",
        "guzzlehttp/guzzle": "^7.5",
        "psr/log": "^3.0",
        "psr/cache": "^3.0",
        "symfony/yaml": "^6.2|^7.0",
        "ramsey/uuid": "^4.7"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "mockery/mockery": "^1.5",
        "phpstan/phpstan": "^1.10",
        "friendsofphp/php-cs-fixer": "^3.0",
        "vimeo/psalm": "^5.0"
    }
}
```

## 12. Распространение

### 12.1 Packagist
- Публикация на packagist.org
- Название пакета: `ibm-cloud/php-sdk`
- Семантическое версионирование
- Автоматические релизы через GitHub Actions

### 12.2 Лицензия
- Apache License 2.0
- Совместимость с open source проектами

## 13. Поддержка и обслуживание

### 13.1 Issue Templates
- Bug report template
- Feature request template
- Security vulnerability template

### 13.2 Contributing Guidelines
- Код должен соответствовать PSR-12
- Обязательные unit тесты для новой функциональности
- Pull requests с подробным описанием
- Code review обязателен

## 14. Roadmap

### Phase 1 (v1.0.0) - Core Functionality
- ✅ IAM Authentication
- ✅ IBM COS базовые операции
- ⬜ WatsonX Foundation Models
- ⬜ WatsonX Text Extraction V2

### Phase 2 (v1.1.0) - Extended Features
- ⬜ Streaming responses
- ⬜ Batch operations
- ⬜ Async/await support
- ⬜ Watson OpenScale integration

### Phase 3 (v1.2.0) - Advanced Features
- ⬜ Watson Assistant
- ⬜ Watson Language Translator
- ⬜ Watson Speech to Text
- ⬜ Watson Text to Speech

### Phase 4 (v2.0.0) - Next Generation
- ⬜ Full async support with ReactPHP
- ⬜ GraphQL support
- ⬜ WebSocket support для streaming
- ⬜ Полная интеграция с Laravel/Symfony

## 15. Примеры использования для CivIQ

### 15.1 Продвинутая суммаризация с контекстом
```php
namespace CivIQ\Services;

use IBMCloud\Services\AI\AIModelInterface;
use IBMCloud\Services\AI\Prompts\PromptTemplate;

final class AdvancedSummarisationService
{
    public function __construct(
        private readonly AIModelInterface $ai,
        private readonly PromptLibrary $prompts,
        private readonly ContextEnricher $enricher
    ) {}
    
    public function summarise(SubmissionDto $submission): SummaryResult
    {
        // Обогащение контекстом
        $context = $this->enricher->enrich($submission, [
            new LocationContext(),
            new HistoricalContext(),
            new RegulationContext(),
        ]);
        
        // Динамический выбор промпта
        $template = $this->prompts->select(
            category: $submission->category,
            style: $submission->requestedStyle,
            constraints: [
                new WordLimit(500),
                new ReadabilityLevel('general_public'),
                new ToneConstraint('neutral'),
            ]
        );
        
        // Генерация с fallback
        $request = CompletionRequestBuilder::create()
            ->model(ModelId::LLAMA_3_70B)
            ->prompt($template->render([
                'text' => $submission->text,
                'context' => $context,
                'keywords' => $submission->keywords,
            ]))
            ->temperature(Temperature::precise())
            ->withConstraints(
                new FactualAccuracy(),
                new NoHallucination(),
                new SourceAttribution()
            )
            ->build();
            
        $response = $this->ai->complete($request);
        
        // Постобработка и валидация
        return $this->postProcess($response, $submission);
    }
    
    private function postProcess(CompletionResult $result, SubmissionDto $submission): SummaryResult
    {
        $validator = new SummaryValidator();
        $validated = $validator->validate($result->text, [
            new FactChecker($submission->originalText),
            new KeywordPresence($submission->keywords),
            new LengthCompliance(),
        ]);
        
        return new SummaryResult(
            text: $validated->text,
            confidence: $validated->confidence,
            sources: $this->extractSources($validated),
            metadata: $this->buildMetadata($result)
        );
    }
}
```

### 15.2 Мультимодальная категоризация
```php
namespace CivIQ\Services;

final class IntelligentCategorisationService
{
    public function __construct(
        private readonly AIModelInterface $ai,
        private readonly CategoryHierarchy $hierarchy,
        private readonly ConfidenceCalculator $confidence
    ) {}
    
    public function categorise(Document $document): CategorisationResult
    {
        // Многоуровневая категоризация
        $primaryCategories = $this->primaryClassification($document);
        $secondaryCategories = $this->secondaryClassification($document, $primaryCategories);
        
        // Анализ уверенности
        $confidenceScores = $this->confidence->calculate(
            $primaryCategories,
            $secondaryCategories,
            $document
        );
        
        // Иерархическая классификация
        $hierarchicalResult = $this->hierarchy->classify(
            $document,
            $primaryCategories,
            $secondaryCategories
        );
        
        return new CategorisationResult(
            primary: $primaryCategories,
            secondary: $secondaryCategories,
            hierarchical: $hierarchicalResult,
            confidence: $confidenceScores,
            reasoning: $this->generateReasoning($hierarchicalResult)
        );
    }
    
    private function primaryClassification(Document $document): array
    {
        $request = CompletionRequestBuilder::create()
            ->model(ModelId::MISTRAL_MEDIUM)
            ->prompt($this->buildClassificationPrompt($document))
            ->temperature(Temperature::zero())
            ->withFormat(OutputFormat::JSON)
            ->build();
            
        return $this->ai->complete($request)->toArray();
    }
}
```

### 15.3 Интеллектуальная обработка документов
```php
namespace CivIQ\Services;

final class SmartDocumentProcessor
{
    public function __construct(
        private readonly DocumentIntelligenceClient $intelligence,
        private readonly StorageInterface $storage,
        private readonly EventDispatcher $events
    ) {}
    
    public function processDocument(UploadedFile $file): ProcessedDocument
    {
        // Определение типа и стратегии обработки
        $strategy = $this->selectStrategy($file);
        
        // Асинхронная обработка с прогрессом
        $job = $this->intelligence->extractAsync(
            AsyncExtractionCommand::create()
                ->source($file)
                ->strategy($strategy)
                ->withEnrichment([
                    new EntityRecognition(),
                    new SentimentAnalysis(),
                    new TopicModeling(),
                ])
                ->onProgress(fn($progress) => 
                    $this->events->dispatch(new ProgressEvent($progress))
                )
                ->webhook($this->config->webhookUrl)
        );
        
        // Мониторинг с экспоненциальным backoff
        $monitor = new JobMonitor($job);
        $result = $monitor
            ->withTimeout(300)
            ->withPollingInterval(2)
            ->wait();
            
        // Сохранение и индексация
        $processed = $this->postProcess($result);
        $this->storage->store($processed);
        $this->indexer->index($processed);
        
        return $processed;
    }
    
    private function selectStrategy(UploadedFile $file): ProcessingStrategy
    {
        return match($file->mimeType()) {
            'application/pdf' => new PdfStrategy([
                'ocr' => true,
                'tables' => true,
                'images' => true,
            ]),
            'image/*' => new ImageStrategy([
                'ocr' => true,
                'enhance' => true,
            ]),
            'application/vnd.ms-excel' => new SpreadsheetStrategy(),
            default => new GenericStrategy(),
        };
    }
}
```

## 16. Контрольный список качества

### 16.1 Код
- [ ] Соответствие PSR-12
- [ ] Покрытие тестами > 80%
- [ ] Отсутствие критических уязвимостей (Psalm/PHPStan)
- [ ] Документация в коде (PHPDoc)
- [ ] Примеры использования

### 16.2 Функциональность
- [ ] Все заявленные методы реализованы
- [ ] Обработка всех типов ошибок
- [ ] Поддержка streaming где применимо
- [ ] Retry механизм для временных сбоев
- [ ] Валидация параметров

### 16.3 Производительность
- [ ] Эффективная работа с памятью
- [ ] Оптимизация для больших файлов
- [ ] Connection pooling
- [ ] Кеширование токенов

### 16.4 Безопасность
- [ ] Безопасное хранение credentials
- [ ] SSL/TLS для всех соединений
- [ ] Санитизация входных данных
- [ ] Регулярные обновления зависимостей

## 17. Ссылки и ресурсы

- [IBM Cloud API Docs](https://cloud.ibm.com/apidocs)
- [WatsonX AI API](https://cloud.ibm.com/apidocs/watsonx-ai)
- [IBM Cloud SDK Handbook](https://github.com/ibm-cloud-docs/sdk-handbook)
- [PSR Standards](https://www.php-fig.org/psr/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

---

**Версия документа**: 1.0.0  
**Дата создания**: 2025-08-19  
**Автор**: IBM Cloud PHP SDK Team