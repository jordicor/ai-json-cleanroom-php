# AI JSON Cleanroom (PHP)

![PHP](https://img.shields.io/badge/php-8.1+-777BB4.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

**Your AI returns broken JSON? Put this in between.**

Works with any AI model: ChatGPT, Claude, Gemini, Llama. Zero dependencies beyond PHP standard library.

Automatically extracts JSON from markdown/text, repairs common AI mistakes, validates structure.
Returns clean data when successful, detailed feedback for retries when not.

**This is the PHP port of [AI JSON Cleanroom](https://github.com/jordicor/ai-json-cleanroom).**

**Quick Links:** [Fast Track (2 min)](#fast-track-integration-in-3-steps) • [Why This Tool?](#why-you-need-this) • [Code Example](#quick-start) • [Install](#installation) • [Configuration Guide](#understanding-the-configuration-options) • [Troubleshooting](#troubleshooting-guide) • [Integrations](#real-world-integrations) • [Full Documentation ↓](#features-overview)

---

## Fast Track: Integration in 3 Steps

**Want to start using this right away?** Here's how:

1. **Download** the `ai_json_cleanroom.php` file to your project
2. **Include it** in your code: `require_once 'ai_json_cleanroom.php';`
3. **Done.** Start processing AI responses through `validate_ai_json()`

Ready in 2 minutes. Works immediately.

[Show me the code →](#quick-start) • [Why do I need this? →](#why-you-need-this)

---

## Why You Need This

**The situation:** You request JSON from your AI. Sometimes you receive:

| What you get | What breaks |
|-------------|-------------|
| `Sure! Here's the JSON: {"name": "Alice"}` | Extra text crashes `json_decode()` |
| `{'name': 'Alice'}` | Single quotes instead of double quotes |
| `{"users": [{"id": 1}, {"i` | Truncated mid-response (token limit) |

**Current solution:** Try/catch blocks, regex patterns, manual fixes, repeated API calls.

**This tool:** Handles all cases automatically. One function call.

---

## Installation

### Via Composer (Recommended)

```bash
composer require jordicor/ai-json-cleanroom-php
```

### Manual Installation

Download `ai_json_cleanroom.php` to your project:

```bash
wget https://raw.githubusercontent.com/jordicor/ai-json-cleanroom-php/main/ai_json_cleanroom.php
```

Then include it:

```php
<?php
require_once 'ai_json_cleanroom.php';
```

**Requirements:**
- PHP 8.1 or higher
- ext-mbstring (for proper UTF-8 handling)
- ext-json

**Ready.** Start using: `validate_ai_json($response)`

---

## Quick Start

```php
<?php
require_once 'ai_json_cleanroom.php';

// Anything your AI returns (messy, wrapped, incomplete)
$aiResponse = "Here's your data:\n```json\n{'name': 'Alice', age: 30}  // Invalid JSON syntax\n```\n";

// One line to clean and validate
$result = validate_ai_json($aiResponse);

if ($result->jsonValid) {
    print_r($result->data);  // Clean: ['name' => 'Alice', 'age' => 30]
} else {
    print_r($result->errors);  // Detailed error information
}
```

**Done.** No configuration needed. It works out of the box.

Check `$result->warnings` to see what was fixed automatically.

---

### What Just Happened?

The cleaner automatically:
- Found the JSON inside markdown code fence
- Fixed single quotes to double quotes
- Added quotes to the unquoted key `age`
- Removed the inline comment
- Validated the final structure

Processing time: ~1ms. Zero configuration required.

**Useful tip:** Check `$result->likelyTruncated` to detect when the AI hit its token limit. This saves unnecessary retry API calls.

---

## You're All Set

**That's everything you need.** The tool works immediately with smart defaults.

Everything below is optional documentation for:
- Understanding how the tool works internally
- Advanced configuration options
- Framework integrations (Laravel, Symfony, etc.)
- Your AI assistant to read and understand the full API

**For most users:** The sections above are sufficient. Start building.

**Want to learn more?** Continue reading below.

**💡 Found this useful?** Star the repo ⭐ to help others discover it!

---

## Features Overview

### 1. Smart Extraction

Automatically extracts JSON from various formats:

```php
// From markdown code fence
$markdown = 'Here is the data:\n```json\n{"status": "success"}\n```\n';
$result = validate_ai_json($markdown);
// Extracted: ["status" => "success"]

// From mixed text
$mixed = 'The result is {"status": "success"} as requested.';
$result = validate_ai_json($mixed);
// Extracted: ["status" => "success"]
```

### 2. Conservative Repair

Fixes common AI mistakes with configurable safeguards:

```php
// Single quotes → double quotes
$result = validate_ai_json("{'name': 'Alice'}");
// Repaired: ["name" => "Alice"]

// Boolean constants (True/False/None) → JSON
$result = validate_ai_json('{"active": True, "value": None}');
// Repaired: ["active" => true, "value" => null]

// Unquoted keys → quoted keys
$result = validate_ai_json('{name: "Alice", age: 30}');
// Repaired: ["name" => "Alice", "age" => 30]

// Comments removal
$result = validate_ai_json('{
  "name": "Alice",  // user name
  /* age field */ "age": 30
}');
// Repaired: ["name" => "Alice", "age" => 30]
```

**Safeguards:**
- Maximum modifications limit (default: 200 changes or 2% of input size)
- Disabled if truncation detected
- Incremental parse-check after each repair pass
- Detailed repair metadata in `$result->info`

### 3. Truncation Detection

Identifies incomplete outputs before wasting retries:

```php
$truncated = '{"users": [{"name": "Alice", "age": 30}, {"name": "Bob", "age":';

$result = validate_ai_json($truncated);
echo $result->likelyTruncated;  // true
echo $result->errors[0]->message;
// "No JSON payload found in input."
print_r($result->errors[0]->detail);
// ['truncation_reasons' => ['unclosed_braces_or_brackets', 'suspicious_trailing_character']]
```

**Detection signals:**
- Unclosed strings
- Unbalanced braces/brackets
- Suspicious trailing characters (`,`, `:`, `{`, `[`)
- Ellipsis at end (`...`)

### 4. Schema Validation

Validate against JSON Schema subset:

```php
$schema = [
    "type" => "object",
    "required" => ["name", "email"],
    "properties" => [
        "name" => [
            "type" => "string",
            "minLength" => 1,
            "maxLength" => 100
        ],
        "email" => [
            "type" => "string",
            "pattern" => '/^[\w\.-]+@[\w\.-]+\.\w+$/'
        ],
        "age" => [
            "type" => "integer",
            "minimum" => 0,
            "maximum" => 150
        ]
    ],
    "additionalProperties" => false
];

$result = validate_ai_json($aiOutput, schema: $schema);

if (!$result->jsonValid) {
    foreach ($result->errors as $error) {
        echo "{$error->code}: {$error->message} at {$error->path}\n";
    }
}
```

**Supported schema keywords:**
- Types: `object`, `array`, `string`, `number`, `integer`, `boolean`, `null`
- Object: `required`, `properties`, `patternProperties`, `additionalProperties`
- Array: `items`, `additionalItems`, `minItems`, `maxItems`, `uniqueItems`
- String: `minLength`, `maxLength`, `pattern`
- Number: `minimum`, `maximum`, `exclusiveMinimum`, `exclusiveMaximum`, `multipleOf`
- Combinators: `anyOf`, `oneOf`, `allOf`
- Constraints: `enum`, `const`, `allow_empty`

### 5. Path-Based Expectations

Validate specific paths with wildcard support:

```php
$expectations = [
    [
        "path" => "users[*].email",
        "required" => true,
        "pattern" => '/^[\w\.-]+@[\w\.-]+\.\w+$/'
    ],
    [
        "path" => "users[*].status",
        "required" => true,
        "in" => ["active", "pending", "inactive"]
    ],
    [
        "path" => "metadata.version",
        "required" => true,
        "type" => "string",
        "pattern" => '/^\d+\.\d+\.\d+$/'
    ]
];

$result = validate_ai_json($aiOutput, expectations: $expectations);
```

### 6. Non-Throwing API

Always returns a `ValidationResult` - never crashes:

```php
$result = validate_ai_json($anyInput);

// Always safe to access
echo "Valid: " . ($result->jsonValid ? 'yes' : 'no') . "\n";
echo "Truncated: " . ($result->likelyTruncated ? 'yes' : 'no') . "\n";
echo "Errors: " . count($result->errors) . "\n";
echo "Warnings: " . count($result->warnings) . "\n";
print_r($result->data);  // null if invalid
print_r($result->info);  // Extraction/parsing metadata

// Structured error handling
foreach ($result->errors as $error) {
    echo "Code: {$error->code}\n";
    echo "Path: {$error->path}\n";
    echo "Message: {$error->message}\n";
    print_r($error->detail);
}
```

---

## Understanding the Configuration Options

Not sure which options to enable? This guide explains each repair strategy with practical examples.

### When to Use Each Repair Strategy

#### `fixSingleQuotes` (Default: true)
**What it does:** Converts single quotes `'text'` to JSON-compliant double quotes `"text"`

**When to keep it ON:**
- Working with AI models that output single-quoted strings
- Processing outputs from code-generation models
- General use - this is safe and commonly needed

**When to turn it OFF:**
- Your AI model never uses single quotes (rare)
- You're processing pure JSON from a non-AI source

**Example scenario:**
```php
// GPT often returns this mix:
$input = "{'name': 'Alice', \"age\": 30}";  // Mixed quotes

// With fixSingleQuotes = true:
// ✅ Becomes: {"name": "Alice", "age": 30}

// With fixSingleQuotes = false:
// ❌ Parse fails on single quotes
```

#### `quoteUnquotedKeys` (Default: true)
**What it does:** Adds quotes to JavaScript-style unquoted object keys

**When to keep it ON:**
- Working with models trained on JavaScript/TypeScript code
- Processing outputs that might include object literals
- Claude models (sometimes output JS-style objects)

**When to turn it OFF:**
- Strict JSON-only environment
- You want to detect and reject JS-style syntax

**Real-world example:**
```php
// Claude sometimes returns:
$input = "{name: 'Alice', age: 30, active: true}";

// With quoteUnquotedKeys = true:
// ✅ Becomes: {"name": "Alice", "age": 30, "active": true}
```

#### `replaceConstants` (Default: true)
**What it does:** Converts capitalized boolean constants (`True`/`False`/`None`) to JSON (`true`/`false`/`null`)

**When to keep it ON:**
- Always, unless you have a specific reason not to
- Essential for AI models that output capitalized booleans

**Example:**
```php
// AI models sometimes output capitalized booleans:
$input = '{"active": True, "deleted": False, "parent": None}';

// With replaceConstants = true:
// ✅ Becomes: {"active": true, "deleted": false, "parent": null}
```

#### `stripJsComments` (Default: true)
**What it does:** Removes JavaScript-style comments (`//` and `/* */`)

**When to keep it ON:**
- Models that explain their JSON with comments
- When processing configuration-style outputs

**Example:**
```php
$input = <<<'JSON'
{
  "name": "Alice",  // user name
  /* age field */ "age": 30
}
JSON;
// ✅ Comments are safely removed
```

#### `normalizeCurlyQuotes` (Default: "always")
**What it does:** Handles smart/typographic quotes that break JSON parsing

**Options:**
- `"always"` - Convert smart quotes before parsing (safest)
- `"auto"` - Only convert if initial parse fails (balanced approach)
- `"never"` - Keep smart quotes as-is (when you want to preserve them)

**When to use each:**
- `"always"`: Default choice, handles copy-paste from documents
- `"auto"`: When performance matters and smart quotes are rare
- `"never"`: When processing content where quote style matters

**Example:**
```php
// From copy-paste or models trained on web text:
$input = '{"text": "She said "hello" to me"}';  // Smart quotes

// With normalizeCurlyQuotes = "always":
// ✅ Becomes: {"text": "She said \"hello\" to me"}
```

#### `enableSafeRepairs` (Default: true)
**What it does:** Master toggle for all repair strategies

**When to turn OFF:**
- You want to validate only, not repair
- Debugging to see raw parsing errors
- You have your own repair logic

#### `maxTotalRepairs` and `maxRepairsPercent` (Defaults: 200, 0.02)
**What they do:** Safety limits to prevent over-correction

**When to increase:**
- Very messy outputs from older models
- Known high-error scenarios

**When to decrease:**
- You want stricter validation
- Suspicious of too many modifications

**Example configuration:**
```php
// For very messy outputs:
$options = new ValidateOptions();
$options->maxTotalRepairs = 500;      // Allow more fixes
$options->maxRepairsPercent = 0.05;   // Allow 5% of content to be modified

// For strict validation:
$options = new ValidateOptions();
$options->maxTotalRepairs = 10;       // Minimal fixes only
$options->maxRepairsPercent = 0.001;  // Less than 0.1% modifications
```

> 📝 **Note:** Start with defaults. They're battle-tested on thousands of real AI outputs. Only adjust if you have specific issues.

---

## Common Scenarios & Solutions

### Scenario 1: "My AI model keeps adding explanations"

**The Problem:** You explicitly ask for JSON only, but get:
```
I'll help you with that! Here's the JSON data:
{"status": "success"}
Let me know if you need anything else!
```

**The Solution:**
```php
// Cleanroom automatically extracts the JSON part
$result = validate_ai_json($chattyResponse);
print_r($result->data);  // Just the JSON: ["status" => "success"]
echo $result->info['source'];  // Tells you where it found it: 'balanced_block'
```

### Scenario 2: "Token limits are cutting off my JSON"

**The Problem:** Large responses get truncated:
```json
{"users": [{"id": 1, "name": "Alice"}, {"id": 2, "na
```

**The Solution:**
```php
$result = validate_ai_json($truncatedResponse);

if ($result->likelyTruncated) {
    // You know exactly what happened
    echo "Response truncated - reasons: ";
    print_r($result->errors[0]->detail['truncation_reasons']);
    // Output: ['unclosed_braces_or_brackets', 'unterminated_string']

    // Smart retry with higher token limit
    retryWithHigherLimit();
}
```

### Scenario 3: "Mixed quote styles are breaking everything"

**The Problem:** Your AI model uses single quotes instead of valid JSON double quotes:
```php
$output = "{'users': [\"Alice\", \"Bob\"], 'count': 2}";
```

**The Solution:**
```php
$result = validate_ai_json($output);
// Automatically fixes to: ["users" => ["Alice", "Bob"], "count" => 2]
```

### Scenario 4: "I need to validate specific fields exist"

**The Problem:** You need certain fields but don't want full schema validation.

**The Solution:** Use path expectations:
```php
$expectations = [
    ["path" => "users[*].email", "required" => true],
    ["path" => "metadata.version", "pattern" => '/^\d+\.\d+\.\d+$/']
];

$result = validate_ai_json($aiOutput, expectations: $expectations);
// Validates that all users have emails and version is semver
```

### Scenario 5: "The JSON has comments and I want to keep the information"

**The Problem:** AI model adds helpful comments that contain important context:
```json
{
  "temperature": 0.7,  // Higher for creativity
  "max_tokens": 100   // Keep responses concise
}
```

**The Solution:**
```php
// First, extract with comments preserved to see them
$rawResponse = $aiOutput;

// Clean for parsing
$result = validate_ai_json($rawResponse);

// The comments are removed for valid JSON
print_r($result->data);  // ["temperature" => 0.7, "max_tokens" => 100]

// If you need the comments, parse them separately from $rawResponse
```

### Scenario 6: "Different AI models fail in different ways"

**The Problem:** GPT may use single quotes and unquoted keys, Claude wraps in markdown, Gemini may truncate.

**The Solution:** One configuration handles all:
```php
// Same code for ALL models
function cleanAnyAiOutput(string $output): array
{
    $result = validate_ai_json($output);  // Default options handle everything

    if ($result->jsonValid) {
        return $result->data;
    } elseif ($result->likelyTruncated) {
        throw new RuntimeException("Output truncated - increase token limit");
    } else {
        $errorMsg = implode(", ", array_map(fn($e) => $e->message, $result->errors));
        throw new RuntimeException("Could not parse: {$errorMsg}");
    }
}

// Works with GPT, Claude, Gemini, Llama, etc.
```

> ⚠️ **Important:** Truncation detection always runs first. If JSON is truncated, repairs are skipped to avoid corrupting partial data.

---

## Troubleshooting Guide

### "Why isn't my JSON being repaired?"

**Possible causes and solutions:**

1. **Truncation detected**
   - Cleanroom disables repairs for truncated input (safety measure)
   - Solution: Get complete output first, then retry

2. **Repair limit reached**
   - Default limit: 200 changes or 2% of input size
   - Solution: Increase limits if needed:
   ```php
   $options = new ValidateOptions();
   $options->maxTotalRepairs = 500;  // Raise limit
   $options->maxRepairsPercent = 0.05;  // Allow 5% modifications
   ```

3. **Specific repair disabled**
   - Check your options - maybe `fixSingleQuotes = false`?
   - Solution: Enable the specific repair you need

### "The parser says JSON is invalid but it looks fine to me"

**Common hidden issues:**
- Invisible Unicode characters (zero-width spaces, etc.)
- Smart quotes from copy-paste: `"text"` vs `"text"`
- Line breaks inside strings without proper escaping

**Diagnosis:**
```php
$result = validate_ai_json($yourInput, options: new ValidateOptions([
    'normalizeCurlyQuotes' => 'always'  // Fixes smart quotes
]));
print_r($result->errors);  // See specific character positions
```

### "It works with GPT but fails with Claude"

**Issue:** Different models have different quirks.

**Solution:** Check the extraction source:
```php
$result = validate_ai_json($claudeOutput);
echo "Found JSON in: {$result->info['source']}\n";
// 'code_fence' = markdown block
// 'balanced_block' = found in text
// 'raw' = was already clean
```

### "Performance is slow with large outputs"

**Solutions:**
1. **Disable unnecessary repairs:**
   ```php
   $options = new ValidateOptions();
   $options->stripJsComments = false;  // If you never have comments
   $options->normalizeCurlyQuotes = 'never';  // If you never have smart quotes
   ```

2. **Use opcache** (PHP's bytecode cache):
   ```php
   // Check if opcache is enabled
   echo opcache_get_status()['opcache_enabled'] ? 'Enabled' : 'Disabled';
   ```

### "I want to see what was changed"

**Solution:** Check warnings and info:
```php
$result = validate_ai_json($messyJson);

// See all repairs applied
foreach ($result->warnings as $warning) {
    if ($warning->code === ErrorCode::REPAIRED) {
        echo "Repairs applied: " . implode(", ", $warning->detail['applied']) . "\n";
        echo "Number of changes: ";
        print_r($warning->detail['counts']);
    }
}

// See extraction details
echo "Extraction method: {$result->info['source']}\n";
echo "Parser used: {$result->info['parse_backend']}\n";
```

### "Schema validation is rejecting valid data"

**Common issues:**
1. **Pattern escaping:** Remember to use delimiters in PHP regex: `'/^\d+$/'` not `'^\d+$'`
2. **Type mismatches:** JSON numbers include floats - use `"type" => "number"` not `"integer"` unless you're sure
3. **Required fields:** Double-check field names are exact matches

**Debug approach:**
```php
// Start without schema to see actual structure
$result = validate_ai_json($output);
print_r($result->data);

// Then add schema gradually
$schema = ["type" => "object"];  // Start simple
// Add requirements one by one
```

### "mbstring extension not found"

**Issue:** PHP complains about missing mbstring functions.

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-mbstring

# macOS with Homebrew
brew install php
# (mbstring is included by default)

# Windows
# Enable in php.ini:
extension=mbstring

# Verify installation
php -m | grep mbstring
```

---

## Real-World Integrations

### With OpenAI API

```php
<?php
require_once 'ai_json_cleanroom.php';

$apiKey = getenv('OPENAI_API_KEY');
$client = new \GuzzleHttp\Client();

$response = $client->post('https://api.openai.com/v1/chat/completions', [
    'headers' => [
        'Authorization' => "Bearer {$apiKey}",
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'model' => 'gpt-4',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant that outputs JSON.'],
            ['role' => 'user', 'content' => 'Generate user profile for Alice Johnson, age 30']
        ],
        'response_format' => ['type' => 'json_object']
    ]
]);

$data = json_decode($response->getBody(), true);
$aiOutput = $data['choices'][0]['message']['content'];

// Clean and validate
$result = validate_ai_json(
    $aiOutput,
    schema: [
        'type' => 'object',
        'required' => ['name', 'age'],
        'properties' => [
            'name' => ['type' => 'string'],
            'age' => ['type' => 'integer', 'minimum' => 0]
        ]
    ]
);

if ($result->jsonValid) {
    $userData = $result->data;
    echo "User: {$userData['name']}, Age: {$userData['age']}\n";
} else {
    echo "Validation failed:\n";
    foreach ($result->errors as $error) {
        echo "- {$error->message}\n";
    }
}
```

### With Anthropic Claude

```php
<?php
require_once 'ai_json_cleanroom.php';

$apiKey = getenv('ANTHROPIC_API_KEY');
$client = new \GuzzleHttp\Client();

$response = $client->post('https://api.anthropic.com/v1/messages', [
    'headers' => [
        'x-api-key' => $apiKey,
        'anthropic-version' => '2023-06-01',
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'model' => 'claude-3-5-sonnet-20241022',
        'max_tokens' => 1024,
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Generate a JSON object with user info for Alice, age 30'
            ]
        ]
    ]
]);

$data = json_decode($response->getBody(), true);
$aiOutput = $data['content'][0]['text'];

// Claude might return:
// "Here's the user data:\n```json\n{\"name\": \"Alice\", \"age\": 30}\n```\nLet me know if you need anything else!"

$result = validate_ai_json($aiOutput);

if ($result->jsonValid) {
    echo "Extracted data:\n";
    print_r($result->data);
    echo "Extraction source: {$result->info['source']}\n";  // 'code_fence'
} else {
    if ($result->likelyTruncated) {
        echo "Response was truncated, increasing max_tokens...\n";
    } else {
        echo "Validation errors:\n";
        print_r($result->errors);
    }
}
```

### Retry Logic with Structured Feedback

```php
<?php
require_once 'ai_json_cleanroom.php';

function generateWithRetry(string $prompt, array $schema, int $maxRetries = 3): ?array
{
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $aiOutput = callAiApi($prompt);  // Your AI API call

        $result = validate_ai_json($aiOutput, schema: $schema);

        if ($result->jsonValid) {
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
    }

    throw new RuntimeException("Failed to generate valid JSON after {$maxRetries} attempts");
}

// Usage
$schema = [
    'type' => 'object',
    'required' => ['name', 'email', 'age'],
    'properties' => [
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'],
        'age' => ['type' => 'integer', 'minimum' => 0]
    ]
];

$userData = generateWithRetry(
    'Generate a user profile for Alice Johnson',
    $schema
);
print_r($userData);
```

### With Laravel Framework

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiJsonService
{
    public function generateUserProfile(string $prompt): array
    {
        // Call AI API using Laravel HTTP client
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        $aiOutput = $response->json()['choices'][0]['message']['content'];

        // Clean and validate with ai-json-cleanroom
        $result = validate_ai_json(
            $aiOutput,
            schema: [
                'type' => 'object',
                'required' => ['name', 'email'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/']
                ]
            ]
        );

        if (!$result->jsonValid) {
            // Log validation errors
            \Log::warning('AI JSON validation failed', [
                'errors' => array_map(fn($e) => $e->message, $result->errors),
                'truncated' => $result->likelyTruncated
            ]);

            throw new \RuntimeException('Invalid AI response');
        }

        return $result->data;
    }
}
```

**Usage in Laravel controller:**
```php
<?php

namespace App\Http\Controllers;

use App\Services\AiJsonService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private AiJsonService $aiService)
    {
    }

    public function generateProfile(): JsonResponse
    {
        try {
            $userData = $this->aiService->generateUserProfile(
                'Generate a user profile for Alice Johnson'
            );

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
```

### With Symfony Framework

```php
<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiJsonProcessor
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {
    }

    public function processAiResponse(string $prompt): array
    {
        // Make API call using Symfony HTTP client
        $response = $this->httpClient->request('POST',
            'https://api.anthropic.com/v1/messages',
            [
                'headers' => [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'claude-3-5-sonnet-20241022',
                    'max_tokens' => 1024,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ]
            ]
        );

        $data = $response->toArray();
        $aiOutput = $data['content'][0]['text'];

        // Clean and validate
        $result = validate_ai_json($aiOutput);

        if (!$result->jsonValid) {
            throw new \RuntimeException(
                sprintf('AI JSON validation failed: %s',
                    implode(', ', array_map(fn($e) => $e->message, $result->errors))
                )
            );
        }

        return $result->data;
    }
}
```

**Configuration in services.yaml:**
```yaml
services:
    App\Service\AiJsonProcessor:
        arguments:
            $apiKey: '%env(ANTHROPIC_API_KEY)%'
```

### With Streaming Responses

```php
<?php
require_once 'ai_json_cleanroom.php';

function processStreamingResponse(string $apiUrl, array $headers, array $payload): array
{
    // Initialize streaming request
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_WRITEFUNCTION => function($curl, $data) use (&$chunks) {
            $chunks[] = $data;
            return strlen($data);
        }
    ]);

    // Collect all chunks
    $chunks = [];
    curl_exec($ch);
    curl_close($ch);

    // Combine chunks
    $fullOutput = implode('', $chunks);

    // Validate complete output
    $result = validate_ai_json($fullOutput);

    if ($result->likelyTruncated) {
        // Stream was truncated - reasons available
        error_log('Stream truncated: ' . json_encode($result->errors[0]->detail['truncation_reasons']));
        throw new RuntimeException('Response was truncated, consider retrying with higher limits');
    }

    if (!$result->jsonValid) {
        throw new RuntimeException('Failed to parse streamed JSON');
    }

    return $result->data;
}
```

### With Guzzle Async/Promises

```php
<?php
require_once 'ai_json_cleanroom.php';
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

function processMultipleAiRequests(array $prompts): array
{
    $client = new Client();
    $promises = [];

    // Create async requests
    foreach ($prompts as $key => $prompt) {
        $promises[$key] = $client->postAsync('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY'),
            ],
            'json' => [
                'model' => 'gpt-4',
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ]
        ]);
    }

    // Wait for all responses
    $responses = Promise\Utils::settle($promises)->wait();

    $results = [];
    foreach ($responses as $key => $response) {
        if ($response['state'] === 'fulfilled') {
            $data = json_decode($response['value']->getBody(), true);
            $aiOutput = $data['choices'][0]['message']['content'];

            // Validate each response
            $result = validate_ai_json($aiOutput);

            if ($result->jsonValid) {
                $results[$key] = $result->data;
            } else {
                $results[$key] = [
                    'error' => 'Validation failed',
                    'details' => array_map(fn($e) => $e->message, $result->errors)
                ];
            }
        } else {
            $results[$key] = ['error' => 'Request failed'];
        }
    }

    return $results;
}

// Usage
$prompts = [
    'user1' => 'Generate profile for Alice',
    'user2' => 'Generate profile for Bob',
    'user3' => 'Generate profile for Charlie',
];

$results = processMultipleAiRequests($prompts);
print_r($results);
```

---

## API Reference

### `validate_ai_json()`

Main validation function with comprehensive options.

```php
function validate_ai_json(
    string|array $inputData,
    ?array $schema = null,
    ?array $expectations = null,
    ?ValidateOptions $options = null
): ValidationResult
```

**Parameters:**
- `$inputData`: String or already-parsed array
- `$schema`: JSON Schema subset for validation
- `$expectations`: List of path-based validation rules
- `$options`: Configuration for parsing, extraction, and repair

**Returns:** `ValidationResult` with `jsonValid`, `errors`, `warnings`, `data`, and `info`

### `ValidationResult`

Result object returned by `validate_ai_json()`.

```php
class ValidationResult
{
    public bool $jsonValid;              // True if parsing and validation succeeded
    public bool $likelyTruncated;        // True if input appears truncated
    public array $errors;                // ValidationIssue[] - validation errors
    public array $warnings;              // ValidationIssue[] - non-blocking warnings
    public mixed $data;                  // Parsed JSON if valid, else null
    public array $info;                  // Extraction/parsing metadata

    public function toArray(): array;    // Convert result to associative array
}
```

**Metadata in `$info`:**
- `source`: How JSON was found (`"raw"`, `"code_fence"`, `"balanced_block"`, `"object"`)
- `extraction`: Details about extraction process
- `parse_backend`: Parser used (`"json"`)
- `curly_quotes_normalization_used`: Whether typographic quotes were normalized
- `repair`: Details about applied repairs (if any)

### `ValidationIssue`

Individual validation error or warning.

```php
class ValidationIssue
{
    public ErrorCode $code;              // Error type (enum)
    public string $path;                 // JSONPath where error occurred
    public string $message;              // Human-readable description
    public ?array $detail;               // Additional context

    public function toArray(): array;    // Convert issue to associative array
}
```

### `ValidateOptions`

Configuration for validation behavior.

```php
class ValidateOptions
{
    // Extraction options
    public bool $strict = false;
    public bool $extractJson = true;
    public bool $allowJsonInCodeFences = true;
    public bool $allowBareTopLevelScalars = false;
    public bool $tolerateTrailingCommas = true;
    public bool $stopOnFirstError = false;

    // Repair options
    public bool $enableSafeRepairs = true;
    public bool $allowJson5Like = true;         // Master toggle for JSON5-like repairs
    public bool $replaceConstants = true;        // True/False/None → true/false/null
    public bool $replaceNansInfinities = true;   // NaN/Infinity → null
    public int $maxTotalRepairs = 200;
    public float $maxRepairsPercent = 0.02;      // 2% of input size

    // Granular repair control
    public string $normalizeCurlyQuotes = "always";  // "always"|"auto"|"never"
    public bool $fixSingleQuotes = true;
    public bool $quoteUnquotedKeys = true;
    public bool $stripJsComments = true;

    // Custom repair hooks
    public ?array $customRepairHooks = null;
}
```

**Curly quotes normalization modes:**
- `"always"` (default): Normalize typographic quotes before parsing
- `"auto"`: Try parsing first; only normalize if parse fails
- `"never"`: Never normalize (preserves typographic quotes as-is)

### `ErrorCode`

Enumeration of validation error types.

```php
enum ErrorCode: string
{
    case PARSE_ERROR = 'parse_error';
    case TRUNCATED = 'truncated';
    case MISSING_REQUIRED = 'missing_required';
    case TYPE_MISMATCH = 'type_mismatch';
    case ENUM_MISMATCH = 'enum_mismatch';
    case CONST_MISMATCH = 'const_mismatch';
    case NOT_ALLOWED_EMPTY = 'not_allowed_empty';
    case ADDITIONAL_PROPERTY = 'additional_property';
    case PATTERN_MISMATCH = 'pattern_mismatch';
    case MIN_LENGTH = 'min_length';
    case MAX_LENGTH = 'max_length';
    case MIN_ITEMS = 'min_items';
    case MAX_ITEMS = 'max_items';
    case MINIMUM = 'minimum';
    case MAXIMUM = 'maximum';
    case REPAIRED = 'repaired';  // Warning: repair was applied
    // ... and more
}
```

---

## PHP-Specific Notes

### Differences from Python Version

1. **JSON Engine**: PHP uses native `json_decode()`/`json_encode()`. Unlike the Python version which can optionally use orjson for performance, PHP relies on its built-in JSON extension which is fast and reliable.
2. **Type System**: PHP 8.1+ enums and typed properties used throughout
3. **Arrays**: PHP associative arrays instead of Python dicts
4. **Namespace**: Functions are global (no module imports needed)
5. **Error Handling**: Non-throwing design (no exceptions from validate_ai_json)
6. **Regex Patterns**: PHP regex requires delimiters (e.g., `'/pattern/'` not `'pattern'`)

### UTF-8 Handling

This library requires **ext-mbstring** for proper UTF-8 multibyte character handling. All string operations use multibyte-safe functions (`mb_strlen()`, `mb_substr()`, `mb_str_split()`).

**Why mbstring is required:**
- Proper character counting for repair limits
- Correct string slicing in multibyte contexts
- Safe handling of emojis and international characters
- Prevention of string corruption during repairs

### Performance

PHP's native JSON parser (`ext-json`) is highly optimized and written in C. Performance characteristics:

#### Typical Processing Times

| Scenario | Time | Notes |
|----------|------|-------|
| Clean JSON (no repairs) | ~0.1-1ms | Direct `json_decode()` |
| Simple extraction + parse | ~1-2ms | From markdown code fence |
| Multiple repairs + parse | ~2-5ms | Fix quotes, constants, comments |
| Complex schema validation | ~5-20ms | Deep nested structure validation |
| Large payload (>100KB) | ~10-50ms | Depends on complexity |

#### Performance Optimization Tips

1. **Enable OPcache** (PHP's bytecode cache):
   ```ini
   ; In php.ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.interned_strings_buffer=8
   opcache.max_accelerated_files=4000
   ```

2. **Disable unnecessary repairs:**
   ```php
   $options = new ValidateOptions();
   $options->stripJsComments = false;  // If you never have comments
   $options->normalizeCurlyQuotes = 'never';  // If you never have smart quotes
   ```

3. **Use schema validation selectively:**
   - Schema validation adds overhead proportional to complexity
   - For simple checks, use path expectations instead
   - Only validate what you actually need

4. **For high-throughput scenarios:**
   ```php
   // Cache the ValidateOptions instance
   static $options = null;
   if ($options === null) {
       $options = new ValidateOptions([
           'maxTotalRepairs' => 100,  // Lower limit for faster processing
           'stopOnFirstError' => true  // Fail fast
       ]);
   }

   $result = validate_ai_json($input, options: $options);
   ```

#### Memory Usage

Memory consumption is proportional to input size:
- Small payloads (<10KB): ~100-500KB peak memory
- Medium payloads (10-100KB): ~500KB-2MB peak memory
- Large payloads (>100KB): ~2-10MB peak memory

The library processes inputs in a single pass where possible to minimize memory overhead.

#### Comparison with Python Version

While the Python version can use orjson for ~3-4x faster JSON parsing, PHP's native `json_decode()` is already quite fast (comparable to Python's stdlib json). The difference is negligible for most use cases (microseconds for typical AI outputs).

---

## Examples

See the [examples/](examples/) directory for complete, runnable examples:

- **`basic_usage.php`** - Core features demonstration
- **`openai_integration.php`** - OpenAI API integration
- **`anthropic_claude.php`** - Anthropic Claude integration
- **`streaming_responses.php`** - Handling streaming outputs
- **`retry_logic_advanced.php`** - Intelligent retry strategies
- **`custom_repair_hooks.php`** - Domain-specific repairs

Run any example:
```bash
php examples/basic_usage.php
```

---

## Should I Use This Tool?

### Quick Decision Guide

**Use AI JSON Cleanroom if you:**
- Work with any AI model (GPT, Claude, Gemini, Llama)
- Receive JSON wrapped in explanations or markdown
- Face token limit truncations
- Need detailed error messages for retries
- Want one solution for all AI quirks
- Value zero dependencies (stdlib only)
- Use Laravel, Symfony, or vanilla PHP

**You might not need it if you:**
- Only work with clean, guaranteed JSON
- Control token generation completely
- Never hit token limits
- Your AI model never adds explanatory text
- You have a custom parsing pipeline that already works

### Comparison with Common Approaches

**Your Current Approach** → **With Cleanroom**

| Without Cleanroom | With Cleanroom |
|-------------------|----------------|
| `try { json_decode(); }` | Always get a result, never crashes |
| Regex extraction | Automatic markdown/fence detection |
| Custom retry logic | Structured errors for targeted retries |
| "Is it truncated?" | Immediate truncation detection with reasons |
| Multiple fix attempts | One call handles everything |
| Scattered error handling | Unified validation pipeline |

### Real-World Use Cases

#### Use Case 1: AI-Powered SaaS Application
```php
// Before: Fragile and unreliable
try {
    $data = json_decode($aiOutput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Retry? Log? Give up? ¯\_(ツ)_/¯
    }
} catch (Exception $e) {
    // Something went wrong...
}

// After: Robust and informative
$result = validate_ai_json($aiOutput, schema: $userSchema);
if ($result->jsonValid) {
    return $result->data;  // ✅ Clean, validated data
} elseif ($result->likelyTruncated) {
    return retryWithHigherTokens();  // ✅ Know exactly what to do
} else {
    return buildRetryPrompt($result->errors);  // ✅ Targeted fixes
}
```

#### Use Case 2: Laravel API Endpoint
```php
// Clean AI responses reliably in your Laravel services
class AiService {
    public function getStructuredData(string $prompt): array {
        $aiResponse = $this->callAiApi($prompt);
        $result = validate_ai_json($aiResponse);

        if (!$result->jsonValid) {
            Log::warning('AI JSON validation failed', [
                'errors' => $result->errors,
                'truncated' => $result->likelyTruncated
            ]);
            throw new AiResponseException('Invalid response');
        }

        return $result->data;
    }
}
```

#### Use Case 3: Batch Processing
```php
// Process hundreds of AI outputs reliably
foreach ($aiOutputs as $output) {
    $result = validate_ai_json($output);

    if ($result->jsonValid) {
        $processed[] = $result->data;
    } elseif ($result->likelyTruncated) {
        $needsRetry[] = $output;
    } else {
        $failed[] = [
            'output' => $output,
            'errors' => $result->errors
        ];
    }
}
```

### The Bottom Line

If you've ever written code like this:

```php
// This is a common scenario...
try {
    $data = json_decode($aiOutput, true);
} catch (Exception $e) {
    // Try to extract JSON with regex
    preg_match('/\{.*\}/s', $aiOutput, $matches);
    if ($matches) {
        try {
            // Fix quotes maybe?
            $fixed = str_replace("'", '"', $matches[0]);
            $data = json_decode($fixed, true);
        } catch (Exception $e2) {
            // Give up
            throw new RuntimeException("Can't parse AI output");
        }
    }
}
```

Then yes, you need this tool. It handles all of that (and much more) in one line:

```php
$result = validate_ai_json($aiOutput);  // Done.
```

**Benefits:**
- ✅ No more silent failures
- ✅ No more guessing why parsing failed
- ✅ No more wasted API calls on truncated responses
- ✅ No more fragile regex patterns
- ✅ No more scattered error handling

---

## Testing

This library includes a comprehensive PHPUnit test suite.

### Run Tests

```bash
# Install dependencies
composer install

# Run all tests
composer test

# Run with coverage (requires Xdebug)
composer test-coverage

# Run specific test
./vendor/bin/phpunit tests/ExtractionTest.php
```

See [tests/README.md](tests/README.md) for detailed testing documentation.

---

## License

MIT License

Copyright (c) 2025 Jordi Cor

See [LICENSE](LICENSE) file for details.

---

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

## Support

- **Issues**: [GitHub Issues](https://github.com/jordicor/ai-json-cleanroom-php/issues)
- **Source**: [GitHub Repository](https://github.com/jordicor/ai-json-cleanroom-php)
- **Python Version**: [Original Project](https://github.com/jordicor/ai-json-cleanroom)

---

If you find this tool useful, please consider starring the repo! ⭐
