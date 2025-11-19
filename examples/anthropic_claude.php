<?php
/**
 * Anthropic Claude Integration Example
 *
 * Demonstrates integration with Anthropic Claude API using AI JSON Cleanroom.
 *
 * Requirements:
 * - composer require guzzlehttp/guzzle
 * - ANTHROPIC_API_KEY environment variable
 *
 * Usage:
 * export ANTHROPIC_API_KEY="sk-ant-..."
 * php examples/anthropic_claude.php
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

// Check if Guzzle is available
if (!class_exists('\GuzzleHttp\Client')) {
    echo "⚠️  This example requires Guzzle HTTP client.\n";
    echo "Install it with: composer require guzzlehttp/guzzle\n";
    exit(1);
}

// Check for API key
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    echo "⚠️  ANTHROPIC_API_KEY environment variable not set.\n";
    echo "Set it with: export ANTHROPIC_API_KEY='sk-ant-...'\n";
    exit(1);
}

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Anthropic Claude Integration Example - AI JSON Cleanroom       ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Basic JSON extraction from Claude
echo str_repeat('=', 70) . "\n";
echo "Example 1: Extract JSON from Claude's Verbose Response\n";
echo str_repeat('=', 70) . "\n";

$client = new \GuzzleHttp\Client();

try {
    $response = $client->post('https://api.anthropic.com/v1/messages', [
        'headers' => [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'claude-3-5-haiku-20241022',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Generate a JSON object with user info for Alice Johnson, age 30, software engineer'
                ]
            ]
        ]
    ]);

    $data = json_decode($response->getBody(), true);
    $aiOutput = $data['content'][0]['text'];

    echo "Raw Claude Output:\n";
    echo str_repeat('-', 70) . "\n";
    echo substr($aiOutput, 0, 300) . (strlen($aiOutput) > 300 ? '...' : '') . "\n";
    echo str_repeat('-', 70) . "\n\n";

    // Claude often wraps JSON in markdown and adds explanations
    $result = validate_ai_json($aiOutput);

    if ($result->jsonValid) {
        echo "✅ Successfully extracted and validated JSON!\n";
        echo "Extraction source: {$result->info['source']}\n";
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

// Example 2: Schema validation with Claude
echo str_repeat('=', 70) . "\n";
echo "Example 2: Schema Validation for Claude Output\n";
echo str_repeat('=', 70) . "\n";

$schema = [
    'type' => 'object',
    'required' => ['company', 'employees'],
    'properties' => [
        'company' => [
            'type' => 'object',
            'required' => ['name', 'industry'],
            'properties' => [
                'name' => ['type' => 'string'],
                'industry' => ['type' => 'string'],
                'founded' => ['type' => 'integer']
            ]
        ],
        'employees' => [
            'type' => 'array',
            'minItems' => 2,
            'items' => [
                'type' => 'object',
                'required' => ['name', 'role'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'role' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/']
                ]
            ]
        ]
    ]
];

try {
    $response = $client->post('https://api.anthropic.com/v1/messages', [
        'headers' => [
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'model' => 'claude-3-5-haiku-20241022',
            'max_tokens' => 1024,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Generate a JSON object for a tech company with at least 2 employees. Include company name, industry, and employee details (name, role, email).'
                ]
            ]
        ]
    ]);

    $data = json_decode($response->getBody(), true);
    $aiOutput = $data['content'][0]['text'];

    // Validate against schema
    $result = validate_ai_json($aiOutput, schema: $schema);

    if ($result->jsonValid) {
        echo "✅ Schema validation passed!\n";
        echo "Company: {$result->data['company']['name']}\n";
        echo "Industry: {$result->data['company']['industry']}\n";
        echo "Employees:\n";
        foreach ($result->data['employees'] as $employee) {
            echo "  - {$employee['name']} ({$employee['role']})\n";
        }
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

// Example 3: Handling Claude's chatty responses
echo str_repeat('=', 70) . "\n";
echo "Example 3: Handling Multiple JSON Blocks\n";
echo str_repeat('=', 70) . "\n";

// Simulate Claude's typical chatty response
$simulatedResponse = <<<'TEXT'
I'll help you create a user profile. Here's the JSON object:

```json
{
  "name": "Charlie Davis",
  "age": 28,
  "occupation": "Data Scientist",
  "skills": ["Python", "Machine Learning", "Statistics"],
  "contact": {
    "email": "charlie@example.com",
    "phone": "+1-555-0123"
  }
}
```

This JSON structure includes:
- Basic information (name, age, occupation)
- A list of professional skills
- Contact information in a nested object

Let me know if you'd like me to add or modify anything!
TEXT;

echo "Simulated Claude Response:\n";
echo str_repeat('-', 70) . "\n";
echo $simulatedResponse . "\n";
echo str_repeat('-', 70) . "\n\n";

$result = validate_ai_json($simulatedResponse);

if ($result->jsonValid) {
    echo "✅ Successfully extracted JSON from chatty response!\n";
    echo "Extraction method: {$result->info['source']}\n";
    echo "\nExtracted Data:\n";
    print_r($result->data);
    echo "\nSkills: " . implode(', ', $result->data['skills']) . "\n";
    echo "Contact Email: {$result->data['contact']['email']}\n";
} else {
    echo "❌ Failed to extract JSON\n";
}

echo "\n";

// Example 4: Truncation detection
echo str_repeat('=', 70) . "\n";
echo "Example 4: Detecting Truncated Responses\n";
echo str_repeat('=', 70) . "\n";

$truncatedResponse = <<<'TEXT'
Here's the data:

```json
{
  "users": [
    {"name": "Alice", "age": 30, "email": "alice@example.com"},
    {"name": "Bob", "age": 25, "email": "bob@example.com"},
    {"name": "Charlie", "age":
TEXT;

echo "Truncated Response:\n";
echo str_repeat('-', 70) . "\n";
echo $truncatedResponse . "\n";
echo str_repeat('-', 70) . "\n\n";

$result = validate_ai_json($truncatedResponse);

if ($result->likelyTruncated) {
    echo "⚠️  Truncation detected!\n";
    echo "This typically means Claude hit the max_tokens limit.\n";
    echo "Truncation reasons:\n";
    $reasons = $result->errors[0]->detail['truncation_reasons'] ?? [];
    foreach ($reasons as $reason) {
        echo "  - $reason\n";
    }
    echo "\n💡 Tip: Increase max_tokens in your API call or request shorter output.\n";
} else {
    echo "✅ Response appears complete\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 70) . "\n";
