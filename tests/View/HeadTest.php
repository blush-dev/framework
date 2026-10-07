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
use Blush\View\PageMarkup;
use Blush\View\ViewException;

#[CoversClass(Head::class)]
final class HeadTest extends TestCase
{
	public function testBuildsTheDocumentTitle(): void
	{
		$this->assertSame('Site', new PageMarkup('Site')->head->documentTitle());
		$this->assertSame('About | Site', new PageMarkup('Site')->head->title(' About ')->documentTitle());
		$this->assertSame('About', new PageMarkup()->head->title('About')->documentTitle());
		$this->assertSame('About — Site', new PageMarkup('Site', ' — ')->head->title('About')->documentTitle());
		$this->assertSame('About', new PageMarkup('Site')->head->title('About')->pageTitle());
	}

	public function testRendersEachTagOnceGroupedByKind(): void
	{
		$head = new PageMarkup('A & B')->head
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
			"\t" . implode("\n\t", [
				'<meta charset="utf-8">',
				'<title>A &amp; B</title>',
				'<meta name="description" content="Second">',
				'<meta property="og:title" content="Title">',
				'<link rel="canonical" href="/new">',
				'<link rel="alternate" href="/feed" type="application/rss+xml">',
				'<link rel="alternate" href="/feed/atom" type="application/atom+xml">',
				'<link rel="stylesheet" href="/a.css" media="print">',
				'<script src="/app.js" defer></script>',
				'<script src="/module.js" type="module"></script>'
			]),
			(string) $head
		);
		$this->assertTrue($head->has('link:canonical'));
		$this->assertFalse($head->has('meta:robots'));
	}

	public function testRepeatsPropertiesThatTakeSeveralValues(): void
	{
		$head = new PageMarkup()->head
			->addProperty('article:author', 'https://example.test/blog/authors/jane')
			->addProperty('article:author', 'https://example.test/blog/authors/sam')
			->addProperty('article:author', 'https://example.test/blog/authors/jane');

		$this->assertSame(
			"\t" . implode("\n\t", [
				'<meta charset="utf-8">',
				'<title></title>',
				'<meta property="article:author" content="https://example.test/blog/authors/jane">',
				'<meta property="article:author" content="https://example.test/blog/authors/sam">'
			]),
			(string) $head
		);
	}

	public function testPrintsRootRelativeUrlsOnTheOrigin(): void
	{
		$head = new PageMarkup('Site', origin: 'https://example.com')->head
			->style('/theme/style.css')
			->script('/theme/app.js', ['type' => 'module'])
			->link('next', '/page/2')
			->link('icon', '//cdn.example.org/icon.png')
			->canonical('https://example.com/about');

		$this->assertSame(
			"\t" . implode("\n\t", [
				'<meta charset="utf-8">',
				'<title>Site</title>',
				'<link rel="next" href="https://example.com/page/2">',
				'<link rel="icon" href="//cdn.example.org/icon.png">',
				'<link rel="canonical" href="https://example.com/about">',
				'<link rel="stylesheet" href="https://example.com/theme/style.css">',
				'<script src="https://example.com/theme/app.js" type="module"></script>'
			]),
			$head->render()
		);
		$this->assertTrue($head->remove('style:/theme/style.css')->has('script:/theme/app.js'));
		$this->assertStringNotContainsString('style.css', $head->render());
	}

	public function testPrintsResourceHintsBeforeStylesKeepingTheCascade(): void
	{
		$head = new PageMarkup()->head
			->script('/app.js')
			->style('/a.css')
			->inlineStyle('tokens', ':root {}')
			->style('/b.css')
			->link('preload', '/font.woff2', ['as' => 'font'])
			->link('icon', '/icon.png')
			->meta('theme-color', '#fff');

		$this->assertSame(
			"\t" . implode("\n\t", [
				'<meta charset="utf-8">',
				'<title></title>',
				'<meta name="theme-color" content="#fff">',
				'<link rel="icon" href="/icon.png">',
				'<link rel="preload" href="/font.woff2" as="font">',
				'<link rel="stylesheet" href="/a.css">',
				"<style id=\"tokens\">\n\t\t:root {}\n\t</style>",
				'<link rel="stylesheet" href="/b.css">',
				'<script src="/app.js" defer></script>'
			]),
			$head->render()
		);
	}

	public function testPreloadsByExtension(): void
	{
		$head = new PageMarkup('Site')->head
			->preload('/fonts/body.woff2?v=1')
			->preload('/css/print.css')
			->preload('/js/app.mjs')
			->preload('/img/hero.webp', ['fetchpriority' => 'high'])
			->preload('/data/list.json', ['as' => 'fetch', 'crossorigin' => true])
			->preload('/fonts/body.woff2?v=1');

		$html = $head->render();

		$this->assertSame(1, substr_count($html, 'body.woff2'), 'Once per URL.');
		$this->assertStringContainsString('<link rel="preload" href="/fonts/body.woff2?v=1" as="font" type="font/woff2" crossorigin>', $html);
		$this->assertStringContainsString('<link rel="preload" href="/css/print.css" as="style">', $html);
		$this->assertStringContainsString('<link rel="preload" href="/js/app.mjs" as="script">', $html);
		$this->assertStringContainsString('<link rel="preload" href="/img/hero.webp" as="image" fetchpriority="high">', $html);
		$this->assertStringContainsString('<link rel="preload" href="/data/list.json" as="fetch" crossorigin>', $html, 'Attributes given win.');
	}

	public function testDropsUnsafeUrls(): void
	{
		$this->assertStringContainsString('<link rel="canonical" href="">', new PageMarkup()->head->canonical('javascript:alert(1)')->render());
	}

	public function testPrintsInlineStylesOncePerId(): void
	{
		$head = new PageMarkup()->head->inlineStyle('palette', ":root {}\n")->style('/a.css')->inlineStyle('palette', ":root { --a: 1; }\n");

		$this->assertSame("\t<meta charset=\"utf-8\">\n\t<title></title>\n\t<style id=\"palette\">\n\t\t:root { --a: 1; }\n\t</style>\n\t<link rel=\"stylesheet\" href=\"/a.css\">", $head->render());
		$this->assertTrue($head->has('inline-style:palette'));

		$this->expectException(ViewException::class);
		$head->inlineStyle('x', 'a</STYLE><script>');
	}

	public function testPrintsInlineScriptsOncePerIdAfterScripts(): void
	{
		$head = new PageMarkup()->head->inlineScript('scheme', "let a;\n")->style('/a.css')->inlineScript('scheme', "document.documentElement.dataset.theme = 'dark';\n");

		$this->assertSame("\t<meta charset=\"utf-8\">\n\t<title></title>\n\t<link rel=\"stylesheet\" href=\"/a.css\">\n\t<script id=\"scheme\">document.documentElement.dataset.theme = 'dark';</script>", $head->render());
		$this->assertTrue($head->has('inline-script:scheme'));
		$this->assertMatchesRegularExpression('#<script src="/late\.js" defer></script>\n\t<script id="scheme">#', $head->script('/late.js')->render(), 'Inline scripts print after scripts (D-579).');

		$this->expectException(ViewException::class);
		$head->inlineScript('x', 'a</SCRIPT><b>');
	}
}
