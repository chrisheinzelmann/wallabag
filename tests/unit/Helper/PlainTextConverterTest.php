<?php

namespace Wallabag\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Wallabag\Helper\PlainTextConverter;

class PlainTextConverterTest extends TestCase
{
    private function getPandocBinary(): string
    {
        $binary = trim((string) shell_exec('which pandoc 2>/dev/null'));
        if ('' === $binary) {
            $this->markTestSkipped('pandoc is not available on this system.');
        }

        return $binary;
    }

    public function testConvertPlainTextReturnsHtml(): void
    {
        $pandoc = $this->getPandocBinary();
        $converter = new PlainTextConverter($pandoc, new NullLogger());

        $result = $converter->convert('Hello world');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testConvertStripsHtmlTagsBeforeConverting(): void
    {
        $pandoc = $this->getPandocBinary();
        $converter = new PlainTextConverter($pandoc, new NullLogger());

        $result = $converter->convert('<pre>Hello world</pre>');

        $this->assertNotNull($result);
        $this->assertStringContainsString('Hello world', $result);
        $this->assertStringNotContainsString('<pre>', $result);
    }

    public function testConvertReturnsNullForEmptyText(): void
    {
        $pandoc = $this->getPandocBinary();
        $converter = new PlainTextConverter($pandoc, new NullLogger());

        $this->assertNull($converter->convert(''));
        $this->assertNull($converter->convert('   '));
        $this->assertNull($converter->convert('<br>'));
    }

    public function testConvertReturnsNullWhenPandocNotFound(): void
    {
        $converter = new PlainTextConverter('/nonexistent/pandoc', new NullLogger());

        $result = $converter->convert('Hello world');

        $this->assertNull($result);
    }

    public function testConvertMultipleParagraphs(): void
    {
        $pandoc = $this->getPandocBinary();
        $converter = new PlainTextConverter($pandoc, new NullLogger());

        $text = "First paragraph.\n\nSecond paragraph.";
        $result = $converter->convert($text);

        $this->assertNotNull($result);
        $this->assertStringContainsString('First paragraph', $result);
        $this->assertStringContainsString('Second paragraph', $result);
        $this->assertStringContainsString('<p>', $result);
    }

    public function testConvertWithInvalidUtf8Input(): void
    {
        $pandoc = $this->getPandocBinary();
        $converter = new PlainTextConverter($pandoc, new NullLogger());

        $result = $converter->convert("Hello\xA9 world");

        $this->assertNotNull($result);
        $this->assertStringContainsString('Hello', $result);
    }
}
