<?php

declare(strict_types=1);

namespace IBMCloud\Services\AI\Models\Requests;

use IBMCloud\Services\AI\Models\ChatTool;
use IBMCloud\Services\AI\Models\ChatToolChoice;
use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\Models\ResponseFormat;
use IBMCloud\Services\AI\ValueObjects\ChatMessage;
use IBMCloud\Services\AI\ValueObjects\ChatRole;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use InvalidArgumentException;

class ChatRequest
{
    /** @var ChatMessage[] */
    private array $messages;

    /** @var ChatTool[] */
    private array $tools;

    private readonly ?ResponseFormat $responseFormat;

    public function __construct(
        private readonly ModelId $modelId,
        array $messages,
        private readonly ?string $projectId = null,
        private readonly ?string $spaceId = null,
        private readonly ?GenerationParameters $parameters = null,
        array $tools = [],
        private readonly ?ChatToolChoice $toolChoice = null,
        private readonly ?string $context = null,
        ResponseFormat|array|null $responseFormat = null,
        private readonly ?array $moderations = null
    ) {
        $this->setMessages($messages);
        $this->setTools($tools);

        // Convert array to ResponseFormat for backwards compatibility.
        if (is_array($responseFormat)) {
            $responseFormat = ResponseFormat::fromArray($responseFormat);
        }
        $this->responseFormat = $responseFormat;

        if ($this->projectId !== null || $this->spaceId !== null) {
            $this->validateRequest();
        }
    }

    /**
     * Create a chat request with model and messages.
     */
    public static function create(ModelId $modelId, array $messages): self
    {
        return new self($modelId, $messages);
    }

    /**
     * Set messages for the chat.
     */
    private function setMessages(array $messages): void
    {
        if (empty($messages)) {
            throw new InvalidArgumentException('Messages cannot be empty.');
        }

        if (count($messages) > 1000) {
            throw new InvalidArgumentException('Maximum 1000 messages allowed.');
        }

        $this->messages = [];
        foreach ($messages as $message) {
            if ($message instanceof ChatMessage) {
                $this->messages[] = $message;
            } elseif (is_array($message)) {
                $this->messages[] = ChatMessage::fromArray($message);
            } else {
                throw new InvalidArgumentException('Each message must be a ChatMessage instance or array.');
            }
        }

        // Filter out system messages as they're not allowed directly.
        $this->messages = array_filter(
            $this->messages,
            fn(ChatMessage $msg) => $msg->getRole() !== ChatRole::SYSTEM
        );

        if (empty($this->messages)) {
            throw new InvalidArgumentException('At least one non-system message is required.');
        }
    }

    /**
     * Set tools for the chat.
     */
    private function setTools(array $tools): void
    {
        if (count($tools) > 128) {
            throw new InvalidArgumentException('Maximum 128 tools allowed.');
        }

        $this->tools = [];
        foreach ($tools as $tool) {
            if ($tool instanceof ChatTool) {
                $this->tools[] = $tool;
            } elseif (is_array($tool)) {
                $this->tools[] = ChatTool::fromArray($tool);
            } else {
                throw new InvalidArgumentException('Each tool must be a ChatTool instance or array.');
            }
        }
    }

    /**
     * Set project ID for the request.
     */
    public function withProjectId(string $projectId): self
    {
        if (empty($projectId)) {
            throw new InvalidArgumentException('Project ID cannot be empty.');
        }

        return new self(
            $this->modelId,
            $this->messages,
            $projectId,
            $this->spaceId,
            $this->parameters,
            $this->tools,
            $this->toolChoice,
            $this->context,
            $this->responseFormat,
            $this->moderations
        );
    }

