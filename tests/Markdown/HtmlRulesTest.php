<?php

/**
 * HTML rules tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Markdown\Html\HtmlAccess;
use Blush\Markdown\Html\HtmlRules;
use Blush\Markdown\Html\HtmlTag;
use Blush\Markdown\Html\RawMarkup;

#[CoversClass(HtmlRules::class)]
#[CoversClass(HtmlTag::class)]
#[CoversClass(RawMarkup::class)]
final class HtmlRulesTest extends TestCase
{
	/**
	 * @return list<string>
	 */
	private static function problems(string $html, HtmlAccess $access, string ...$urls): array
	{
		return HtmlRules::problems(new RawMarkup([$html], array_values($urls)), $access);
	}

	public function testReadsTagsAndTheirAttributes(): void
	{
		$tags = HtmlTag::all("<!-- <script> --><A HREF='/x' Title=\"a &amp; b\" hidden>x</A><br/><!doctype html><?php");

		$this->assertEquals([
			new HtmlTag('a', ['href' => '/x', 'title' => 'a & b', 'hidden' => '']),
			new HtmlTag('br'),
			new HtmlTag('!'),
			new HtmlTag('?')
		], $tags, 'Comments are left out, and closing tags add nothing.');
	}

	public function testEachLevelRefusesWhatItShould(): void
	{
		$this->assertSame(['<b>'], self::problems('<b>x</b>', HtmlAccess::None));
		$this->assertSame([], self::problems('<!-- a note -->', HtmlAccess::None), 'A comment isn\'t HTML anyone can misuse.');
		$this->assertSame([], self::problems('<abbr title="x" class="y" data-a="1" aria-label="z">x</abbr>', HtmlAccess::Allowed));
		$this->assertSame(['<p style>', '<marquee>'], self::problems('<p style="x">a</p><marquee>', HtmlAccess::Allowed));
		$this->assertSame([], self::problems('<p style="x">a</p><marquee>', HtmlAccess::Unfiltered));
		$this->assertSame(['<iframe>', '<a onclick>', '<img srcdoc>'], self::problems('<iframe></iframe><a onclick="x">a</a><img srcdoc="x">', HtmlAccess::Unfiltered));
	}

	public function testRefusesUnsafeAddressesHoweverTheyreWritten(): void
	{
		$this->assertSame(['javascript: in <a href>'], self::problems('<a href=" JaVa&#x09;Script&colon;alert(1)">x</a>', HtmlAccess::Unfiltered));
		$this->assertSame(['data: in <img srcset>'], self::problems('<img srcset="/a.png 1x, data:text/html,x 2x">', HtmlAccess::Allowed));
		$this->assertSame([], self::problems('<img src="data:image/webp;base64,AAAA">', HtmlAccess::Allowed));
		$this->assertSame(['vbscript: link', 'file: link'], self::problems('', HtmlAccess::Unfiltered, '/fine', 'vbscript:x', 'file:///etc/passwd', 'https://example.com'));
	}
}
