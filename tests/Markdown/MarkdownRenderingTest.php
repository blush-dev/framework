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
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Markdown\CommonMark\FigureRenderer;
use Blush\Markdown\CommonMark\ResolveLinks;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaConfig;
use Blush\Media\MediaResolver;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(CommonMarkParser::class)]
#[CoversClass(FigureRenderer::class)]
#[CoversClass(ResolveLinks::class)]
final class MarkdownRenderingTest extends TestCase
{
	use TemporaryDirectory;

	protected function setUp(): void
	{
		$image = imagecreatetruecolor(4, 3);
		$this->assertNotFalse($image);

		foreach (['user/media/2019/cat.png', 'user/content/_post/hello/photo.png'] as $path) {
			$this->writeTemporaryFile($path, '');
			imagepng($image, $this->temporaryDirectory() . '/' . $path);
		}
	}

	private function parser(bool $figures = true, bool $absoluteLinks = true): CommonMarkParser
	{
		return new CommonMarkParser(
			new MarkdownConfig(
				smartPunctuation: false,
				headingAnchors: false,
				lineBreaks: false,
				figures: $figures,
				absoluteLinks: $absoluteLinks
			),
			new MediaResolver(Paths::fromRoot($this->temporaryDirectory()), new MediaConfig()),
			new AppConfig(url: 'https://example.com/blog')
		);
	}

	public function testDefinitionListsAndHighlightingAreOn(): void
	{
		$html = $this->parser()->toHtml("Blush\n: A flat-file CMS.\n\nIt's ==fast==.");

		$this->assertSame("<dl>\n<dt>Blush</dt>\n<dd>A flat-file CMS.</dd>\n</dl>\n<p>It's <mark>fast</mark>.</p>\n", $html);
	}

	public function testAttributesAreOnByDefault(): void
	{
		$html = $this->parser()->toHtml(<<<'MD'
			## Title {.big #top}

			A paragraph
			on two lines. {.lead}

			- one {.first}
			- [ ] two

			{.aside}
			> Quoted

			{.demo}
			```php
			echo 1;
			```

			{.data}
			| A |
			|---|
			| 1 |

			{.break}
			---
			MD);

		$this->assertStringContainsString('<h2 class="big" id="top">Title</h2>', $html);
		$this->assertStringContainsString("<p class=\"lead\">A paragraph\non two lines.</p>", $html);
		$this->assertStringContainsString('<li class="first">one</li>', $html);
		$this->assertStringContainsString('<blockquote class="aside">', $html);
		$this->assertStringContainsString('<code class="demo language-php">', $html);
		$this->assertStringContainsString('<table class="data">', $html);
		$this->assertStringContainsString('<hr class="break" />', $html);
	}

	/**
	 * Empty brackets are empty alt text, as written (D-272): nothing
	 * fills them in.
	 */
	public function testAnImageWithoutAltTextHasEmptyAltText(): void
	{
		$this->writeTemporaryFile('user/data/media/2019/cat.png.json', '{"alt": "A cat"}');

		$this->assertStringContainsString('alt=""', $this->parser()->toHtml('![](/media/2019/cat.png)'));
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

	/**
	 * An image on each line, as a gallery's are written (D-528), is a
	 * figure each, without a paragraph or line breaks around them.
	 */
	public function testAnImageOnEachLineIsAFigureEach(): void
	{
		$html = $this->parser()->toHtml("![A](/media/2019/cat.png \"First\"){.wide}\n[![B](/media/2019/cat.png)](/about)\\\n![C](/media/2019/cat.png)");

		$this->assertSame(3, substr_count($html, '<figure'));
		$this->assertStringContainsString('<figure class="wide">', $html);
		$this->assertStringContainsString('<figcaption>First</figcaption>', $html);
		$this->assertStringContainsString('<figure><a href="https://example.com/about"><img', $html);
		$this->assertStringNotContainsString('<p>', $html);
		$this->assertStringNotContainsString('<br', $html);
	}

	public function testImagesSideBySideOrWithTextStayAParagraph(): void
	{
		$this->assertStringStartsWith('<p><img', $this->parser()->toHtml('![A](/media/2019/cat.png) ![B](/media/2019/cat.png)'));
		$this->assertStringStartsWith('<p><img', $this->parser()->toHtml("![A](/media/2019/cat.png)\nA cat."));
	}

	public function testInlineImagesStayInTheirParagraph(): void
	{
		$this->assertSame(
			'<p>A <img width="4" height="3" src="https://example.com/media/2019/cat.png" alt="cat" /> sat.</p>' . "\n",
			$this->parser()->toHtml('A ![cat](/media/2019/cat.png) sat.')
		);
	}

	public function testRelativeMediaIsFromTheSiteRootOnly(): void
	{
		$this->assertStringContainsString('src="https://example.com/media/2019/cat.png"', $this->parser()->toHtml('![Cat](user/media/2019/cat.png)'));
		$this->assertStringContainsString('src="photo.png"', $this->parser()->toHtml('![Photo](photo.png)'), 'Media is never beside an entry (D-294).');
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
