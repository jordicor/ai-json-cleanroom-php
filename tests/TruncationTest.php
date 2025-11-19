<?php
/**
 * Tests for truncation detection.
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class TruncationTest extends TestCase
{
    public function testDetectTruncatedArray(): void
    {
        $truncated = '{"users": [{"name": "Alice", "age": 30}, {"name": "Bob", "age":';

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
        $this->assertNotEmpty($result->errors);
        $this->assertArrayHasKey('truncation_reasons', $result->errors[0]->detail ?? []);
    }

    public function testDetectTruncatedObject(): void
    {
        $truncated = '{"user": {"name": "Alice", "email":';

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
    }

    public function testDetectUnterminatedString(): void
    {
        $truncated = '{"message": "This is a message that never ends';

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
    }

    public function testDetectEllipsis(): void
    {
        $truncated = '{"data": [1, 2, 3, ...';

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
    }

    public function testDetectSuspiciousTrailingCharacter(): void
    {
        $testCases = [
            '{"name": "Alice",',
            '{"items": [1, 2, 3',
            '{"nested": {"key":',
            '{"array": [',
        ];

        foreach ($testCases as $truncated) {
            $result = validate_ai_json($truncated);
            $this->assertTrue($result->likelyTruncated, "Failed to detect truncation in: $truncated");
        }
    }

    public function testCompleteJsonNotTruncated(): void
    {
        $complete = '{"name": "Alice", "age": 30, "active": true}';

        $result = validate_ai_json($complete);

        $this->assertTrue($result->jsonValid);
        $this->assertFalse($result->likelyTruncated);
    }

    public function testTruncationDisablesRepairs(): void
    {
        // Even with repair-able issues, truncation should prevent repairs
        $truncated = "{'name': 'Alice', 'age':";

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
        // Repairs should not be applied to truncated data
    }

    public function testTruncationReasons(): void
    {
        $truncated = '{"users": [{"name": "Alice"';

        $result = validate_ai_json($truncated);

        $this->assertTrue($result->likelyTruncated);
        $this->assertNotEmpty($result->errors);

        $reasons = $result->errors[0]->detail['truncation_reasons'] ?? [];
        $this->assertNotEmpty($reasons);
        $this->assertIsArray($reasons);
    }

    public function testLoadTruncatedFixtures(): void
    {
        $truncatedArray = loadFixture('truncated_array.json');
        $result = validate_ai_json($truncatedArray);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);

        $truncatedObject = loadFixture('truncated_object.json');
        $result = validate_ai_json($truncatedObject);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
    }

    public function testTruncatedNestedStructure(): void
    {
        $truncated = <<<'JSON'
{
  "company": {
    "name": "Acme Corp",
    "employees": [
      {"name": "Alice", "role": "Engineer"},
      {"name": "Bob", "role":
JSON;

        $result = validate_ai_json($truncated);

        $this->assertFalse($result->jsonValid);
        $this->assertTrue($result->likelyTruncated);
    }
}
