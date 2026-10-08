<?php

/**
 * Document parser tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Parser\Document;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Parser\SymfonyYamlParser;

#[CoversClass(DocumentParser::class)]
#[CoversClass(Document::class)]
final class DocumentParserTest extends TestCase
{
	private function parser(): DocumentParser
	{
		return new DocumentParser(new FrontMatter(new SymfonyYamlParser()));
	}

	public function testParsesFrontMatterAndAMarkdownBody(): void
	{
		$parser = $this->parser();

		$this->assertEquals(new Document(['title' => 'Hi'], "# Hi\n"), $parser->parse("---\ntitle: Hi\n---\n# Hi\n"));
		$this->assertEquals(new Document([], 'Plain'), $parser->parse('Plain'));
		$this->assertEquals(new Document(), $parser->parse(''));
	}

	public function testFrontMatterMustBeAMap(): void
	{
		$this->expectException(InvalidDocument::class);

		$this->parser()->parse("---\n- a\n- b\n---\nBody");
	}
}
