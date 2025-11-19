<?php
/**
 * Advanced Retry Logic Example
 *
 * Demonstrates intelligent retry strategies with validation feedback.
 * No API keys required - uses mock AI generators.
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Advanced Retry Logic Example - AI JSON Cleanroom               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Mock AI generator that gradually improves
class MockAIGenerator
{
    private int $attemptCount = 0;
    private array $responses;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public function generate(string $prompt): string
    {
        $response = $this->responses[$this->attemptCount] ?? end($this->responses);
        $this->attemptCount++;
        return $response;
    }

    public function reset(): void
    {
        $this->attemptCount = 0;
    }
}

// Example 1: Simple retry with exponential backoff
echo str_repeat('=', 70) . "\n";
echo "Example 1: Retry with Exponential Backoff\n";
echo str_repeat('=', 70) . "\n";

function retryWithBackoff(
    callable $operation,
    int $maxRetries = 3,
    int $baseDelayMs = 100
): mixed {
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $result = $operation();

            if ($result !== null) {
                return $result;
            }
        } catch (Exception $e) {
            echo "  ⚠️  Attempt " . ($attempt + 1) . " failed: {$e->getMessage()}\n";
        }

        if ($attempt < $maxRetries - 1) {
            $delay = $baseDelayMs * pow(2, $attempt);
            echo "  ⏳ Waiting {$delay}ms before retry...\n";
            usleep($delay * 1000);
        }
    }

    return null;
}

$generator = new MockAIGenerator([
    '{"invalid": syntax}',  // Attempt 1: broken
    '{"name": "Alice"',      // Attempt 2: truncated
    '{"name": "Alice", "age": 30}'  // Attempt 3: success
]);

$result = retryWithBackoff(function() use ($generator) {
    echo "  🔄 Calling AI...\n";
    $response = $generator->generate('Generate user');
    $result = validate_ai_json($response);

    if ($result->jsonValid) {
        echo "  ✅ Valid JSON received!\n";
        return $result->data;
    }

    echo "  ❌ Invalid JSON\n";
    return null;
}, maxRetries: 3, baseDelayMs: 50);

if ($result) {
    echo "\n📊 Final result:\n";
    print_r($result);
} else {
    echo "\n❌ Failed after all retries\n";
}

echo "\n";

// Example 2: Retry with structured feedback
echo str_repeat('=', 70) . "\n";
echo "Example 2: Retry with Validation Feedback\n";
echo str_repeat('=', 70) . "\n";

$feedbackGenerator = new MockAIGenerator([
    '{"name": "", "email": "invalid", "age": 200}',  // Validation errors
    '{"name": "Bob", "email": "bob@", "age": 25}',   // Still some errors
    '{"name": "Bob Smith", "email": "bob@example.com", "age": 25}'  // Success
]);

$schema = [
    'type' => 'object',
    'required' => ['name', 'email', 'age'],
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 1],
        'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'],
        'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150]
    ]
];

function retryWithFeedback(
    MockAIGenerator $generator,
    array $schema,
    int $maxRetries = 3
): ?array {
    $prompt = 'Generate user profile with name, email, and age';

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        echo "  📝 Attempt " . ($attempt + 1) . "/$maxRetries\n";
        echo "  Prompt: " . substr($prompt, 0, 80) . "...\n";

        $response = $generator->generate($prompt);
        $result = validate_ai_json($response, schema: $schema);

        if ($result->jsonValid) {
            echo "  ✅ Validation passed!\n";
            return $result->data;
        }

        // Build structured feedback
        echo "  ❌ Validation failed:\n";
        $errorMessages = [];
        foreach ($result->errors as $error) {
            echo "    - [{$error->path}] {$error->message}\n";
            $errorMessages[] = "{$error->path}: {$error->message}";
        }

        // Append feedback to prompt for next attempt
        $feedback = implode("\n", $errorMessages);
        $prompt = "Generate user profile with name, email, and age.\n\n" .
                  "Previous attempt had these issues:\n$feedback\n\n" .
                  "Please fix these and generate valid JSON.";
    }

    return null;
}

$result = retryWithFeedback($feedbackGenerator, $schema, maxRetries: 3);

if ($result) {
    echo "\n📊 Successfully generated:\n";
    echo "  Name: {$result['name']}\n";
    echo "  Email: {$result['email']}\n";
    echo "  Age: {$result['age']}\n";
} else {
    echo "\n❌ Failed to generate valid data\n";
}

echo "\n";

// Example 3: Smart retry based on error type
echo str_repeat('=', 70) . "\n";
echo "Example 3: Error-Type-Specific Retry Strategy\n";
echo str_repeat('=', 70) . "\n";

function smartRetry(string $response, array $schema): array
{
    $result = validate_ai_json($response, schema: $schema);

    $strategy = [
        'should_retry' => false,
        'reason' => '',
        'suggested_action' => '',
        'max_retries' => 3
    ];

    if ($result->jsonValid) {
        $strategy['reason'] = 'Success';
        return $strategy;
    }

    if ($result->likelyTruncated) {
        $strategy['should_retry'] = true;
        $strategy['reason'] = 'Truncation detected';
        $strategy['suggested_action'] = 'Increase max_tokens or reduce output size';
        $strategy['max_retries'] = 2;  // Quick retries for truncation
        return $strategy;
    }

    // Check error types
    $errorCodes = array_map(fn($e) => $e->code->value, $result->errors);

    if (in_array('parse_error', $errorCodes)) {
        $strategy['should_retry'] = true;
        $strategy['reason'] = 'Parse error - malformed JSON';
        $strategy['suggested_action'] = 'Request AI to output only valid JSON';
        $strategy['max_retries'] = 3;
    } elseif (in_array('missing_required', $errorCodes)) {
        $strategy['should_retry'] = true;
        $strategy['reason'] = 'Missing required fields';
        $strategy['suggested_action'] = 'Specify required fields explicitly';
        $strategy['max_retries'] = 2;
    } elseif (in_array('type_mismatch', $errorCodes) || in_array('pattern_mismatch', $errorCodes)) {
        $strategy['should_retry'] = true;
        $strategy['reason'] = 'Type/pattern validation failed';
        $strategy['suggested_action'] = 'Provide examples of correct format';
        $strategy['max_retries'] = 3;
    }

    return $strategy;
}

$testCases = [
    [
        'name' => 'Truncated response',
        'response' => '{"name": "Alice", "age":',
        'expected' => 'Truncation detected'
    ],
    [
        'name' => 'Malformed JSON',
        'response' => '{name: Alice}',
        'expected' => 'Parse error'
    ],
    [
        'name' => 'Missing fields',
        'response' => '{"name": "Alice"}',
        'expected' => 'Missing required fields'
    ],
    [
        'name' => 'Type mismatch',
        'response' => '{"name": "Alice", "email": "alice@test.com", "age": "thirty"}',
        'expected' => 'Type/pattern validation failed'
    ],
];

foreach ($testCases as $testCase) {
    echo "Test: {$testCase['name']}\n";
    $strategy = smartRetry($testCase['response'], $schema);

    echo "  Reason: {$strategy['reason']}\n";
    echo "  Should retry: " . ($strategy['should_retry'] ? 'Yes' : 'No') . "\n";

    if ($strategy['should_retry']) {
        echo "  Suggested action: {$strategy['suggested_action']}\n";
        echo "  Max retries: {$strategy['max_retries']}\n";
    }

    echo "\n";
}

// Example 4: Progressive refinement
echo str_repeat('=', 70) . "\n";
echo "Example 4: Progressive Refinement Strategy\n";
echo str_repeat('=', 70) . "\n";

$refinementGenerator = new MockAIGenerator([
    '{"users": [{"name": "Alice"}]}',  // Missing fields
    '{"users": [{"name": "Alice", "email": "alice"}]}',  // Invalid email
    '{"users": [{"name": "Alice", "email": "alice@example.com"}]}'  // Success
]);

$strictSchema = [
    'type' => 'object',
    'required' => ['users'],
    'properties' => [
        'users' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'required' => ['name', 'email'],
                'properties' => [
                    'name' => ['type' => 'string', 'minLength' => 1],
                    'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/']
                ]
            ]
        ]
    ]
];

echo "Attempting progressive refinement...\n\n";

for ($attempt = 0; $attempt < 3; $attempt++) {
    echo "Attempt " . ($attempt + 1) . ":\n";
    $response = $refinementGenerator->generate('Generate users');
    $result = validate_ai_json($response, schema: $strictSchema);

    if ($result->jsonValid) {
        echo "  ✅ Validation passed!\n";
        echo "  Generated users:\n";
        foreach ($result->data['users'] as $user) {
            echo "    - {$user['name']} ({$user['email']})\n";
        }
        break;
    } else {
        echo "  ❌ Validation failed\n";
        echo "  Issues found: " . count($result->errors) . "\n";
        foreach ($result->errors as $error) {
            echo "    - {$error->message}\n";
        }
    }

    echo "\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 70) . "\n";
