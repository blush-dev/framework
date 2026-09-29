<?php

/**
 * YAML map editor tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Writer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Writer\YamlMap;

#[CoversClass(YamlMap::class)]
final class YamlMapTest extends TestCase
{
	/**
	 * jtcom-style front matter: aligned colons, empty values, a comment.
	 */
	private const string JTCOM = <<<YAML
		title     : "Consumer vs. Creator"
		author    : justintadlock
		# Set when it's done.
		date      : 2001-01-01 00:00:00 -6
		format    :
		category  :
		  - life
		  - work
		image : ""

		YAML;

	public function testRewritesOnlyTheKeyKeepingItsAlignment(): void
	{
		$text = YamlMap::fromText(self::JTCOM)->with(['title'], 'Makers and Takers')->text();

		$this->assertSame(str_replace('title     : "Consumer vs. Creator"', 'title     : "Makers and Takers"', self::JTCOM), $text);
	}

	public function testUpdatesAnAliasTheTextAlreadyUses(): void
	{
		$map = YamlMap::fromText(self::JTCOM);

		$this->assertSame('date', $map->present(['published', 'date']));
		$this->assertStringContainsString("\ndate      : 2026-09-29 09:00:00 -05:00\n", $map->with(['published', 'date'], '2026-09-29 09:00:00 -05:00')->text());
		$this->assertStringNotContainsString('published', $map->with(['published', 'date'], '2026-09-29 09:00:00 -05:00')->text());
	}

	public function testReplacesAKeysWholeBlock(): void
	{
		$text = YamlMap::fromText(self::JTCOM)->with(['category'], ['art'])->text();

		$this->assertStringContainsString("category  : [art]\nimage : \"\"\n", $text);
		$this->assertStringNotContainsString('- life', $text);
	}

	public function testReplacesACompactListAtTheKeysIndent(): void
	{
		$text = YamlMap::fromText("tags:\n- one\n- two\n\ntitle: Hi\n")->with(['tags'], 'three')->text();

		$this->assertSame("tags: three\n\ntitle: Hi\n", $text);
	}

	public function testWritesValuesAsPeopleDo(): void
	{
		$text = YamlMap::fromText('')
			->with(['empty'], null)
			->with(['title'], 'Hello: World')
			->with(['plain'], 'Hello')
			->with(['published'], '2026-09-29 09:00:00 -05:00')
			->with(['draft'], false)
			->with(['tags'], [])
			->with(['notes'], "One\nTwo")
			->text();

		$this->assertSame("empty:\ntitle: \"Hello: World\"\nplain: Hello\npublished: 2026-09-29 09:00:00 -05:00\ndraft: false\ntags: []\nnotes: |-\n  One\n  Two\n", $text);
	}

	public function testRemovesKeysAndTheirBlocks(): void
	{
		$text = YamlMap::fromText(self::JTCOM)->without(['category', 'format', 'missing'])->text();

		$this->assertSame(str_replace("format    :\ncategory  :\n  - life\n  - work\n", '', self::JTCOM), $text);
	}

	public function testKeepsWindowsLineEndings(): void
	{
		$text = YamlMap::fromText("title: A\r\nauthor: me\r\n")->with(['title'], 'B')->text();

		$this->assertSame("title: B\r\nauthor: me\r\n", $text);
	}

	public function testKeepsCommentsAndBlankLinesAroundEdits(): void
	{
		$yaml = "# About\ntitle: A\n\n# Who\nauthor: me\n";

		$this->assertSame("# About\ntitle: B\n\n# Who\nauthor: me\n", YamlMap::fromText($yaml)->with(['title'], 'B')->text());
		$this->assertSame("# About\ntitle: A\n\n# Who\nauthor: me\nstatus: draft\n", YamlMap::fromText($yaml)->with(['status'], 'draft')->text());
	}
}
