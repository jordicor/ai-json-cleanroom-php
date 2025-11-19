# Test Suite for AI JSON Cleanroom (PHP)

Comprehensive test suite with unit tests, integration tests, and edge case validation.

## Quick Start

### Install Test Dependencies

```bash
# Install PHPUnit via Composer
composer install --dev
```

### Run All Tests

```bash
# Run all tests
composer test

# Or directly with PHPUnit
./vendor/bin/phpunit

# Run with verbose output
./vendor/bin/phpunit --verbose

# Run with coverage report (requires Xdebug)
composer test-coverage
./vendor/bin/phpunit --coverage-html coverage

# Run specific test file
./vendor/bin/phpunit tests/ExtractionTest.php

# Run specific test class
./vendor/bin/phpunit --filter ExtractionTest

# Run specific test method
./vendor/bin/phpunit --filter testExtractFromMarkdownJsonFence
```

## Test Organization

### Core Test Modules

- **`JsonEngineTest.php`** - Tests for JSON parsing engine
  - Parse simple and complex JSON
  - Unicode handling
  - Large numbers
  - Empty structures
  - Bytes input

- **`ExtractionTest.php`** - Tests for JSON extraction from mixed text
  - Markdown code fences (```json)
  - Generic code fences (```)
  - Balanced block extraction
  - Chatty AI responses
  - Multiple JSON blocks

- **`RepairTest.php`** - Tests for JSON repair strategies
  - Single quote fixing
  - Unquoted key quoting
  - PHP constant replacement (True/False/None → true/false/null)
  - JavaScript comment removal
  - Trailing comma removal
  - Smart quote normalization
  - Repair limits and safeguards

- **`TruncationTest.php`** - Tests for truncation detection
  - Truncated arrays and objects
  - Various truncation patterns
  - Ellipsis detection
  - Unterminated strings
  - Unbalanced braces/brackets

- **`SchemaValidationTest.php`** - Tests for JSON Schema validation
  - Type validation
  - Required fields
  - String constraints (minLength, maxLength, pattern)
  - Number constraints (minimum, maximum)
  - Array validation (minItems, maxItems, uniqueItems)
  - Enum and const
  - Nested objects
  - additionalProperties
  - anyOf combinator

- **`PathExpectationsTest.php`** - Tests for path-based validation
  - Simple and nested paths
  - Array wildcards (`[*]`)
  - Pattern matching
  - Enum-like constraints (`in`)
  - Numeric bounds
  - String length
  - Array size
  - Optional paths

- **`EdgeCasesTest.php`** - Edge cases and corner scenarios
  - UTF-8 multibyte characters
  - Emoji handling
  - Complex nesting
  - Large payloads

- **`Utf8HandlingTest.php`** - UTF-8 specific tests
  - Curly quotes in multibyte strings
  - Emoji and special characters
  - Character encoding validation

## Test Fixtures

Located in `tests/fixtures/`:

- **`simple.json`** - Simple JSON with basic types (~150 bytes)
- **`complex.json`** - Complex nested structure with arrays, objects (~3KB)
- **`malformed_mixed_quotes.json`** - JSON with mixed single/double quotes
- **`malformed_unquoted_keys.json`** - JSON with unquoted object keys
- **`truncated_array.json`** - Incomplete JSON array
- **`truncated_object.json`** - Incomplete JSON object

## Example Test Commands

### Run Tests by Category

```bash
# Test extraction functionality only
./vendor/bin/phpunit tests/ExtractionTest.php

# Test repair functionality only
./vendor/bin/phpunit tests/RepairTest.php

# Test schema validation only
./vendor/bin/phpunit tests/SchemaValidationTest.php
```

### Coverage Reports

```bash
# Generate HTML coverage report (requires Xdebug)
./vendor/bin/phpunit --coverage-html coverage

# Open coverage report
# Windows: start coverage/index.html
# Linux/Mac: xdg-open coverage/index.html

# Generate terminal coverage report
./vendor/bin/phpunit --coverage-text
```

### Running with Different PHP Versions

```bash
# Use specific PHP version
/usr/bin/php8.1 vendor/bin/phpunit
/usr/bin/php8.2 vendor/bin/phpunit
/usr/bin/php8.3 vendor/bin/phpunit
```

## Writing New Tests

### Test Structure

```php
<?php
/**
 * Description of test module.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class FeatureTest extends TestCase
{
    public function testSpecificBehavior(): void
    {
        // Arrange
        $inputData = '{"test": "value"}';

        // Act
        $result = validate_ai_json($inputData);

        // Assert
        $this->assertTrue($result->jsonValid);
        $this->assertEquals('value', $result->data['test']);
    }
}
```

### Using Fixtures

```php
public function testWithFixture(): void
{
    $simpleJson = loadFixture('simple.json');
    $malformedJson = loadFixture('malformed_mixed_quotes.json');

    $result = validate_ai_json($malformedJson);
    $this->assertTrue($result->jsonValid);
}
```

### Data Providers

```php
/**
 * @dataProvider validJsonProvider
 */
public function testValidJson(string $input, mixed $expected): void
{
    $result = validate_ai_json($input);
    $this->assertTrue($result->jsonValid);
    $this->assertEquals($expected, $result->data['field']);
}

public function validJsonProvider(): array
{
    return [
        'simple object' => ['{"field": "value"}', 'value'],
        'with number' => ['{"field": 123}', 123],
        'with boolean' => ['{"field": true}', true],
    ];
}
```

## Continuous Integration

These tests are designed to run in CI/CD pipelines:

```yaml
# Example GitHub Actions workflow
- name: Run tests
  run: |
    composer install --dev
    ./vendor/bin/phpunit --coverage-text
```

## Test Coverage Goals

- **Minimum target:** 80% code coverage
- **Ideal target:** 90%+ code coverage
- Focus on:
  - Edge cases
  - Error handling
  - Repair safeguards
  - Schema validation completeness

## Performance Considerations

Tests should run quickly:
- Full suite: < 5 seconds
- Individual test: < 100ms
- Use fixtures for large data
- Mock external dependencies

## Troubleshooting Tests

### PHPUnit Not Found

```bash
# Ensure Composer dependencies are installed
composer install --dev

# Check vendor directory
ls -la vendor/bin/phpunit
```

### Fixture Not Found

```bash
# Ensure fixtures directory exists
ls -la tests/fixtures/

# Check bootstrap.php is loaded
cat tests/bootstrap.php
```

### Extension Missing

```bash
# Check required extensions
php -m | grep mbstring
php -m | grep json

# Install missing extensions
# Ubuntu/Debian: sudo apt-get install php-mbstring
# macOS: brew install php
```

### Coverage Requires Xdebug

```bash
# Check if Xdebug is installed
php -v | grep Xdebug

# Install Xdebug
# Ubuntu/Debian: sudo apt-get install php-xdebug
# macOS: pecl install xdebug
```

## Contributing Tests

When adding new features:

1. Write tests first (TDD approach)
2. Ensure all existing tests pass
3. Add test for the new feature
4. Add tests for edge cases
5. Update this README if adding new test files

```bash
# Before committing
composer test
```

---

## Test Statistics

Run this command to see test statistics:

```bash
./vendor/bin/phpunit --testdox
```

This will show a readable list of all test methods as specifications.

---

**Questions?** Check the main README.md or open an issue on GitHub.
