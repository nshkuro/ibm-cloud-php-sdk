<?php

/**
 * IBM WatsonX JSON Structure Output Example.
 *
 * This example demonstrates how to use the chat API to extract structured
 * data from political/news texts and return it as strictly validated JSON.
 */

require __DIR__ . '/../vendor/autoload.php';

use IBMCloud\Configuration\ConfigurationBuilder;
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

// Check required environment variables.
if (!isset($_ENV['IBM_WATSONX_PROJECT_ID'])) {
    echo "❌ Error: IBM_WATSONX_PROJECT_ID environment variable is not set.\n";
    echo "Please add it to your .env file.\n";
    exit(1);
}

echo "=== IBM WatsonX JSON Structure Output Example ===\n\n";
echo "Task: Extract subjective statements from political news text\n";
echo str_repeat('=', 70) . "\n\n";

// Define the system prompt for structured JSON extraction.
$systemPrompt = <<<'PROMPT'
You are a model designed to perform structured analysis of political and news texts.
Your task is to extract all subjective statements from the input text and return the result strictly as valid JSON matching the provided schema.

When given a text (news article, political speech, interview, etc.), you must:

1. Identify all statements that contain subjectivity (judgments, emotions, criticism, support, predictions, concerns).
2. For each subjective statement, extract:
   - quote (exact text)
   - speaker (person making the statement, or "unknown")
   - target (subject of the statement, or "unspecified")
   - category: judgment, emotion, prediction, concern, other
   - stance: pro, anti, neutral
   - polarity: positive, negative, neutral
   - modality: assertive, interrogative, evaluative, other
   - certainty: high, medium, low
   - evidentiality: first_hand, second_hand, inferred, reported
   - cues (linguistic markers of subjectivity)
   - confidence (0.0 to 1.0)
3. Statement IDs start from 1.
4. Set processed_at to current ISO 8601 timestamp.

Output ONLY the JSON object. No markdown, no commentary, no additional text.
PROMPT;

// Define the news article to analyze.
$newsArticle = <<<'ARTICLE'
Fine Gael's Heather Humphreys defends record on disability issue
Updated / Monday, 20 Oct 2025 23:23
Candidates are running out of time to make their pitch to the public
Candidates are running out of time to make their pitch to the public
By Fiachra Ó Cionnaith  Colman O'Sullivan  Paul Cunningham
Fine Gael Presidential Election candidate Heather Humphreys has defended her record on the issue of disability.

Speaking on the Big Interview with Colette Fitzpatrick on Virgin Media One the Fine Gael presidential candidate said her Green Paper on disability benefits was a consultation.

She said: "I did not defend any of the proposals - it was purely for discussion purposes, it was for no other reason than to have a conversation."

She said when people objected to the Green Paper, she withdrew it.

"What I said to my officials, is this is not working, back to the drawing board."

She said she was sorry that the family of Shane O'Farrell felt she had not done enough for them.

She said she had never been at a fox hunt or hare coursing: "But these are rural pursuits and there are rules and regulations around these events and once they are complied with, I support them."

She added that she did keep her promise to refresh her Irish and went to the Gaeltacht for a week but - "my problem is confidence".

Asked to say something positive about Catherine Connolly she said: "Well I think Catherine Connolly is a woman of conviction and you know I admire people who believe in themselves, they believe in what they want to do."

Ms Humphreys added that she and her family had received abuse on social media because of her running for presidency: "I've never seen the like of it before, the amount of sectarian abuse that has been out there in terms of about my religion and my traditions and where I come from."

Meanwhile, Ms Connolly dismissed suggestions from Fine Gael that she is anti-European.

Earlier, the Tánaiste Simon Harris criticised Ms Connolly for her record of voting against EU treaties in referenda.

Responding this afternoon, Ms Connolly said: "Being constructively critical of Europe is not anti Europe. It's actually pro-European. It actually goes back to its foundation as a peace process.

"I've articulated over and over again that I'm seriously concerned about the direction that Europe is going, in terms of increased militarisation."

Speaking as he arrived for a meeting of EU foreign ministers in Luxembourg earlier, Mr Harris said: "It's very hard to see how any candidate can say that they're pro-European or in favour of Europe and yet vote against every single European treaty.

