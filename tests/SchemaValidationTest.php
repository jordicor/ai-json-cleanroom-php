<?php
/**
 * Tests for JSON Schema validation.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class SchemaValidationTest extends TestCase
{
    public function testValidateRequiredFields(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['name', 'email'],
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string']
            ]
        ];

        $valid = '{"name": "Alice", "email": "alice@example.com"}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"name": "Alice"}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testValidateTypes(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
                'score' => ['type' => 'number']
            ]
        ];

        $valid = '{"name": "Alice", "age": 30, "active": true, "score": 95.5}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"name": "Alice", "age": "thirty"}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testValidateStringConstraints(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10],
                'email' => ['type' => 'string', 'pattern' => '/^[\w\.-]+@[\w\.-]+\.\w+$/']
            ]
        ];

        $valid = '{"name": "Alice", "email": "alice@test.com"}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"name": "", "email": "invalid"}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
        $this->assertGreaterThanOrEqual(2, count($result->errors));
    }

    public function testValidateNumberConstraints(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 150],
                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100]
            ]
        ];

        $valid = '{"age": 30, "score": 95.5}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"age": 200, "score": 150}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testValidateArrays(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 5,
                    'items' => ['type' => 'string']
                ]
            ]
        ];

        $valid = '{"tags": ["python", "ai", "ml"]}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"tags": []}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testValidateEnum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'pending']]
            ]
        ];

        $valid = '{"status": "active"}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"status": "unknown"}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testAdditionalPropertiesFalse(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string']
            ],
            'additionalProperties' => false
        ];

        $valid = '{"name": "Alice"}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"name": "Alice", "age": 30}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testNestedObjects(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['user'],
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'required' => ['name', 'email'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string']
                    ]
                ]
            ]
        ];

        $valid = '{"user": {"name": "Alice", "email": "alice@test.com"}}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"user": {"name": "Alice"}}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }

    public function testArrayOfObjects(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'users' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string']
                        ]
                    ]
                ]
            ]
        ];

        $valid = '{"users": [{"name": "Alice"}, {"name": "Bob"}]}';
        $result = validate_ai_json($valid, schema: $schema);
        $this->assertTrue($result->jsonValid);

        $invalid = '{"users": [{"name": "Alice"}, {"age": 30}]}';
        $result = validate_ai_json($invalid, schema: $schema);
        $this->assertFalse($result->jsonValid);
    }
}
