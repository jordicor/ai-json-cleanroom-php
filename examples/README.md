# Examples - AI JSON Cleanroom (PHP)

Practical, ready-to-run examples demonstrating real-world usage patterns with AI JSON Cleanroom PHP.

## Quick Start

All examples are standalone PHP scripts that can be run directly:

```bash
# Run any example
php examples/basic_usage.php
php examples/openai_integration.php
php examples/anthropic_claude.php
```

## Example Index

### 📚 **basic_usage.php** - Fundamentals
**Best for:** First-time users, learning core features

Demonstrates:
- Extract JSON from markdown code fences
- Automatic repair of common mistakes
- Schema validation
- Truncation detection
- Path-based expectations
- Custom validation options

```bash
php examples/basic_usage.php
```

**No API keys required** - uses mock data

---

### 🤖 **openai_integration.php** - OpenAI Integration
**Best for:** Using with GPT-3.5, GPT-4, GPT-4o models

Demonstrates:
- Integration with OpenAI Chat API
- Retry logic with validation feedback
- Schema validation for OpenAI responses
- Handling JSON mode quirks
- Error recovery strategies

```bash
# Requires: composer require guzzlehttp/guzzle
# Set: OPENAI_API_KEY environment variable
php examples/openai_integration.php
```

**Requirements:**
- Guzzle HTTP client (`composer require guzzlehttp/guzzle`)
- `OPENAI_API_KEY` environment variable

---

### 💬 **anthropic_claude.php** - Anthropic Claude Integration
**Best for:** Using with Claude 3, Claude 3.5 models

Demonstrates:
- Handling Claude's chatty/verbose responses
- Extracting JSON from markdown-wrapped output
- Schema validation for Claude outputs
- Error handling specific to Claude

```bash
# Requires: composer require guzzlehttp/guzzle
# Set: ANTHROPIC_API_KEY environment variable
php examples/anthropic_claude.php
```

**Requirements:**
- Guzzle HTTP client (`composer require guzzlehttp/guzzle`)
- `ANTHROPIC_API_KEY` environment variable

---

### 🌊 **streaming_responses.php** - Streaming Patterns
**Best for:** Working with streaming APIs

Demonstrates:
- Collecting streaming chunks (simulated)
- Progressive truncation detection
- Buffer-based validation
- Best practices for stream processing
- Error recovery for incomplete streams

```bash
php examples/streaming_responses.php
```

**No API keys required** - simulates streaming with delays

---

### 🔄 **retry_logic_advanced.php** - Intelligent Retries
**Best for:** Production systems needing robust error handling

Demonstrates:
- Smart retry strategies based on error types
- Exponential backoff
- Building improved prompts from validation errors
- Progressive refinement
- Truncation-aware retries

```bash
php examples/retry_logic_advanced.php
```

**No API keys required** - uses mock generators

---

### 🔧 **custom_repair_hooks.php** - Domain-Specific Repairs
**Best for:** Advanced users with specific data transformation needs

Demonstrates:
- Creating custom repair hooks
- Domain-specific transformations
- Currency symbol removal
- Date format normalization
- Boolean string conversion
- Null variant handling

```bash
php examples/custom_repair_hooks.php
```

**No API keys required**

---

## Usage Patterns by Scenario

### Scenario 1: Simple Cleanup
**"I just need to parse AI output reliably"**

→ Start with `basic_usage.php`

### Scenario 2: Production API Integration
**"Integrating with OpenAI/Claude for production system"**

→ Use `openai_integration.php` or `anthropic_claude.php`
→ Add retry logic from `retry_logic_advanced.php`

### Scenario 3: Streaming Data
**"Processing streaming responses from AI APIs"**

→ Use `streaming_responses.php`

### Scenario 4: Complex Validation
**"Need strict schema compliance and retry on failure"**

→ Combine:
  - Schema validation from `basic_usage.php`
  - Retry logic from `retry_logic_advanced.php`
  - Error handling from integration examples

### Scenario 5: Custom Data Format
**"AI outputs domain-specific formats (medical, financial, etc.)"**

→ Use `custom_repair_hooks.php` as template
→ Create hooks for your specific patterns

---

## Common Patterns

### Pattern: Extract from Chatty AI

```php
<?php
require_once '../ai_json_cleanroom.php';

// Works with any AI model's verbose output
$chattyResponse = <<<'TEXT'
Sure! Here's the data:

```json
{"result": "success"}
```

Hope this helps!
TEXT;

$result = validate_ai_json($chattyResponse);
// $result->data = ["result" => "success"]
```

