<?php
/**
 * Tests for JSON parsing engine.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class JsonEngineTest extends TestCase
{
    public function testParseSimpleJson(): void
    {
        $simple = loadFixture('simple.json');
        $result = validate_ai_json($simple);

        $this->assertTrue($result->jsonValid);
        $this->assertIsArray($result->data);
        $this->assertNotEmpty($result->data);
    }

    public function testParseComplexJson(): void
    {
        $complex = loadFixture('complex.json');
        $result = validate_ai_json($complex);

        $this->assertTrue($result->jsonValid);
        $this->assertIsArray($result->data);
    }

    public function testParseEmptyObject(): void
    {
        $result = validate_ai_json('{}');

        $this->assertTrue($result->jsonValid);
        $this->assertIsArray($result->data);
        $this->assertEmpty($result->data);
    }

    public function testParseEmptyArray(): void
    {
        $result = validate_ai_json('[]');

        $this->assertTrue($result->jsonValid);
        $this->assertIsArray($result->data);
        $this->assertEmpty($result->data);
    }

    public function testParseLargeNumbers(): void
    {
        $json = '{"big": 9999999999999999, "small": -9999999999999999}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertIsNumeric($result->data['big']);
        $this->assertIsNumeric($result->data['small']);
    }

    public function testParseFloatingPoint(): void
    {
        $json = '{"pi": 3.14159, "e": 2.71828, "negative": -0.5}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertIsFloat($result->data['pi']);
        $this->assertIsFloat($result->data['e']);
        $this->assertIsFloat($result->data['negative']);
    }

    public function testParseBooleans(): void
    {
        $json = '{"yes": true, "no": false}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertTrue($result->data['yes']);
        $this->assertFalse($result->data['no']);
    }

    public function testParseNull(): void
    {
        $json = '{"value": null}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertNull($result->data['value']);
    }

    public function testParseNestedStructures(): void
    {
        $json = <<<'JSON'
{
  "level1": {
    "level2": {
      "level3": {
        "value": "deep"
      }
    }
  }
}
JSON;

        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('deep', $result->data['level1']['level2']['level3']['value']);
    }

    public function testParseArrayOfObjects(): void
    {
        $json = '[{"id": 1}, {"id": 2}, {"id": 3}]';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertCount(3, $result->data);
        $this->assertEquals(1, $result->data[0]['id']);
    }

    public function testParseObjectWithArrays(): void
    {
        $json = '{"items": [1, 2, 3], "names": ["a", "b", "c"]}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals([1, 2, 3], $result->data['items']);
        $this->assertEquals(['a', 'b', 'c'], $result->data['names']);
    }

    public function testParseUnicodeEscapes(): void
    {
        $json = '{"unicode": "\\u0048\\u0065\\u006C\\u006C\\u006F"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Hello', $result->data['unicode']);
    }

    public function testParseEscapedCharacters(): void
    {
        $json = '{"escaped": "Line 1\\nLine 2\\tTabbed\\r\\n"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertStringContainsString("\n", $result->data['escaped']);
        $this->assertStringContainsString("\t", $result->data['escaped']);
    }

    public function testInvalidJson(): void
    {
        $invalid = '{invalid json}';
        $result = validate_ai_json($invalid);

        $this->assertFalse($result->jsonValid);
        $this->assertNotEmpty($result->errors);
    }

    public function testParseBackendInfo(): void
    {
        $json = '{"test": "value"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertArrayHasKey('parse_backend', $result->info);
        $this->assertEquals('json', $result->info['parse_backend']);
    }

    public function testAlreadyParsedArray(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];
        $result = validate_ai_json($data);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice', $result->data['name']);
        $this->assertEquals(30, $result->data['age']);
    }
}
