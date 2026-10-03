<?php

/**
 * Markdown link tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Llms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Llms\MarkdownLinks;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(MarkdownLinks::class)]
final class MarkdownLinksTest extends TestCase
{
	use BuildsContentSite;

	private MarkdownLinks $links;

	protected function setUp(): void
	{
		$this->writeTemporaryFile('user/media/pixel.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));
		$this->entry('index.md', 'title: Home');

		$this->links = $this->site()->container()->make(MarkdownLinks::class);
	}

	public function testGivesLinksAndImagesFullUrls(): void
	{
		$this->assertSame(
			"See [about](http://localhost/about) and [up](http://localhost/a/b?c=d#e).\n"
			. "![Pixel](http://localhost/media/pixel.png \"A title\"){.stretch-wide}\n"
			. "![Gone](http://localhost/user/media/missing.png) [spaced](<http://localhost/a b>)\n"
			. "[[nested]](http://localhost/x) and [file](http://localhost/media/pixel.png)\n",
			$this->links->absolute(
				"See [about](/about) and [up](/a/b?c=d#e).\n"
				. "![Pixel](/user/media/pixel.png \"A title\"){.stretch-wide}\n"
				. "![Gone](/user/media/missing.png) [spaced](</a b>)\n"
				. "[[nested]](/x) and [file](user/media/pixel.png)\n"
			),
			'Media references become their media URLs, as on the HTML page.'
		);
	}

	public function testLeavesOtherUrlsAlone(): void
	{
		$markdown = "[full](https://example.com/a) [proto](//cdn.example.com/a) [hash](#top) [mail](mailto:a@example.com) [rel](notes/a)\n";

		$this->assertSame($markdown, $this->links->absolute($markdown));
	}

	public function testRewritesReferenceDefinitions(): void
	{
		$this->assertSame(
			"[About][1]\n\n[1]: http://localhost/about \"About me\"\n  [img]: <http://localhost/media/pixel.png>\n",
			$this->links->absolute("[About][1]\n\n[1]: /about \"About me\"\n  [img]: </user/media/pixel.png>\n")
		);
	}

	public function testRewritesDirectiveUrlProps(): void
	{
		$this->assertSame(
			"::audio{src=http://localhost/user/media/clip.mp3 .wide}\n"
			. "::button[Go]{url=\"http://localhost/about\" label='/not-a-url'}\n"
			. ":::figure{.wide}\n![Pixel](http://localhost/media/pixel.png)\n:::\n"
			. "::::group\n:button{url='http://localhost/a'} and ::unknown{src=/x}\n::::\n",
			$this->links->absolute(
				"::audio{src=/user/media/clip.mp3 .wide}\n"
				. "::button[Go]{url=\"/about\" label='/not-a-url'}\n"
				. ":::figure{.wide}\n![Pixel](/user/media/pixel.png)\n:::\n"
				. "::::group\n:button{url='/a'} and ::unknown{src=/x}\n::::\n"
			),
			'Only a component\'s media and link props, by its definition.'
		);
	}

	public function testLeavesCodeAlone(): void
	{
		$markdown = "Use `[x](/a)` or ``[y](/b) ` here``.\n\n"
			. "```md\n[x](/a)\n::button{url=/a}\n```\n\n"
			. "> ~~~~\n> [x](/a)\n> ~~~\n> [still](/code)\n> ~~~~\n\n"
			. "    [indented](/code)\n\n    [still](/code)\n";

		$this->assertSame($markdown, $this->links->absolute($markdown));
		$this->assertSame(
			"```\ncode\n```\n[after](http://localhost/a)\n",
			$this->links->absolute("```\ncode\n```\n[after](/a)\n")
		);
	}

	public function testLeavesHtmlAlone(): void
	{
		$markdown = "<div>\n[x](/a)\n</div>\n\n"
			. "<pre>\n[x](/a)\n\n[y](/b)\n</pre>\n"
			. "<!-- [x](/a)\n\n[y](/b) -->\n"
			. "<p>[x](/a)</p>\n\n";

		$this->assertSame($markdown . "[after](http://localhost/a)\n", $this->links->absolute($markdown . "[after](/a)\n"));
		$this->assertSame(
			"Text <a href=\"/a\">here</a> and [there](http://localhost/b).\n",
			$this->links->absolute("Text <a href=\"/a\">here</a> and [there](/b).\n"),
			'Inline HTML keeps its attributes; the Markdown beside it changes.'
		);
	}
}
