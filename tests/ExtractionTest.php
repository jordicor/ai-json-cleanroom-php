<?php
/**
 * Tests for JSON extraction from mixed text (markdown, code fences, etc).
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class ExtractionTest extends TestCase
{
    public function testExtractFromMarkdownJsonFence(): void
    {
        $markdown = <<<'TEXT'
Here's the data:

```json
{
  "name": "Charlie",
  "email": "charlie@example.com"
}
```

Done!
TEXT;

        $result = validate_ai_json($markdown);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Charlie', $result->data['name']);
        $this->assertEquals('charlie@example.com', $result->data['email']);
        $this->assertEquals('code_fence', $result->info['source']);
    }

    public function testExtractFromGenericFence(): void
    {
        $text = <<<'TEXT'
Here's the data:

```
{"status": "ok", "code": 200}
```

Done!
TEXT;

        $result = validate_ai_json($text);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('ok', $result->data['status']);
        $this->assertEquals(200, $result->data['code']);
    }

    public function testExtractFromMixedText(): void
    {
        $text = 'The API returned {"success": true, "message": "Data saved"} which is good.';
        $result = validate_ai_json($text);

        $this->assertTrue($result->jsonValid);
        $this->assertTrue($result->data['success']);
        $this->assertEquals('Data saved', $result->data['message']);
        $this->assertEquals('balanced_block', $result->info['source']);
    }

    public function testExtractArrayFromText(): void
    {
        $text = 'The items are [{"id": 1}, {"id": 2}, {"id": 3}] in the list.';
        $result = validate_ai_json($text);

        $this->assertTrue($result->jsonValid);
        $this->assertIsArray($result->data);
        $this->assertCount(3, $result->data);
        $this->assertEquals(1, $result->data[0]['id']);
    }

    public function testChattyAIResponse(): void
    {
        $chattyResponse = <<<'TEXT'
Sure! Here's the user data you requested:

```json
{
  "name": "Alice Johnson",
  "active": true,
  "email": "alice@example.com"
}
```

Let me know if you need anything else!
TEXT;

        $result = validate_ai_json($chattyResponse);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Alice Johnson', $result->data['name']);
        $this->assertTrue($result->data['active']);
        $this->assertEquals('alice@example.com', $result->data['email']);
    }

    public function testMultipleJsonBlocksTakesFirst(): void
    {
        $text = <<<'TEXT'
First: {"type": "first", "value": 1}

Second: {"type": "second", "value": 2}
TEXT;

        $result = validate_ai_json($text);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('first', $result->data['type']);
        $this->assertEquals(1, $result->data['value']);
    }

    public function testNestedBracesInStrings(): void
    {
        $text = 'Data: {"message": "Use {braces} in text", "valid": true}';
        $result = validate_ai_json($text);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Use {braces} in text', $result->data['message']);
        $this->assertTrue($result->data['valid']);
    }

    public function testNoJsonInInput(): void
    {
        $text = 'This is just plain text without any JSON';
        $result = validate_ai_json($text);

        $this->assertFalse($result->jsonValid);
        $this->assertCount(1, $result->errors);
    }

    public function testEmptyInput(): void
    {
        $result = validate_ai_json('');

        $this->assertFalse($result->jsonValid);
        $this->assertCount(1, $result->errors);
    }

    public function testWhitespaceOnly(): void
    {
        $result = validate_ai_json('   ');

        $this->assertFalse($result->jsonValid);
        $this->assertCount(1, $result->errors);
    }
}
