<?php
/**
 * Tests for JSON repair strategies.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class RepairTest extends TestCase
{
    public function testRepairSingleQuotes(): void
    {
        $input = "{'name': 'Alice', 'age': 30}";
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['name']);
        $this->assertEquals(30, $result->data['age']);
    }

    public function testRepairUnquotedKeys(): void
    {
        $input = '{name: "Alice", age: 30}';
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['name']);
        $this->assertEquals(30, $result->data['age']);
    }

    public function testReplacePythonConstants(): void
    {
        $input = '{"active": True, "deleted": False, "parent": None}';
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertTrue($result->data['active']);
        $this->assertFalse($result->data['deleted']);
        $this->assertNull($result->data['parent']);
    }

    public function testStripJsComments(): void
    {
        $input = <<<'JSON'
{
  "name": "Alice",  // user name
  /* age field */ "age": 30
}
JSON;

        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['name']);
        $this->assertEquals(30, $result->data['age']);
    }

    public function testRemoveTrailingCommas(): void
    {
        $input = '{"items": [1, 2, 3,], "count": 3,}';
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals([1, 2, 3], $result->data['items']);
        $this->assertEquals(3, $result->data['count']);
    }

    public function testNormalizeCurlyQuotes(): void
    {
        $input = '{"text": "She said "hello" to me"}';
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertStringContainsString('hello', $result->data['text']);
    }

    public function testMultipleRepairsAtOnce(): void
    {
        $input = <<<'JSON'
{
  'name': 'Bob',
  age: 25,
  // User is active
  active: True,
  tags: ["python", "ai",],
}
JSON;

        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Bob', $result->data['name']);
        $this->assertEquals(25, $result->data['age']);
        $this->assertTrue($result->data['active']);
        $this->assertEquals(['python', 'ai'], $result->data['tags']);
        $this->assertNotEmpty($result->warnings);
    }

    public function testRepairLimitRespected(): void
    {
        // Create input with many issues
        $broken = str_repeat("{'key': 'value'},", 500);
        $input = "[{$broken}]";

        $options = new \ValidateOptions();
        $options->maxTotalRepairs = 10;

        $result = validate_ai_json($input, options: $options);

        // Should fail because repair limit is exceeded
        $this->assertFalse($result->jsonValid);
    }

    public function testDisableRepairs(): void
    {
        $input = "{'name': 'Alice'}";

        $options = new \ValidateOptions();
        $options->enableSafeRepairs = false;

        $result = validate_ai_json($input, options: $options);

        $this->assertFalse($result->jsonValid);
    }

    public function testDisableSpecificRepair(): void
    {
        $input = "{'name': 'Alice'}";

        $options = new \ValidateOptions();
        $options->fixSingleQuotes = false;

        $result = validate_ai_json($input, options: $options);

        $this->assertFalse($result->jsonValid);
    }

    public function testRepairMetadata(): void
    {
        $input = "{'name': 'Alice', age: 30}";
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertNotEmpty($result->warnings);

        // Check that repair metadata is present
        $repairWarning = null;
        foreach ($result->warnings as $warning) {
            if ($warning->code->value === 'repaired') {
                $repairWarning = $warning;
                break;
            }
        }

        $this->assertNotNull($repairWarning);
        $this->assertArrayHasKey('applied', $repairWarning->detail);
        $this->assertNotEmpty($repairWarning->detail['applied']);
    }

    public function testInnerUnescapedQuotes(): void
    {
        $input = '{"text": "She said "hello" and "goodbye""}';
        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertIsString($result->data['text']);
    }

    public function testNestedObjectRepair(): void
    {
        $input = <<<'JSON'
{
  user: {
    name: 'Alice',
    settings: {
      theme: 'dark',
      notifications: True
    }
  }
}
JSON;

        $result = validate_ai_json($input);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['user']['name']);
        $this->assertEquals('dark', $result->data['user']['settings']['theme']);
        $this->assertTrue($result->data['user']['settings']['notifications']);
    }

    public function testRepairDoesNotChangeValidJson(): void
    {
        $validJson = '{"name": "Alice", "age": 30, "active": true}';
        $result = validate_ai_json($validJson);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['name']);
        // Should have no warnings for already-valid JSON
        $this->assertEmpty($result->warnings);
    }
}
