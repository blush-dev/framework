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

	public function testRepeatsPropertiesThatTakeSeveralValues(): void
	{
		$head = new Head()
			->addProperty('article:author', 'https://example.test/blog/authors/jane')
			->addProperty('article:author', 'https://example.test/blog/authors/sam')
			->addProperty('article:author', 'https://example.test/blog/authors/jane');

		$this->assertSame(
			implode("\n", [
				'<title></title>',
				'<meta property="article:author" content="https://example.test/blog/authors/jane">',
				'<meta property="article:author" content="https://example.test/blog/authors/sam">'
			]),
			(string) $head
		);
	}

	public function testPrintsRootRelativeUrlsOnTheOrigin(): void
	{
		$head = new Head('Site', origin: 'https://example.com')
			->style('/theme/style.css')
			->script('/theme/app.js', ['type' => 'module'])
			->link('next', '/page/2')
			->link('icon', '//cdn.example.org/icon.png')
			->canonical('https://example.com/about');

		$this->assertSame(
			implode("\n", [
				'<title>Site</title>',
				'<link rel="stylesheet" href="https://example.com/theme/style.css">',
				'<script src="https://example.com/theme/app.js" type="module"></script>',
				'<link rel="next" href="https://example.com/page/2">',
				'<link rel="icon" href="//cdn.example.org/icon.png">',
				'<link rel="canonical" href="https://example.com/about">'
			]),
			$head->render()
		);
		$this->assertTrue($head->remove('style:/theme/style.css')->has('script:/theme/app.js'));
		$this->assertStringNotContainsString('style.css', $head->render());
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