"That's not the definition of pro-European. So I'm hoping this week, when people go out and vote for the president of Ireland, that they will vote to reflect our values."

He said Ireland was "proudly" at the heart of the European Union, and would be hosting the rotating presidency of the EU next year.

Mr Harris said it was a narrower ballot than people expected.

"But ultimately, there is a binary choice on the ballot on Friday, and I think in the hours and days ahead, when people look at the two candidates, I'd ask them to ask themselves the question, who is the candidate most closely aligned with my worldview?

"Who is the candidate most closely aligned with my values? Who is the candidate that will be the safest bet in terms of representing Ireland with pride and distinction at home and abroad and will never let our country down? And indeed, who is the candidate that is pro Europe? Because at a time where European unity is so important. I want Ireland to very clearly vote in that direction."

Electorate has choice between 'two propositions' - McDonald

Meanwhile, Sinn Féin leader Mary Lou McDonald said that the electorate had a choice next Friday between "two propositions".

Speaking in Stormont earlier, Ms McDonald said that one is Ms Connolly who is about maintaining neutrality, championing human rights democracy for peace, and being a voice in the Áras for young people, citizens with a disability and Irish unification over the next seven years.

She said it is "that proposition or alternatively a candidate who is essentialy an echo chamber for Government".

She said they need to keep the campaign momentum going strongly, encourage people to come out and cast their vote and confound the prediction that turnout will be low.

Ms McDonald said: "I hope people realise and appreciate the value and strength of the position of Uachtarán na hÉireann … that it can be a force and a voice for progress for unity and for advancement for the whole island. I would not be saying that anybody has anything in the bag at this point in time."

She said the campaign has been brilliant and unique in a number of respects, adding: "Not least the fact that in a cross party way, we have come out with others to back the candidacy of Catherine.

"The unity of purpose between ourselves and the combined opposition said to people we can come together and work in common cause."

Campaign continues

Meanwhile, Minister for Justice Jim O'Callaghan said that it is not correct or fair to criticise lawyers because of the actions of their clients.

His comments come after Fine Gael hit out at Ms Connolly's work as a barrister a number of years ago, including in home repossession cases.

Campaigning is continuing in the Presidential Election, with Ms Connolly and Ms Humphreys seeking to win over public support before this Friday's vote.

Additional reporting Eleanor Burnhill, Joe Mag Raollaigh, Tony Connelly
ARTICLE;

// Define the JSON schema as plain text (user can copy-paste their own schema).
$schemaJson = <<<'JSON'
{
  "type": "object",
  "properties": {
    "version": {
      "type": "string",
      "description": "Schema version"
    },
    "doc": {
      "type": "object",
      "properties": {
        "doc_id": {
          "type": "string",
          "description": "Document identifier"
        },
        "language": {
          "type": "string",
          "description": "Document language code"
        },
        "source": {
          "type": "string",
          "description": "Source type"
        },
        "processed_at": {
          "type": "string",
          "description": "Processing timestamp in ISO 8601 format"
        }
      },
      "required": ["doc_id", "language", "source", "processed_at"],
      "additionalProperties": false
    },
    "statements": {
      "type": "array",
      "description": "Array of extracted subjective statements",
      "items": {
        "type": "object",
        "properties": {
          "id": {
            "type": "integer",
            "description": "Statement ID",
            "minimum": 1
          },
          "quote": {
            "type": "string",
            "description": "Exact quote from the text"
          },
          "speaker": {
            "type": "string",
            "description": "Person making the statement"
          },
          "target": {
            "type": "string",
            "description": "Subject or target of the statement"
          },
          "category": {
            "type": "string",
            "description": "Statement category",
            "enum": ["judgment", "emotion", "prediction", "concern", "other"]
          },
          "stance": {
            "type": "string",
            "description": "Stance of the speaker",
            "enum": ["pro", "anti", "neutral"]
          },
          "polarity": {
            "type": "string",
            "description": "Emotional polarity",
            "enum": ["positive", "negative", "neutral"]
          },
          "modality": {
            "type": "string",
            "description": "Statement modality",
            "enum": ["assertive", "interrogative", "evaluative", "other"]
          },
          "certainty": {
            "type": "string",
            "description": "Certainty level",
            "enum": ["high", "medium", "low"]
          },
          "evidentiality": {
            "type": "string",
            "description": "Source of evidence",
            "enum": ["first_hand", "second_hand", "inferred", "reported"]
          },
          "cues": {
            "type": "array",
            "description": "Linguistic cues indicating subjectivity",
            "items": {
              "type": "string"
            }
          },
          "confidence": {
            "type": "number",
            "description": "Confidence score",
            "minimum": 0.0,
            "maximum": 1.0
          }
        },
        "required": [
          "id",
          "quote",
          "speaker",
          "target",
          "category",
          "stance",
          "polarity",
          "modality",
          "certainty",
          "evidentiality",
          "cues",
          "confidence"
        ],
        "additionalProperties": false
      }
    }
  },
  "required": ["version", "doc", "statements"],
  "additionalProperties": false
}
JSON;

