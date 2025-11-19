<?php
/**
 * Tests for path-based validation.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class PathExpectationsTest extends TestCase
{
    public function testSimplePath(): void
    {
        $expectations = [
            ['path' => 'name', 'required' => true, 'type' => 'string']
        ];

        $valid = '{"name": "Alice"}';
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"age": 30}';
        $result = validate_ai_json($invalid, expectations: $expectations);
        $this->assertFalse($result->jsonValid);
    }

    public function testNestedPath(): void
    {
        $expectations = [
            ['path' => 'user.name', 'required' => true],
            ['path' => 'user.email', 'required' => true]
        ];

        $valid = '{"user": {"name": "Alice", "email": "alice@test.com"}}';
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);
    }

    public function testArrayWildcard(): void
    {
        $expectations = [
            ['path' => 'users[*].email', 'required' => true, 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/']
        ];

        $valid = <<<'JSON'
{
  "users": [
    {"email": "alice@test.com"},
    {"email": "bob@test.com"}
  ]
}
JSON;
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $invalid = <<<'JSON'
{
  "users": [
    {"email": "alice@test.com"},
    {"email": "invalid"}
  ]
}
JSON;
        $result = validate_ai_json($invalid, expectations: $expectations);
        $this->assertFalse($result->jsonValid);
    }

    public function testInConstraint(): void
    {
        $expectations = [
            ['path' => 'status', 'in' => ['active', 'inactive', 'pending']]
        ];

        $valid = '{"status": "active"}';
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"status": "unknown"}';
        $result = validate_ai_json($invalid, expectations: $expectations);
        $this->assertFalse($result->jsonValid);
    }

    public function testPatternMatching(): void
    {
        $expectations = [
            ['path' => 'version', 'pattern' => '/^\d+\.\d+\.\d+$/']
        ];

        $valid = '{"version": "1.2.3"}';
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"version": "1.2"}';
        $result = validate_ai_json($invalid, expectations: $expectations);
        $this->assertFalse($result->jsonValid);
    }

    public function testNumericBounds(): void
    {
        $expectations = [
            ['path' => 'age', 'minimum' => 0, 'maximum' => 150]
        ];

        $valid = '{"age": 30}';
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"age": 200}';
        $result = validate_ai_json($invalid, expectations: $expectations);
        $this->assertFalse($result->jsonValid);
    }

    public function testOptionalPath(): void
    {
        $expectations = [
            ['path' => 'optional_field', 'required' => false, 'type' => 'string']
        ];

        $withField = '{"optional_field": "value"}';
        $result = validate_ai_json($withField, expectations: $expectations);
        $this->assertTrue($result->jsonValid);

        $withoutField = '{"other_field": "value"}';
        $result = validate_ai_json($withoutField, expectations: $expectations);
        $this->assertTrue($result->jsonValid); // Should pass because field is optional
    }

    public function testMultipleExpectations(): void
    {
        $expectations = [
            ['path' => 'users[*].name', 'required' => true],
            ['path' => 'users[*].email', 'required' => true, 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/'],
            ['path' => 'users[*].status', 'in' => ['active', 'inactive']],
            ['path' => 'metadata.version', 'required' => true]
        ];

        $valid = <<<'JSON'
{
  "users": [
    {"name": "Alice", "email": "alice@test.com", "status": "active"},
    {"name": "Bob", "email": "bob@test.com", "status": "inactive"}
  ],
  "metadata": {"version": "1.0.0"}
}
JSON;
        $result = validate_ai_json($valid, expectations: $expectations);
        $this->assertTrue($result->jsonValid);
    }
}
