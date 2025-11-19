<?php
/**
 * Streaming Responses Example
 *
 * Demonstrates patterns for handling streaming API responses.
 * This example simulates streaming by breaking a response into chunks.
 *
 * No API keys required - uses simulated streaming.
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Streaming Responses Example - AI JSON Cleanroom                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Basic streaming pattern
echo str_repeat('=', 70) . "\n";
echo "Example 1: Collect Streaming Chunks and Validate\n";
echo str_repeat('=', 70) . "\n";

// Simulate a complete response that will be streamed
$completeResponse = <<<'JSON'
{
  "users": [
    {"name": "Alice", "age": 30, "email": "alice@example.com"},
    {"name": "Bob", "age": 25, "email": "bob@example.com"},
    {"name": "Charlie", "age": 35, "email": "charlie@example.com"}
  ],
  "metadata": {
    "total": 3,
    "page": 1
  }
}
JSON;

// Simulate streaming by breaking into chunks
function simulateStreaming(string $content, int $chunkSize = 50): Generator
{
    $length = strlen($content);
    for ($i = 0; $i < $length; $i += $chunkSize) {
        yield substr($content, $i, $chunkSize);
        usleep(100000);  // 100ms delay to simulate network
    }
}

echo "Collecting streaming chunks...\n";
$chunks = [];
foreach (simulateStreaming($completeResponse) as $chunk) {
    $chunks[] = $chunk;
    echo ".";
}
echo " Done!\n\n";

// Validate complete output
$fullOutput = implode('', $chunks);
$result = validate_ai_json($fullOutput);

if ($result->jsonValid) {
    echo "✅ Stream validated successfully!\n";
    echo "Total users: " . count($result->data['users']) . "\n";
    echo "Metadata: Total={$result->data['metadata']['total']}, Page={$result->data['metadata']['page']}\n";
} else {
    echo "❌ Validation failed\n";
}

echo "\n";

// Example 2: Truncation detection in streaming
echo str_repeat('=', 70) . "\n";
echo "Example 2: Detect Truncated Stream\n";
echo str_repeat('=', 70) . "\n";

$truncatedStream = <<<'JSON'
{
  "products": [
    {"id": 1, "name": "Laptop", "price": 999.99},
    {"id": 2, "name": "Mouse", "price": 29.99},
    {"id": 3, "name": "Keyboard", "pri
JSON;

echo "Simulating truncated stream...\n";
$chunks = [];
foreach (simulateStreaming($truncatedStream) as $chunk) {
    $chunks[] = $chunk;
    echo ".";
}
echo " Done!\n\n";

$fullOutput = implode('', $chunks);
$result = validate_ai_json($fullOutput);

if ($result->likelyTruncated) {
    echo "⚠️  Truncation detected in stream!\n";
    echo "Truncation reasons:\n";
    $reasons = $result->errors[0]->detail['truncation_reasons'] ?? [];
    foreach ($reasons as $reason) {
        echo "  - $reason\n";
    }
    echo "\n💡 Recommendation: Increase max_tokens or request less data per stream.\n";
} elseif ($result->jsonValid) {
    echo "✅ Stream complete and valid\n";
} else {
    echo "❌ Stream invalid but not truncated\n";
}

echo "\n";

// Example 3: Progressive validation (buffered approach)
echo str_repeat('=', 70) . "\n";
echo "Example 3: Progressive Validation with Buffer\n";
echo str_repeat('=', 70) . "\n";

$largeResponse = <<<'JSON'
{
  "status": "success",
  "data": [
    {"id": 1, "value": 100},
    {"id": 2, "value": 200},
    {"id": 3, "value": 300},
    {"id": 4, "value": 400},
    {"id": 5, "value": 500}
  ],
  "timestamp": "2025-01-01T00:00:00Z"
}
JSON;

echo "Processing stream with progressive validation...\n\n";

$buffer = '';
$chunkCount = 0;
$lastCheckSize = 0;

foreach (simulateStreaming($largeResponse, 30) as $chunk) {
    $buffer .= $chunk;
    $chunkCount++;

    // Check every 100 bytes
    if (strlen($buffer) - $lastCheckSize >= 100) {
        $lastCheckSize = strlen($buffer);

        // Quick truncation check (don't repair)
        $tempOptions = new ValidateOptions();
        $tempOptions->enableSafeRepairs = false;
        $result = validate_ai_json($buffer, options: $tempOptions);

        if ($result->likelyTruncated) {
            echo "  [Chunk $chunkCount] Stream ongoing, {strlen($buffer)} bytes so far...\n";
        } elseif ($result->jsonValid) {
            echo "  [Chunk $chunkCount] ✅ Complete valid JSON received!\n";
            break;
        }
    }
}

echo "\nFinal validation...\n";
$finalResult = validate_ai_json($buffer);

if ($finalResult->jsonValid) {
    echo "✅ Stream complete and validated!\n";
    echo "Status: {$finalResult->data['status']}\n";
    echo "Data points: " . count($finalResult->data['data']) . "\n";
} else {
    echo "❌ Final validation failed\n";
}

echo "\n";

// Example 4: Error recovery patterns
echo str_repeat('=', 70) . "\n";
echo "Example 4: Error Recovery in Streaming\n";
echo str_repeat('=', 70) . "\n";

function processStreamWithRetry(string $content, int $maxRetries = 3): ?ValidationResult
{
    $chunks = [];

    foreach (simulateStreaming($content, 40) as $chunk) {
        $chunks[] = $chunk;
    }

    $fullOutput = implode('', $chunks);
    $result = validate_ai_json($fullOutput);

    if ($result->jsonValid) {
        return $result;
    }

    if ($result->likelyTruncated) {
        echo "⚠️  Stream truncated - would retry with higher max_tokens\n";
        return null;
    }

    // Try repair with more permissive options
    echo "🔧 Attempting repair...\n";
    $options = new ValidateOptions();
    $options->maxTotalRepairs = 500;
    $options->maxRepairsPercent = 0.05;

    $result = validate_ai_json($fullOutput, options: $options);

    if ($result->jsonValid) {
        echo "✅ Repair successful!\n";
        return $result;
    }

    echo "❌ Unable to recover\n";
    return null;
}

$messyStream = <<<'JSON'
{
  'status': 'success',
  data: [
    {id: 1, name: 'Item 1'},
    {id: 2, name: 'Item 2'}
  ]
}
JSON;

$result = processStreamWithRetry($messyStream);

if ($result) {
    echo "Successfully processed stream:\n";
    print_r($result->data);
} else {
    echo "Failed to process stream\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 70) . "\n";