    /**
     * Set space ID for the request.
     */
    public function withSpaceId(string $spaceId): self
    {
        if (empty($spaceId)) {
            throw new InvalidArgumentException('Space ID cannot be empty.');
        }

        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $spaceId,
            $this->parameters,
            $this->tools,
            $this->toolChoice,
            $this->context,
            $this->responseFormat,
            $this->moderations
        );
    }

    /**
     * Set generation parameters.
     */
    public function withParameters(GenerationParameters $parameters): self
    {
        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $this->spaceId,
            $parameters,
            $this->tools,
            $this->toolChoice,
            $this->context,
            $this->responseFormat,
            $this->moderations
        );
    }

    /**
     * Add tools for function calling.
     */
    public function withTools(array $tools, ?ChatToolChoice $toolChoice = null): self
    {
        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $tools,
            $toolChoice ?? ChatToolChoice::auto(),
            $this->context,
            $this->responseFormat,
            $this->moderations
        );
    }

    /**
     * Set context to be injected into messages.
     */
    public function withContext(string $context): self
    {
        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $this->tools,
            $this->toolChoice,
            $context,
            $this->responseFormat,
            $this->moderations
        );
    }

    /**
     * Set response format (e.g., JSON mode, JSON schema).
     *
     * @param ResponseFormat|array $format ResponseFormat object or legacy array format.
     */
    public function withResponseFormat(ResponseFormat|array $format): self
    {
        // Convert legacy array format to ResponseFormat for backwards compatibility.
        if (is_array($format)) {
            $format = ResponseFormat::fromArray($format);
        }

        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $this->tools,
            $this->toolChoice,
            $this->context,
            $format,
            $this->moderations
        );
    }

    /**
     * Enable JSON response mode.
     * The model will return valid JSON, but structure is not enforced.
     */
    public function withJsonMode(): self
    {
        return $this->withResponseFormat(ResponseFormat::jsonObject());
    }

    /**
     * Enable JSON schema response mode.
     * The model will return JSON that strictly conforms to the provided schema.
     *
     * @param string $name A descriptive name for the schema.
     * @param array|string $schema The JSON schema definition (array or JSON string).
     * @param bool $strict Whether to enforce strict schema validation.
     */
    public function withJsonSchema(string $name, array|string $schema, bool $strict = true): self
    {
        // Convert JSON string to array if needed.
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(
                    'Invalid JSON schema string: ' . json_last_error_msg()
                );
            }
            $schema = $decoded;
        }

        return $this->withResponseFormat(ResponseFormat::jsonSchema($name, $schema, $strict));
    }

    /**
     * Set content moderation settings.
     */
    public function withModerations(array $moderations): self
    {
        return new self(
            $this->modelId,
            $this->messages,
            $this->projectId,
            $this->spaceId,
            $this->parameters,
            $this->tools,
            $this->toolChoice,
            $this->context,
            $this->responseFormat,
            $moderations
        );
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        $this->validateRequest();

        $request = [
            'model_id' => $this->modelId->toString(),
            'messages' => array_map(fn(ChatMessage $msg) => $msg->toArray(), $this->messages),
        ];

        if ($this->projectId !== null) {
            $request['project_id'] = $this->projectId;
        }

        if ($this->spaceId !== null) {
            $request['space_id'] = $this->spaceId;
        }

        if ($this->parameters !== null) {
            // Merge parameters into request for chat API.
            $params = $this->parameters->toArray();
            if (isset($params['max_new_tokens'])) {
                $request['max_tokens'] = $params['max_new_tokens'];
                unset($params['max_new_tokens']);
            }
            foreach ($params as $key => $value) {
                $request[$key] = $value;
            }
        }

        if (!empty($this->tools)) {
            $request['tools'] = array_map(fn(ChatTool $tool) => $tool->toArray(), $this->tools);
        }

        if ($this->toolChoice !== null) {
            $toolChoiceValue = $this->toolChoice->toValue();
            if ($toolChoiceValue !== null) {
                if (is_string($toolChoiceValue) && $toolChoiceValue !== 'auto') {
                    $request['tool_choice_option'] = $toolChoiceValue;
                } elseif (is_array($toolChoiceValue)) {
                    $request['tool_choice'] = $toolChoiceValue;
                }
            }
        }

        if ($this->context !== null) {
            $request['context'] = $this->context;
        }

        if ($this->responseFormat !== null) {
            $request['response_format'] = $this->responseFormat->toArray();
        }

        if ($this->moderations !== null) {
            $request['moderations'] = $this->moderations;
        }

        return $request;
    }

    /**
     * Validate the request.
     */
    private function validateRequest(): void
    {
        if ($this->projectId === null && $this->spaceId === null) {
            throw new InvalidArgumentException(
                'Either project_id or space_id must be provided.'
            );
        }

        if ($this->projectId !== null && $this->spaceId !== null) {
            throw new InvalidArgumentException(
                'Cannot specify both project_id and space_id. Choose one.'
            );
        }
    }

    // Getters
    public function getModelId(): ModelId
    {
        return $this->modelId;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getSpaceId(): ?string
    {
        return $this->spaceId;
    }

    public function getParameters(): ?GenerationParameters
    {
        return $this->parameters;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function getToolChoice(): ?ChatToolChoice
    {
        return $this->toolChoice;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getResponseFormat(): ?ResponseFormat
    {
        return $this->responseFormat;
    }

    public function getModerations(): ?array
    {
        return $this->moderations;
    }
}