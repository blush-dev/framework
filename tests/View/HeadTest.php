<?php

/**
 * Head tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\View\Head;
use Blush\View\ViewException;

#[CoversClass(Head::class)]
final class HeadTest extends TestCase
{
	public function testBuildsTheDocumentTitle(): void
	{
		$this->assertSame('Site', new Head('Site')->documentTitle());
		$this->assertSame('About | Site', new Head('Site')->title(' About ')->documentTitle());
		$this->assertSame('About', new Head()->title('About')->documentTitle());
		$this->assertSame('About — Site', new Head('Site', ' — ')->title('About')->documentTitle());
		$this->assertSame('About', new Head('Site')->title('About')->pageTitle());
	}

	public function testRendersEachTagOnceInOrder(): void
	{
		$head = new Head('A & B')
			->style('/a.css')
			->meta('description', 'First "one"')
			->canonical('/old')
			->property('og:title', 'Title')
			->script('/app.js')
			->script('/module.js', ['type' => 'module'])
			->link('alternate', '/feed', ['type' => 'application/rss+xml'])
			->link('alternate', '/feed/atom', ['type' => 'application/atom+xml'])
			->style('/a.css', ['media' => 'print'])
			->meta('description', 'Second')
			->canonical('/new');

		$this->assertSame(
			implode("\n", [
				'<title>A &amp; B</title>',
				'<link rel="stylesheet" href="/a.css" media="print">',
				'<meta name="description" content="Second">',
				'<link rel="canonical" href="/new">',
				'<meta property="og:title" content="Title">',
				'<script src="/app.js" defer></script>',
				'<script src="/module.js" type="module"></script>',
				'<link rel="alternate" href="/feed" type="application/rss+xml">',
				'<link rel="alternate" href="/feed/atom" type="application/atom+xml">'
			]),
			(string) $head
		);
		$this->assertTrue($head->has('link:canonical'));
		$this->assertFalse($head->has('meta:robots'));
	}

	public function testDropsUnsafeUrls(): void
	{
		$this->assertStringContainsString('<link rel="canonical" href="">', new Head()->canonical('javascript:alert(1)')->render());
	}

	public function testPrintsInlineStylesOncePerId(): void
	{
		$head = new Head()->inlineStyle('palette', ":root {}\n")->style('/a.css')->inlineStyle('palette', ":root { --a: 1; }\n");

		$this->assertSame("<title></title>\n<style id=\"palette\">\n:root { --a: 1; }\n</style>\n<link rel=\"stylesheet\" href=\"/a.css\">", $head->render());
		$this->assertTrue($head->has('inline-style:palette'));

		$this->expectException(ViewException::class);
		$head->inlineStyle('x', 'a</STYLE><script>');
	}
}
