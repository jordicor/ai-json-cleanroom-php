<?php
/**
 * Basic usage examples for ai-json-cleanroom PHP.
 *
 * This script demonstrates common use cases for cleaning and validating
 * AI-generated JSON outputs.
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

// Example 1: Clean mixed markdown and JSON
echo str_repeat('=', 60) . "\n";
echo "Example 1: Extract from markdown\n";
echo str_repeat('=', 60) . "\n";

$markdownResponse = <<<'TEXT'
Sure! Here's the user data you requested:

```json
{
  'name': "Alice Johnson",
  active: True,
  "email": "alice@example.com",
  "age": 30,
}
```

Let me know if you need anything else!
TEXT;

$result = validate_ai_json($markdownResponse);
echo "Valid: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "Data: ";
print_r($result->data);
echo "Source: {$result->info['source']}\n";
echo "\n";

// Example 2: Repair common mistakes
echo str_repeat('=', 60) . "\n";
echo "Example 2: Automatic repair\n";
echo str_repeat('=', 60) . "\n";

$messyJson = <<<'JSON'
{
  'name': 'Bob Smith',
  age: 25,
  // User is active
  active: True,
  tags: ["python", "ai",],
}
JSON;

$result = validate_ai_json($messyJson);
echo "Valid: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "Data: ";
print_r($result->data);
if (!empty($result->warnings)) {
    echo "Repairs applied: ";
    print_r($result->warnings[0]->detail['applied'] ?? []);
}
echo "\n";

// Example 3: Schema validation
echo str_repeat('=', 60) . "\n";
echo "Example 3: Schema validation\n";
echo str_repeat('=', 60) . "\n";

$schema = [
    'type' => 'object',
    'required' => ['name', 'email'],
    'properties' => [
        'name' => ['type' => 'string', 'minLength' => 1],
        'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'],
        'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150]
    ]
];

$goodData = '{"name": "Charlie", "email": "charlie@test.com", "age": 28}';
$badData = '{"name": "", "email": "invalid-email", "age": 200}';

$resultGood = validate_ai_json($goodData, schema: $schema);
echo "Good data valid: " . ($resultGood->jsonValid ? 'yes' : 'no') . "\n";

$resultBad = validate_ai_json($badData, schema: $schema);
echo "Bad data valid: " . ($resultBad->jsonValid ? 'yes' : 'no') . "\n";
echo "Errors:\n";
foreach ($resultBad->errors as $error) {
    echo "  - {$error->path}: {$error->message}\n";
}
echo "\n";

// Example 4: Truncation detection
echo str_repeat('=', 60) . "\n";
echo "Example 4: Truncation detection\n";
echo str_repeat('=', 60) . "\n";

$truncated = '{"users": [{"name": "Alice", "age": 30}, {"name": "Bob", "age":';

$result = validate_ai_json($truncated);
echo "Truncated: " . ($result->likelyTruncated ? 'yes' : 'no') . "\n";
$reasons = $result->errors[0]->detail['truncation_reasons'] ?? 'N/A';
echo "Reasons: ";
if (is_array($reasons)) {
    echo implode(', ', $reasons) . "\n";
} else {
    echo "$reasons\n";
}
echo "\n";

// Example 5: Path expectations
echo str_repeat('=', 60) . "\n";
echo "Example 5: Path-based expectations\n";
echo str_repeat('=', 60) . "\n";

$expectations = [
    [
        'path' => 'users[*].email',
        'required' => true,
        'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'
    ],
    [
        'path' => 'users[*].status',
        'in' => ['active', 'inactive']
    ]
];

$dataWithUsers = <<<'JSON'
{
  "users": [
    {"name": "Alice", "email": "alice@test.com", "status": "active"},
    {"name": "Bob", "email": "bob@test.com", "status": "inactive"}
  ]
}
JSON;

$result = validate_ai_json($dataWithUsers, expectations: $expectations);
echo "Valid: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "All users have valid emails and status: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "\n";

// Example 6: Custom options
echo str_repeat('=', 60) . "\n";
echo "Example 6: Custom validation options\n";
echo str_repeat('=', 60) . "\n";

$options = new ValidateOptions();
$options->fixSingleQuotes = true;
$options->quoteUnquotedKeys = true;
$options->stripJsComments = true;
$options->normalizeCurlyQuotes = 'auto';
$options->maxTotalRepairs = 100;

$result = validate_ai_json($messyJson, options: $options);
echo "Valid: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "Backend used: {$result->info['parse_backend']}\n";
echo "Curly quotes normalized: " . ($result->info['curly_quotes_normalization_used'] ? 'yes' : 'no') . "\n";
echo "\n";

echo str_repeat('=', 60) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 60) . "\n";
