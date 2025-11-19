<?php
/**
 * Custom Repair Hooks Example
 *
 * Demonstrates creating domain-specific repair hooks for AI JSON Cleanroom.
 * No API keys required.
 */

require_once __DIR__ . '/../ai_json_cleanroom.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║  Custom Repair Hooks Example - AI JSON Cleanroom                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

// Example 1: Currency symbol removal hook
echo str_repeat('=', 70) . "\n";
echo "Example 1: Remove Currency Symbols\n";
echo str_repeat('=', 70) . "\n";

function removeCurrencySymbols(string $text, ValidateOptions $options): array
{
    $modified = $text;
    $changes = 0;
    $metadata = [];

    // Remove common currency symbols before numbers
    $patterns = [
        '/"\$(\d+\.?\d*)"/u' => '"$1"',    // "$99.99" -> "99.99"
        '/"€(\d+\.?\d*)"/u' => '"$1"',     // "€99.99" -> "99.99"
        '/"£(\d+\.?\d*)"/u' => '"$1"',     // "£99.99" -> "99.99"
        '/"¥(\d+\.?\d*)"/u' => '"$1"',     // "¥99.99" -> "99.99"
    ];

    foreach ($patterns as $pattern => $replacement) {
        $count = 0;
        $modified = preg_replace($pattern, $replacement, $modified, -1, $count);
        $changes += $count;
    }

    $metadata['currency_symbols_removed'] = $changes;

    return [$modified, $changes, $metadata];
}

$priceData = <<<'JSON'
{
  "products": [
    {"name": "Laptop", "price": "$999.99"},
    {"name": "Mouse", "price": "$29.99"},
    {"name": "Keyboard", "price": "$79.99"}
  ]
}
JSON;

echo "Input with currency symbols:\n$priceData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = ['removeCurrencySymbols'];

$result = validate_ai_json($priceData, options: $options);

if ($result->jsonValid) {
    echo "✅ Successfully repaired and parsed!\n";
    echo "Products:\n";
    foreach ($result->data['products'] as $product) {
        echo "  - {$product['name']}: \${$product['price']}\n";
    }
}

echo "\n";

// Example 2: Date format normalization
echo str_repeat('=', 70) . "\n";
echo "Example 2: Normalize Date Formats\n";
echo str_repeat('=', 70) . "\n";

function normalizeDates(string $text, ValidateOptions $options): array
{
    $modified = $text;
    $changes = 0;
    $metadata = [];

    // Convert "MM/DD/YYYY" to "YYYY-MM-DD"
    $pattern = '/"(\d{2})\/(\d{2})\/(\d{4})"/';
    $modified = preg_replace_callback(
        $pattern,
        function($matches) use (&$changes) {
            $changes++;
            return "\"{$matches[3]}-{$matches[1]}-{$matches[2]}\"";
        },
        $modified
    );

    $metadata['dates_normalized'] = $changes;

    return [$modified, $changes, $metadata];
}

$dateData = <<<'JSON'
{
  "events": [
    {"name": "Conference", "date": "03/15/2025"},
    {"name": "Workshop", "date": "06/22/2025"},
    {"name": "Meetup", "date": "09/10/2025"}
  ]
}
JSON;

echo "Input with MM/DD/YYYY dates:\n$dateData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = ['normalizeDates'];

$result = validate_ai_json($dateData, options: $options);

if ($result->jsonValid) {
    echo "✅ Dates normalized!\n";
    echo "Events:\n";
    foreach ($result->data['events'] as $event) {
        echo "  - {$event['name']}: {$event['date']}\n";
    }
}

echo "\n";

// Example 3: Boolean string conversion
echo str_repeat('=', 70) . "\n";
echo "Example 3: Convert Boolean Strings\n";
echo str_repeat('=', 70) . "\n";

function convertBooleanStrings(string $text, ValidateOptions $options): array
{
    $modified = $text;
    $changes = 0;
    $metadata = [];

    // Convert string booleans to actual booleans
    $patterns = [
        '/"yes"/i' => 'true',
        '/"no"/i' => 'false',
        '/"on"/i' => 'true',
        '/"off"/i' => 'false',
        '/"enabled"/i' => 'true',
        '/"disabled"/i' => 'false',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $count = 0;
        $modified = preg_replace($pattern, $replacement, $modified, -1, $count);
        $changes += $count;
    }

    $metadata['boolean_strings_converted'] = $changes;

    return [$modified, $changes, $metadata];
}

$configData = <<<'JSON'
{
  "features": {
    "notifications": "enabled",
    "dark_mode": "yes",
    "analytics": "no",
    "auto_save": "on"
  }
}
JSON;

echo "Input with string booleans:\n$configData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = ['convertBooleanStrings'];

$result = validate_ai_json($configData, options: $options);

if ($result->jsonValid) {
    echo "✅ Boolean strings converted!\n";
    echo "Features:\n";
    foreach ($result->data['features'] as $feature => $enabled) {
        $status = $enabled ? '✅ Enabled' : '❌ Disabled';
        echo "  - $feature: $status\n";
    }
}

echo "\n";

// Example 4: Null variant handling
echo str_repeat('=', 70) . "\n";
echo "Example 4: Handle Various Null Representations\n";
echo str_repeat('=', 70) . "\n";

