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
use Blush\Content\Parser\BodyFormat;
use Blush\Content\Parser\DataDocumentParser;
use Blush\Content\Parser\Document;
use Blush\Content\Parser\DocumentFormat;
use Blush\Content\Parser\DocumentParserRegistrar;
use Blush\Content\Parser\DocumentParserRegistry;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\HtmlDocumentParser;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Parser\MarkdownDocumentParser;
use Blush\Data\DataParserRegistry;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Data\KeyValueParser;

#[CoversClass(DocumentParsers::class)]
#[CoversClass(DocumentParserRegistry::class)]
#[CoversClass(DocumentParserRegistrar::class)]
#[CoversClass(DocumentFormat::class)]
#[CoversClass(Document::class)]
#[CoversClass(MarkdownDocumentParser::class)]
#[CoversClass(HtmlDocumentParser::class)]
#[CoversClass(DataDocumentParser::class)]
final class DocumentParsersTest extends TestCase
{
	use BootsScratchSite;

	private function parsers(): DocumentParsers
	{
		return $this->scratchApplication()->container()->make(DocumentParsers::class);
	}

	public function testParsesMarkdownAndHtml(): void
	{
		$parsers = $this->parsers();

		$this->assertEquals(new Document(['title' => 'Hi'], "# Hi\n"), $parsers->parse('a.md', "---\ntitle: Hi\n---\n# Hi\n"));
		$this->assertEquals(new Document([], 'Plain'), $parsers->parse('b.MARKDOWN', 'Plain'));
		$this->assertEquals(new Document(['title' => 'Hi'], '<p>Hi</p>', BodyFormat::Html), $parsers->parse('c.html', "---\ntitle: Hi\n---\n<p>Hi</p>"));
	}

	public function testParsesDataEntries(): void
	{
		$parsers = $this->parsers();

		$this->assertEquals(
			new Document(['title' => 'Hi', 'rating' => 4], '*Great*'),
			$parsers->parse('movie.json', '{"title": "Hi", "rating": 4, "body": "*Great*"}')
		);
		$this->assertEquals(new Document(['title' => 'Hi']), $parsers->parse('movie.yml', 'title: Hi'));
		$this->assertEquals(new Document(), $parsers->parse('movie.yaml', ''));
	}

	public function testDataEntriesMustBeMapsWithTextBodies(): void
	{
		$parsers = $this->parsers();

		foreach (['a.json' => '[1, 2]', 'b.json' => '{"body": 5}', 'c.yaml' => 'a: ['] as $path => $contents) {
			try {
				$parsers->parse($path, $contents);
				$this->fail($path);
			} catch (InvalidDocument) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testKnowsWhichFilesAreContent(): void
	{
		$parsers = $this->parsers();

		$this->assertTrue($parsers->supports('_posts/a.md'));
		$this->assertTrue($parsers->supports('data.YML'));
		$this->assertFalse($parsers->supports('media/photo.jpg'));
		$this->assertSame(['md', 'markdown', 'html', 'json', 'yaml', 'yml'], $parsers->extensions());
		$this->assertSame($parsers->for('md'), $parsers->for('MD'));
	}

	public function testUnknownExtensionsHaveNoParser(): void
	{
		$this->expectException(InvalidDocument::class);
		$this->expectExceptionMessage('No content parser is registered for ".txt" files.');

		$this->parsers()->for('txt');
	}

	public function testDataFormatsFromExtensionsWorkForEntries(): void
	{
		$application = $this->scratchApplication();
		$container   = $application->container();

		$container->make(DataParserRegistry::class)->register('kv', KeyValueParser::class);
		$container->make(DocumentParserRegistry::class)->register('kv', DataDocumentParser::class);

		$this->assertEquals(
			new Document(['title' => 'Hi'], 'Text'),
			$container->make(DocumentParsers::class)->parse('entry.kv', "title = Hi\nbody = Text")
		);
	}
}
