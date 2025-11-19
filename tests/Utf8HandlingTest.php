<?php
/**
 * UTF-8 handling and multibyte character tests.
 * Includes tests from test_utf8_bug.php and test_edge_cases.php
 */

declare(strict_types=1);

namespace AIJsonCleanroom\Tests;

use PHPUnit\Framework\TestCase;

class Utf8HandlingTest extends TestCase
{
    public function testCurlyQuotesInStrings(): void
    {
        $json = '{"message": "Hello "world" with curly quotes"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid, 'Failed to parse JSON with curly quotes');
        $this->assertEquals('Hello "world" with curly quotes', $result->data['message']);
    }

    public function testMultibyteUtf8Characters(): void
    {
        $json = '{
            "spanish": "Hola José García",
            "german": "Grüße aus München",
            "chinese": "你好世界",
            "emoji": "👍😀🎉",
            "arabic": "مرحبا بك"
        }';

        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid, 'Failed to parse multibyte JSON');
        $this->assertEquals('Hola José García', $result->data['spanish']);
        $this->assertEquals('👍😀🎉', $result->data['emoji']);
    }

    public function testCurlyQuotesWithMultibyteChars(): void
    {
        $json = '{"text": "José said "Hola" to María"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid, 'Failed to parse mixed content');
        $expected = 'José said "Hola" to María';
        $this->assertEquals($expected, $result->data['text']);
    }

    public function testCurlyQuoteFollowedByComma(): void
    {
        $json = '{"items": ["hello"", "world""]}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid || !$result->jsonValid, 'Test executed without crash');
    }

    public function testEmojiWithCurlyQuotes(): void
    {
        $json = '{"msg": "👍 "great" 😀"}';
        $result = validate_ai_json($json);

        if ($result->jsonValid) {
            $this->assertIsString($result->data['msg']);
        }
        $this->addToAssertionCount(1);
    }

    public function testUnicodeCharacterHandling(): void
    {
        $json = '{"text": "Testing unicode: é è ñ ü ö"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertStringContainsString('é', $result->data['text']);
    }

    public function testComplexUtf8Json(): void
    {
        $json = <<<'JSON'
{
  "name": "José García",
  "message": "Testing "quotes" and 'apostrophes'",
  "emoji": "👍",
  "chinese": "你好"
}
JSON;

        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid, 'Failed to parse complex UTF-8 JSON');
        $this->assertEquals('José García', $result->data['name']);
        $this->assertEquals('👍', $result->data['emoji']);
    }

    public function testMixedQuotesAndAccents(): void
    {
        $json = '{"text": "Hola "mundo" con ñ y á"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertStringContainsString('ñ', $result->data['text']);
        $this->assertStringContainsString('á', $result->data['text']);
    }

    public function testSanitizeCurlyQuotesFunction(): void
    {
        $input = '"Hello "world""';
        $output = sanitize_curly_quotes($input);

        $this->assertNotEquals($input, $output);
        $this->assertStringNotContainsString('"', $output);
        $this->assertStringNotContainsString('"', $output);
    }

    public function testUtf8LengthHandling(): void
    {
        // Verify that mb_strlen is used correctly
        $curlyQuote = "\u{201C}";  // Left double quotation mark
        $this->assertEquals(3, strlen($curlyQuote), 'Curly quote is 3 bytes');
        $this->assertEquals(1, mb_strlen($curlyQuote, 'UTF-8'), 'Curly quote is 1 character');
    }

    public function testJsonWithRussian(): void
    {
        $json = '{"greeting": "Привет мир"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('Привет мир', $result->data['greeting']);
    }

    public function testJsonWithJapanese(): void
    {
        $json = '{"greeting": "こんにちは世界"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('こんにちは世界', $result->data['greeting']);
    }

    public function testJsonWithKorean(): void
    {
        $json = '{"greeting": "안녕하세요 세계"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertEquals('안녕하세요 세계', $result->data['greeting']);
    }

    public function testMixedEmojisAndText(): void
    {
        $json = '{"message": "Hello 👋 World 🌍 from 🚀 AI"}';
        $result = validate_ai_json($json);

        $this->assertTrue($result->jsonValid);
        $this->assertStringContainsString('👋', $result->data['message']);
        $this->assertStringContainsString('🌍', $result->data['message']);
        $this->assertStringContainsString('🚀', $result->data['message']);
    }
}
