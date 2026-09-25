<?php

/**
 * Front matter tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\InvalidDocument;
use Blush\Data\SymfonyYamlParser;

#[CoversClass(FrontMatter::class)]
#[CoversClass(InvalidDocument::class)]
final class FrontMatterTest extends TestCase
{
	/**
	 * @return array<string, array{string, ?string, string}>
	 */
	public static function documents(): array
	{
		return [
			'front matter'         => ["---\ntitle: Hi\n---\n\nBody", 'title: Hi', "\nBody"],
			'spaced delimiters'    => ["---  \ntitle : Hi\n--- \nBody", 'title : Hi', 'Body'],
			'CRLF line endings'    => ["---\r\ntitle: Hi\r\n---\r\nBody", 'title: Hi', 'Body'],
			'YAML end marker'      => ["---\ntitle: Hi\n...\nBody", 'title: Hi', 'Body'],
			'empty block'          => ["---\n---\nBody", '', 'Body'],
			'nothing after it'     => ["---\ntitle: Hi\n---", 'title: Hi', ''],
			'byte-order mark'      => ["\xEF\xBB\xBF---\ntitle: Hi\n---\nBody", 'title: Hi', 'Body'],
			'no front matter'      => ["# Title\n\n---\n\nText", null, "# Title\n\n---\n\nText"],
			'BOM without a block'  => ["\xEF\xBB\xBFText", null, 'Text'],
			'unclosed block'       => ["---\ntitle: Hi\n\nBody", null, "---\ntitle: Hi\n\nBody"],
			'rule in the body'     => ["---\na: 1\n---\nOne\n\n---\n\nTwo", 'a: 1', "One\n\n---\n\nTwo"],
			'dashes inside a line' => ["---\na: ---x\n----\n---\nBody", "a: ---x\n----", 'Body']
		];
	}

	#[DataProvider('documents')]
	public function testSplitsDocuments(string $contents, ?string $yaml, string $body): void
	{
		$this->assertSame([$yaml, $body], FrontMatter::split($contents));
	}

	public function testParsesFrontMatter(): void
	{
		$frontMatter = new FrontMatter(new SymfonyYamlParser());

		$this->assertSame([['title' => 'Hi', 'og:title' => 'Site'], 'Body'], $frontMatter->parse("---\ntitle : Hi\nog:title: Site\n---\nBody"));
		$this->assertSame([[], 'Body'], $frontMatter->parse("---\n# just a comment\n---\nBody"));
		$this->assertSame([[], 'Body'], $frontMatter->parse('Body'));
	}

	public function testRejectsBadFrontMatter(): void
	{
		$frontMatter = new FrontMatter(new SymfonyYamlParser());

		foreach (["---\n- a\n- b\n---\n" => 'must be a map', "---\njust text\n---\n" => 'must be a map', "---\na: [\n---\n" => 'Invalid front matter'] as $contents => $message) {
			try {
				$frontMatter->parse($contents);
				$this->fail($message);
			} catch (InvalidDocument $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}
	}
}