function normalizeNullVariants(string $text, ValidateOptions $options): array
{
    $modified = $text;
    $changes = 0;
    $metadata = [];

    // Convert various null representations
    $patterns = [
        '/"NULL"/i' => 'null',
        '/"nil"/i' => 'null',
        '/"undefined"/i' => 'null',
        '/"N\/A"/i' => 'null',
        '/"n\/a"/i' => 'null',
        '/""/' => 'null',  // Empty strings to null
    ];

    foreach ($patterns as $pattern => $replacement) {
        $count = 0;
        $modified = preg_replace($pattern, $replacement, $modified, -1, $count);
        $changes += $count;
    }

    $metadata['null_variants_normalized'] = $changes;

    return [$modified, $changes, $metadata];
}

$nullData = <<<'JSON'
{
  "user": {
    "name": "Alice",
    "middle_name": "N/A",
    "nickname": "NULL",
    "title": "",
    "suffix": "undefined"
  }
}
JSON;

echo "Input with null variants:\n$nullData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = ['normalizeNullVariants'];

$result = validate_ai_json($nullData, options: $options);

if ($result->jsonValid) {
    echo "✅ Null variants normalized!\n";
    echo "User data:\n";
    foreach ($result->data['user'] as $field => $value) {
        $display = $value ?? '(null)';
        echo "  - $field: $display\n";
    }
}

echo "\n";

// Example 5: Combining multiple hooks
echo str_repeat('=', 70) . "\n";
echo "Example 5: Chain Multiple Repair Hooks\n";
echo str_repeat('=', 70) . "\n";

$complexData = <<<'JSON'
{
  "order": {
    "id": 12345,
    "date": "01/15/2025",
    "items": [
      {"name": "Laptop", "price": "$999.99", "in_stock": "yes"},
      {"name": "Mouse", "price": "$29.99", "in_stock": "no"}
    ],
    "shipping": "NULL",
    "tracking": "N/A"
  }
}
JSON;

echo "Input with multiple issues:\n$complexData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = [
    'removeCurrencySymbols',
    'normalizeDates',
    'convertBooleanStrings',
    'normalizeNullVariants'
];

$result = validate_ai_json($complexData, options: $options);

if ($result->jsonValid) {
    echo "✅ All repairs applied successfully!\n";
    echo "Order:\n";
    echo "  ID: {$result->data['order']['id']}\n";
    echo "  Date: {$result->data['order']['date']}\n";
    echo "  Items:\n";
    foreach ($result->data['order']['items'] as $item) {
        $stock = $item['in_stock'] ? '✅' : '❌';
        echo "    - {$item['name']}: \${$item['price']} $stock\n";
    }
    $shipping = $result->data['order']['shipping'] ?? 'None';
    $tracking = $result->data['order']['tracking'] ?? 'None';
    echo "  Shipping: $shipping\n";
    echo "  Tracking: $tracking\n";

    // Check repair metadata
    if (!empty($result->warnings)) {
        echo "\n📊 Repairs applied:\n";
        foreach ($result->warnings as $warning) {
            if ($warning->code->value === 'repaired' && isset($warning->detail['applied'])) {
                foreach ($warning->detail['applied'] as $repair) {
                    echo "  - $repair\n";
                }
            }
        }
    }
}

echo "\n";

// Example 6: Domain-specific example - Medical data
echo str_repeat('=', 70) . "\n";
echo "Example 6: Medical Data Normalization (Domain-Specific)\n";
echo str_repeat('=', 70) . "\n";

function normalizeMedicalData(string $text, ValidateOptions $options): array
{
    $modified = $text;
    $changes = 0;
    $metadata = [];

    // Normalize temperature units (assume Fahrenheit, convert to Celsius)
    $modified = preg_replace_callback(
        '/"temperature":\s*"?(\d+\.?\d*)°?F"?/i',
        function($matches) use (&$changes) {
            $changes++;
            $fahrenheit = (float)$matches[1];
            $celsius = round(($fahrenheit - 32) * 5/9, 1);
            return "\"temperature\": $celsius";
        },
        $modified
    );

    // Normalize blood pressure format "120/80" to object
    $modified = preg_replace_callback(
        '/"blood_pressure":\s*"(\d+)\/(\d+)"/i',
        function($matches) use (&$changes) {
            $changes++;
            return "\"blood_pressure\": {\"systolic\": {$matches[1]}, \"diastolic\": {$matches[2]}}";
        },
        $modified
    );

    $metadata['medical_normalizations'] = $changes;

    return [$modified, $changes, $metadata];
}

$medicalData = <<<'JSON'
{
  "patient": {
    "name": "John Doe",
    "temperature": "98.6°F",
    "blood_pressure": "120/80",
    "heart_rate": 72
  }
}
JSON;

echo "Input with medical data:\n$medicalData\n\n";

$options = new ValidateOptions();
$options->customRepairHooks = ['normalizeMedicalData'];

$result = validate_ai_json($medicalData, options: $options);

if ($result->jsonValid) {
    echo "✅ Medical data normalized!\n";
    $patient = $result->data['patient'];
    echo "Patient: {$patient['name']}\n";
    echo "Temperature: {$patient['temperature']}°C\n";
    if (is_array($patient['blood_pressure'])) {
        $bp = $patient['blood_pressure'];
        echo "Blood Pressure: {$bp['systolic']}/{$bp['diastolic']} mmHg\n";
    }
    echo "Heart Rate: {$patient['heart_rate']} bpm\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "All examples completed!\n";
echo str_repeat('=', 70) . "\n";
echo "\n💡 Tip: Create custom hooks for your domain-specific data formats!\n";