// Create the chat messages.
$messages = [
    ChatMessage::system($systemPrompt),
    ChatMessage::user($newsArticle),
];

// Configure generation parameters for structured output.
$parameters = GenerationParameters::create()
    ->maxNewTokens(4000)
    ->minNewTokens(100)  // Ensure we get at least some output.
    ->temperature(Temperature::from(0.7))  // Lower temperature for more deterministic output.
    ->topP(0.95);

// Create the chat request with JSON schema validation.
$request = ChatRequest::create(
    ModelId::from('meta-llama/llama-4-maverick-17b-128e-instruct-fp8'),
    $messages
)
->withProjectId($_ENV['IBM_WATSONX_PROJECT_ID'])
->withParameters($parameters)
->withJsonSchema('SubjectiveStatementExtraction', $schemaJson, true);

try {
    echo "Analyzing news article and extracting subjective statements...\n";
    echo "Using JSON Schema for strict output validation.\n\n";

    $result = $watsonx->chat($request);

    $jsonResponse = $result->getContent();

    // Display full LLM response.
    echo str_repeat('=', 70) . "\n";
    echo "FULL LLM RESPONSE\n";
    echo str_repeat('=', 70) . "\n";
    echo $jsonResponse . "\n";
    echo str_repeat('=', 70) . "\n\n";

    echo "Response size: " . strlen($jsonResponse) . " characters\n\n";

    // Extract JSON from markdown if wrapped.
    $cleanedJson = $jsonResponse;
    if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $jsonResponse, $matches)) {
        $cleanedJson = trim($matches[1]);
        echo "⚠️  Extracted JSON from markdown wrapper\n\n";
    }

    // Parse and validate the JSON.
    $parsed = json_decode($cleanedJson, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ JSON Parsing Error: " . json_last_error_msg() . "\n\n";
        echo "Cleaned JSON (first 500 chars):\n";
        echo substr($cleanedJson, 0, 500) . "\n\n";
        exit(1);
    }

    echo "✅ JSON parsed successfully!\n\n";

    // Pretty-print the parsed JSON.
    echo "FORMATTED JSON OUTPUT:\n";
    echo str_repeat('=', 70) . "\n";
    echo json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    echo str_repeat('=', 70) . "\n\n";

    // Display analysis summary.
    echo "Analysis Summary:\n";
    echo str_repeat('-', 70) . "\n";
    echo "Version: " . ($parsed['version'] ?? 'N/A') . "\n";
    echo "Document ID: " . ($parsed['doc']['doc_id'] ?? 'N/A') . "\n";
    echo "Language: " . ($parsed['doc']['language'] ?? 'N/A') . "\n";
    echo "Source: " . ($parsed['doc']['source'] ?? 'N/A') . "\n";
    echo "Processed At: " . ($parsed['doc']['processed_at'] ?? 'N/A') . "\n";
    echo "Number of Statements: " . count($parsed['statements'] ?? []) . "\n\n";

    // Display all extracted statements.
    if (!empty($parsed['statements'])) {
        echo "ALL EXTRACTED SUBJECTIVE STATEMENTS:\n";
        echo str_repeat('=', 70) . "\n";

        foreach ($parsed['statements'] as $statement) {
            echo "\nStatement #{$statement['id']}:\n";
            echo "  Quote: \"{$statement['quote']}\"\n";
            echo "  Speaker: {$statement['speaker']}\n";
            echo "  Target: {$statement['target']}\n";
            echo "  Category: {$statement['category']}\n";
            echo "  Stance: {$statement['stance']}\n";
            echo "  Polarity: {$statement['polarity']}\n";
            echo "  Modality: {$statement['modality']}\n";
            echo "  Certainty: {$statement['certainty']}\n";
            echo "  Evidentiality: {$statement['evidentiality']}\n";
            echo "  Cues: " . implode(', ', $statement['cues']) . "\n";
            echo "  Confidence: " . number_format($statement['confidence'], 2) . "\n";
            echo str_repeat('-', 70) . "\n";
        }
    }

    echo "\n";

    // Display token usage.
    if ($usage = $result->getUsage()) {
        echo "\nTOKEN USAGE:\n";
        echo str_repeat('=', 70) . "\n";
        echo "  Prompt Tokens: " . $usage->getPromptTokens() . "\n";
        echo "  Completion Tokens: " . $usage->getCompletionTokens() . "\n";
        echo "  Total Tokens: " . $usage->getTotalTokens() . "\n";
        echo str_repeat('=', 70) . "\n\n";
    }

    // Validate schema compliance.
    echo "SCHEMA VALIDATION:\n";
    echo str_repeat('=', 70) . "\n";

    $schemaValid = true;
    $errors = [];

    // Check required top-level fields.
    if (!isset($parsed['version'])) {
        $errors[] = "Missing required field: version";
        $schemaValid = false;
    }
    if (!isset($parsed['doc'])) {
        $errors[] = "Missing required field: doc";
        $schemaValid = false;
    }
    if (!isset($parsed['statements'])) {
        $errors[] = "Missing required field: statements";
        $schemaValid = false;
    }

    // Check required doc fields.
    if (isset($parsed['doc'])) {
        $requiredDocFields = ['doc_id', 'language', 'source', 'processed_at'];
        foreach ($requiredDocFields as $field) {
            if (!isset($parsed['doc'][$field])) {
                $errors[] = "Missing required doc field: $field";
                $schemaValid = false;
            }
        }
    }

    // Check statements structure.
    if (isset($parsed['statements']) && is_array($parsed['statements'])) {
        $requiredStatementFields = [
            'id', 'quote', 'speaker', 'target', 'category', 'stance',
            'polarity', 'modality', 'certainty', 'evidentiality', 'cues', 'confidence'
        ];

        foreach ($parsed['statements'] as $index => $statement) {
            foreach ($requiredStatementFields as $field) {
                if (!isset($statement[$field])) {
                    $errors[] = "Statement #$index missing required field: $field";
                    $schemaValid = false;
                }
            }
        }
    }

    if ($schemaValid) {
        echo "✅ Schema validation PASSED!\n";
        echo "All required fields are present and valid.\n";
    } else {
        echo "❌ Schema validation FAILED:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo str_repeat('=', 70) . "\n\n";

    // Final summary.
    echo "EXECUTION SUMMARY:\n";
    echo str_repeat('=', 70) . "\n";
    echo "✅ JSON extraction: SUCCESS\n";
    echo "✅ JSON parsing: SUCCESS\n";
    echo "✅ Schema validation: " . ($schemaValid ? "SUCCESS" : "FAILED") . "\n";
    echo "📊 Statements extracted: " . count($parsed['statements'] ?? []) . "\n";
    echo "📝 Response size: " . strlen($jsonResponse) . " characters\n";
    if ($usage = $result->getUsage()) {
        echo "🔢 Total tokens used: " . $usage->getTotalTokens() . "\n";
    }
    echo str_repeat('=', 70) . "\n";

} catch (Exception $e) {
    echo "\n";
    echo str_repeat('=', 70) . "\n";
    echo "❌ ERROR OCCURRED\n";
    echo str_repeat('=', 70) . "\n";
    echo "Message: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    echo str_repeat('=', 70) . "\n";
    exit(1);
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "End of JSON Structure Output Example\n";
echo str_repeat('=', 70) . "\n";
