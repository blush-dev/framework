<?php

/**
 * Markdown rendering tests.
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
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Event\EventDispatcher;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Markdown\CommonMark\FigureRenderer;
use Blush\Markdown\CommonMark\ResolveLinks;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\MarkdownContext;
use Blush\Media\MediaConfig;
use Blush\Media\MediaResolver;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(CommonMarkParser::class)]
#[CoversClass(FigureRenderer::class)]
#[CoversClass(ResolveLinks::class)]
#[CoversClass(MarkdownContext::class)]
final class MarkdownRenderingTest extends TestCase
{
	use TemporaryDirectory;

	protected function setUp(): void
	{
		$image = imagecreatetruecolor(4, 3);
		$this->assertNotFalse($image);

		foreach (['user/media/2019/cat.png', 'user/content/_posts/hello/photo.png'] as $path) {
			$this->writeTemporaryFile($path, '');
			imagepng($image, $this->temporaryDirectory() . '/' . $path);
		}
	}

	private function parser(bool $figures = true, bool $absoluteLinks = true): CommonMarkParser
	{
		return new CommonMarkParser(
			new MarkdownConfig(
				extensions: [...MarkdownConfig::DEFAULT_EXTENSIONS, AttributesExtension::class],
				figures: $figures,
				absoluteLinks: $absoluteLinks
			),
			new EventDispatcher(new ListenerRegistry()),
			new MediaResolver(Paths::fromRoot($this->temporaryDirectory()), new MediaConfig()),
			new AppConfig(url: 'https://example.com/blog')
		);
	}

	public function testDefinitionListsAndHighlightingAreOn(): void
	{
		$html = $this->parser()->toHtml("Blush\n: A flat-file CMS.\n\nIt's ==fast==.");

		$this->assertSame("<dl>\n<dt>Blush</dt>\n<dd>A flat-file CMS.</dd>\n</dl>\n<p>It's <mark>fast</mark>.</p>\n", $html);
	}

	public function testAnExtensionListedTwiceIsAddedOnce(): void
	{
		$parser = new CommonMarkParser(
			new MarkdownConfig(extensions: [...MarkdownConfig::DEFAULT_EXTENSIONS, HighlightExtension::class]),
			new EventDispatcher(new ListenerRegistry()),
			new MediaResolver(Paths::fromRoot($this->temporaryDirectory()), new MediaConfig()),
			new AppConfig(url: 'https://example.com')
		);

		$this->assertSame("<p><mark>once</mark></p>\n", $parser->toHtml('==once=='));
	}

	public function testALoneImageIsAFigure(): void
	{
		$this->assertSame(
			'<figure class="wide"><img width="4" height="3" src="https://example.com/media/2019/cat.png" alt="A cat" />' . "\n"
				. '<figcaption>Sleeping &lt;quietly&gt;</figcaption></figure>' . "\n",
			$this->parser()->toHtml('![A cat](/media/2019/cat.png "Sleeping <quietly>"){.wide}')
		);
	}

	public function testALinkedImageIsAFigure(): void
	{
		$this->assertSame(
			'<figure class="framed"><a href="https://example.com/about"><img width="4" height="3" src="https://example.com/media/2019/cat.png" alt="Cat" /></a></figure>' . "\n",
			$this->parser()->toHtml('[![Cat](/user/media/2019/cat.png)](/about){.framed}')
		);
	}

	public function testInlineImagesStayInTheirParagraph(): void
	{
		$this->assertSame(
			'<p>A <img width="4" height="3" src="https://example.com/media/2019/cat.png" alt="cat" /> sat.</p>' . "\n",
			$this->parser()->toHtml('A ![cat](/media/2019/cat.png) sat.')
		);
	}

	public function testBundleMediaResolvesAgainstTheEntry(): void
	{
		$this->assertStringContainsString(
			'src="https://example.com/media/_content/_posts/hello/photo.png"',
			$this->parser()->toHtml('![Photo](photo.png)', '_posts/hello')
		);
		$this->assertStringContainsString('src="photo.png"', $this->parser()->toHtml('![Photo](photo.png)'));
	}

	public function testRootRelativeLinksBecomeAbsolute(): void
	{
		$html = $this->parser()->toHtml('[a](/archives/x) [b](https://other.example) [c](#top) [d](page) [e](//cdn.example/x.js)');

		$this->assertStringContainsString('<a href="https://example.com/archives/x">a</a>', $html);
		$this->assertStringContainsString('<a href="https://other.example">b</a>', $html);
		$this->assertStringContainsString('<a href="#top">c</a>', $html);
		$this->assertStringContainsString('<a href="page">d</a>', $html);
		$this->assertStringContainsString('<a href="//cdn.example/x.js">e</a>', $html);
	}

	public function testFiguresAndAbsoluteLinksCanBeTurnedOff(): void
	{
		$this->assertSame(
			'<p><img class="wide" width="4" height="3" src="/media/2019/cat.png" alt="A cat" title="Cat" /></p>' . "\n",
			$this->parser(figures: false, absoluteLinks: false)->toHtml('![A cat](/user/media/2019/cat.png "Cat"){.wide}')
		);
	}

	public function testMissingOrDisallowedMediaIsLeftAlone(): void
	{
		$this->writeTemporaryFile('user/media/notes.txt', 'text');

		$this->assertStringContainsString('src="https://example.com/media/missing.png"', $this->parser()->toHtml('![x](/media/missing.png)'));
		$this->assertStringContainsString('href="https://example.com/media/notes.txt"', $this->parser()->toHtml('[notes](/media/notes.txt)'));
	}
}
