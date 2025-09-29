<?php

/**
 * IBM WatsonX Chat API Example.
 *
 * This example demonstrates how to use the chat completion API
 * with multi-turn conversations, tool calling, and various response modes.
 */

require __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Configuration\ConfigurationBuilder;
use IBMCloud\Services\AI\Models\ChatTool;
use IBMCloud\Services\AI\Models\ChatToolChoice;
use IBMCloud\Services\AI\Models\GenerationParameters;
use IBMCloud\Services\AI\Models\Requests\ChatRequest;
use IBMCloud\Services\AI\ValueObjects\ChatMessage;
use IBMCloud\Services\AI\ValueObjects\ModelId;
use IBMCloud\Services\AI\ValueObjects\Temperature;
use Dotenv\Dotenv;

// Load environment variables.
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Configure the SDK.
$config = ConfigurationBuilder::create()
    ->withRegion($_ENV['IBM_WATSONX_REGION'] ?? 'us-south')
    ->withEnvironment('development')
    ->build();

// Get the WatsonX AI client.
$watsonx = $config->createWatsonXClient();

echo "=== IBM WatsonX Chat API Example ===\n\n";

// Example 1: Simple chat conversation.
echo "1. Simple Chat Conversation\n";
echo str_repeat('-', 40) . "\n";

$messages = [
    ChatMessage::user("What is the capital of France?"),
];

// Note: The chat API may require a deployment ID rather than a model ID
// For testing, we're using a model ID but you may need to create a deployment first
$request = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),  // Try a different model
    $messages
)->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID']);

try {
    $result = $watsonx->chat($request);

    echo "User: What is the capital of France?\n";
    echo "Assistant: " . $result->getContent() . "\n\n";

    // Continue the conversation.
    $messages[] = ChatMessage::assistant($result->getContent());
    $messages[] = ChatMessage::user("What is the population of that city?");

    $request2 = ChatRequest::create(
        ModelId::from('mistralai/mistral-large'),
        $messages
    )->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID']);

    $result2 = $watsonx->chat($request2);

    echo "User: What is the population of that city?\n";
    echo "Assistant: " . $result2->getContent() . "\n\n";

    // Display token usage.
    if ($usage = $result2->getUsage()) {
        echo "Token Usage:\n";
        echo "  Prompt: " . $usage->getPromptTokens() . "\n";
        echo "  Completion: " . $usage->getCompletionTokens() . "\n";
        echo "  Total: " . $usage->getTotalTokens() . "\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 2: Chat with context injection.
echo "2. Chat with Context Injection\n";
echo str_repeat('-', 40) . "\n";

$contextMessages = [
    ChatMessage::user("Who are you and what day is tomorrow?"),
];

$contextRequest = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $contextMessages
)
->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID'])
->withContext("Today is Wednesday, December 20, 2023. You are an AI assistant created by IBM.");

try {
    $contextResult = $watsonx->chat($contextRequest);

    echo "Context: Today is Wednesday, December 20, 2023. You are an AI assistant created by IBM.\n";
    echo "User: Who are you and what day is tomorrow?\n";
    echo "Assistant: " . $contextResult->getContent() . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 3: Chat with generation parameters.
echo "3. Chat with Generation Parameters\n";
echo str_repeat('-', 40) . "\n";

$parameters = GenerationParameters::create()
    ->maxNewTokens(150)
    ->temperature(Temperature::from(0.7))
    ->topP(0.9)
    ->topK(50)
    ->repetitionPenalty(1.1)
    ->stopSequences(["\n\n", "User:"]);

$paramMessages = [
    ChatMessage::user("Write a short poem about coding in PHP."),
];

$paramRequest = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $paramMessages
)
->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID'])
->withParameters($parameters);

try {
    $paramResult = $watsonx->chat($paramRequest);

    echo "User: Write a short poem about coding in PHP.\n";
    echo "Assistant:\n" . $paramResult->getContent() . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 4: Chat with JSON response mode.
echo "4. Chat with JSON Response Mode\n";
echo str_repeat('-', 40) . "\n";

$jsonMessages = [
    ChatMessage::user("List 3 popular PHP frameworks with their key features. Return as JSON."),
];

$jsonRequest = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $jsonMessages
)
->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID'])
->withJsonMode();

try {
    $jsonResult = $watsonx->chat($jsonRequest);

    echo "User: List 3 popular PHP frameworks with their key features. Return as JSON.\n";
    echo "Assistant:\n";

    $response = $jsonResult->getContent();
    echo $response . "\n\n";

    // Try to parse the JSON.
    $parsed = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Parsed JSON successfully!\n\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Example 5: Chat with tool/function calling.
echo "5. Chat with Tool/Function Calling\n";
echo str_repeat('-', 40) . "\n";

// Define available tools.
$tools = [
    ChatTool::function(
        'get_weather',
        'Get the current weather for a location',
        ChatTool::createParameterSchema(
            [
                'location' => ChatTool::stringParam('The city and country, e.g. San Francisco, USA'),
                'unit' => ChatTool::stringParam('Temperature unit', ['celsius', 'fahrenheit']),
            ],
            ['location'] // required parameters
        )
    ),
    ChatTool::function(
        'calculate',
        'Perform mathematical calculations',
        ChatTool::createParameterSchema(
            [
                'expression' => ChatTool::stringParam('Mathematical expression to evaluate'),
            ],
            ['expression']
        )
    ),
];

$toolMessages = [
    ChatMessage::user("What's the weather like in Paris, France?"),
];

$toolRequest = ChatRequest::create(
    ModelId::from('mistralai/mistral-large'),
    $toolMessages
)
->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID'])
->withTools($tools, ChatToolChoice::auto());

try {
    $toolResult = $watsonx->chat($toolRequest);

    echo "User: What's the weather like in Paris, France?\n";

    if ($toolResult->hasToolCalls()) {
        echo "Assistant wants to call tools:\n";
        foreach ($toolResult->getAllToolCalls() as $toolCall) {
            echo "  - Function: " . $toolCall->getFunctionName() . "\n";
            echo "    Arguments: " . $toolCall->getArguments() . "\n";

            // In a real application, you would execute the function here
            // and add the result as a tool message to continue the conversation.
        }
    } else {
        echo "Assistant: " . $toolResult->getContent() . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}


echo "=== End of Examples ===\n";