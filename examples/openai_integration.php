<?php
/**
 * OpenAI Integration Example
 *
 * Demonstrates integration with OpenAI Chat API using AI JSON Cleanroom.
 *
 * Requirements:
 * - composer require guzzlehttp/guzzle
 * - OPENAI_API_KEY environment variable
 *
 * Usage:
 * export OPENAI_API_KEY="sk-..."
 * php examples/openai_integration.php
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

// Check if Guzzle is available
if (!class_exists('\GuzzleHttp\Client')) {
    echo "⚠️  This example requires Guzzle HTTP client.\n";
    echo "Install it with: composer require guzzlehttp/guzzle\n";
    exit(1);
}

// Check for API key
$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    echo "⚠️  OPENAI_API_KEY environment variable not set.\n";
    echo "Set it with: export OPENAI_API_KEY='sk-...'\n";
    exit(1);
}

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  OpenAI Integration Example - AI JSON Cleanroom                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Simple JSON generation
echo str_repeat('=', 70) . "\n";
echo "Example 1: Generate User Profile with JSON Mode\n";
echo str_repeat('=', 70) . "\n";

$client = new \GuzzleHttp\Client();

try {
    $response = $client->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that outputs JSON.'
                ],
                [
                    'role' => 'user',
                    'content' => 'Generate a user profile for Alice Johnson, age 30, software engineer'
                ]
            ],
            'response_format' => ['type' => 'json_object']
        ]
    ]);

    $data = json_decode($response->getBody(), true);
    $aiOutput = $data['choices'][0]['message']['content'];

    echo "Raw AI Output:\n";
    echo substr($aiOutput, 0, 200) . (strlen($aiOutput) > 200 ? '...' : '') . "\n\n";

    // Clean and validate
    $result = validate_ai_json($aiOutput);

    if ($result->jsonValid) {
        echo "✅ Validation succeeded!\n";
        echo "Data:\n";
        print_r($result->data);
    } else {
        echo "❌ Validation failed!\n";
        foreach ($result->errors as $error) {
            echo "  - {$error->message}\n";
        }
    }

} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "❌ API request failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 2: With schema validation
echo str_repeat('=', 70) . "\n";
echo "Example 2: Generate with Schema Validation\n";
echo str_repeat('=', 70) . "\n";

$schema = [
    'type' => 'object',
    'required' => ['name', 'email', 'occupation'],
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 1],
        'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'],
        'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150],
        'occupation' => ['type' => 'string']
    ]
];

try {
    $response = $client->post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant that outputs JSON with user profiles.'
                ],
                [
                    'role' => 'user',
                    'content' => 'Generate a user profile for Bob Smith, age 25, data scientist, email bob@example.com'
                ]
            ],
            'response_format' => ['type' => 'json_object']
        ]
    ]);

    $data = json_decode($response->getBody(), true);
    $aiOutput = $data['choices'][0]['message']['content'];

    // Validate against schema
    $result = validate_ai_json($aiOutput, schema: $schema);

    if ($result->jsonValid) {
        echo "✅ Schema validation passed!\n";
        echo "User: {$result->data['name']}, Age: {$result->data['age']}, Occupation: {$result->data['occupation']}\n";
        echo "Email: {$result->data['email']}\n";
    } else {
        echo "❌ Schema validation failed!\n";
        foreach ($result->errors as $error) {
            echo "  - [{$error->path}] {$error->message}\n";
        }
    }

} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo "❌ API request failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Example 3: Retry logic with feedback
echo str_repeat('=', 70) . "\n";
echo "Example 3: Retry Logic with Validation Feedback\n";
echo str_repeat('=', 70) . "\n";

function generateWithRetry(
    \GuzzleHttp\Client $client,
    string $apiKey,
    string $prompt,
    array $schema,
    int $maxRetries = 3
): ?array {
    $systemPrompt = 'You are a helpful assistant that outputs valid JSON matching the requested schema.';

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        echo "Attempt " . ($attempt + 1) . "/$maxRetries...\n";

        try {
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'response_format' => ['type' => 'json_object']
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $aiOutput = $data['choices'][0]['message']['content'];

            $result = validate_ai_json($aiOutput, schema: $schema);

            if ($result->jsonValid) {
                echo "✅ Success on attempt " . ($attempt + 1) . "!\n";
                return $result->data;
            }

            // Build feedback for retry
            if ($result->likelyTruncated) {
                $prompt .= "\n\nIMPORTANT: Your previous response was truncated. Please ensure the complete JSON is returned.";
            } else {
                $errorMessages = array_map(
                    fn($e) => "- {$e->path}: {$e->message}",
                    $result->errors
                );
                $feedback = implode("\n", $errorMessages);
                $prompt .= "\n\nYour previous JSON had these issues:\n{$feedback}\n\nPlease fix these and return valid JSON.";
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            echo "❌ API request failed: " . $e->getMessage() . "\n";
            if ($attempt < $maxRetries - 1) {
                sleep(2);  // Wait before retry
            }
        }
    }

    return null;
}

$retrySchema = [
    'type' => 'object',
    'required' => ['products'],
    'properties' => [
        'products' => [
            'type' => 'array',
            'minItems' => 3,
            'items' => [
                'type' => 'object',
                'required' => ['name', 'price', 'in_stock'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'price' => ['type' => 'number', 'minimum' => 0],
                    'in_stock' => ['type' => 'boolean']
                ]
            ]
        ]
    ]
];

$result = generateWithRetry(
    $client,
    $apiKey,
    'Generate a JSON object with at least 3 products (name, price, in_stock)',
    $retrySchema,
    maxRetries: 3
);

if ($result) {
    echo "\n📦 Generated Products:\n";
    foreach ($result['products'] as $product) {
        $stock = $product['in_stock'] ? '✅ In Stock' : '❌ Out of Stock';
        echo "  - {$product['name']}: \${$product['price']} {$stock}\n";
    }
} else {
    echo "\n❌ Failed to generate valid data after all retries.\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 70) . "\n";