### Pattern: Retry with Feedback

```php
function generateWithRetry(string $prompt, array $schema, int $maxRetries = 3): ?array
{
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $aiOutput = callAiApi($prompt);
        $result = validate_ai_json($aiOutput, schema: $schema);

        if ($result->jsonValid) {
            return $result->data;
        }

        // Build feedback for next attempt
        if ($result->likelyTruncated) {
            $prompt .= "\n\nIMPORTANT: Previous response was cut off.";
        } else {
            $errors = array_map(fn($e) => "- {$e->message}", $result->errors);
            $prompt .= "\n\nFix these:\n" . implode("\n", $errors);
        }
    }

    throw new RuntimeException("Failed after retries");
}
```

### Pattern: Stream + Validate

```php
// Collect streaming chunks
$chunks = [];
foreach ($streamingResponse as $chunk) {
    $chunks[] = $chunk;
}

// Validate once complete
$fullOutput = implode('', $chunks);
$result = validate_ai_json($fullOutput);

if ($result->likelyTruncated) {
    // Request retry with higher max_tokens
    retryWithHigherLimit();
} elseif ($result->jsonValid) {
    // Success
    process($result->data);
}
```

### Pattern: Custom Domain Transform

```php
function repairMyFormat(string $text, ValidateOptions $options): array
{
    // Transform domain-specific patterns
    $modified = str_replace("MY_NULL", "null", $text);
    $changes = substr_count($text, "MY_NULL");
    return [$modified, $changes, ["my_repairs" => $changes]];
}

$options = new ValidateOptions();
$options->customRepairHooks = [repairMyFormat::class];

$result = validate_ai_json($aiOutput, options: $options);
```

---

## Installation Requirements

### Core Library

```bash
# No dependencies beyond PHP standard library
# Just require the file
require_once 'ai_json_cleanroom.php';
```

### For API Integration Examples

```bash
# Install Guzzle HTTP client
composer require guzzlehttp/guzzle

# Set API keys
export OPENAI_API_KEY="sk-..."
export ANTHROPIC_API_KEY="sk-ant-..."
```

---

## Customizing Examples

All examples are designed to be easily modified:

### Change API Model

```php
// OpenAI
$response = $client->post('https://api.openai.com/v1/chat/completions', [
    'json' => [
        'model' => 'gpt-4o-2024-08-06',  // ← Change this
        // ...
    ]
]);

// Anthropic
$response = $client->post('https://api.anthropic.com/v1/messages', [
    'json' => [
        'model' => 'claude-3-5-sonnet-20241022',  // ← Change this
        // ...
    ]
]);
```

### Adjust Schema

```php
// Make stricter
$schema = [
    'type' => 'object',
    'required' => ['field1', 'field2', 'field3'],  // ← Add more
    'additionalProperties' => false  // ← Disallow extras
];

// Make looser
$schema = [
    'type' => 'object',
    'properties' => [
        'field' => ['type' => 'string']
    ]
    // No required, additionalProperties allowed by default
];
```

### Configure Repairs

```php
// Strict (minimal repairs)
$options = new ValidateOptions();
$options->fixSingleQuotes = true;
$options->quoteUnquotedKeys = false;
$options->stripJsComments = false;
$options->maxTotalRepairs = 10;

// Permissive (more repairs)
$options = new ValidateOptions();
$options->fixSingleQuotes = true;
$options->quoteUnquotedKeys = true;
$options->stripJsComments = true;
$options->maxTotalRepairs = 500;
```

---

## Testing Examples

Run examples to verify your setup:

```bash
# Test all examples (no API keys needed)
php examples/basic_usage.php
php examples/streaming_responses.php
php examples/retry_logic_advanced.php
php examples/custom_repair_hooks.php

# Test with API keys
php examples/openai_integration.php
php examples/anthropic_claude.php
```

---

## Need Help?

- **Getting started?** → Run `basic_usage.php`
- **Specific API?** → Check integration examples
- **Production patterns?** → See retry and streaming examples
- **Custom formats?** → Study `custom_repair_hooks.php`
- **Still stuck?** → Open an issue on GitHub

---

**Next Steps:**
1. Run `basic_usage.php` to understand fundamentals
2. Try integration example for your AI provider
3. Adapt patterns to your use case
4. Build your own examples!

Happy parsing!
