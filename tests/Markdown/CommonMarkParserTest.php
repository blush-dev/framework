<?php

/**
 * CommonMark parser tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Markdown;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Markdown\CommonMark\BracketedSpanParser;
use Blush\Markdown\CommonMark\BracketedSpanRenderer;
use Blush\Markdown\CommonMark\DescriptionAttributes;
use Blush\Markdown\CommonMark\DescriptionListRenderer;
use Blush\Markdown\CommonMark\MentionLinks;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\FootnoteOptions;
use Blush\Markdown\HeadingAnchorOptions;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\MarkdownException;
use Blush\Markdown\MentionResolver;
use Blush\Markdown\RawHtml;

#[CoversClass(CommonMarkParser::class)]
#[CoversClass(BracketedSpanParser::class)]
#[CoversClass(BracketedSpanRenderer::class)]
#[CoversClass(DescriptionAttributes::class)]
#[CoversClass(DescriptionListRenderer::class)]
#[CoversClass(MentionLinks::class)]
#[CoversClass(MarkdownConfig::class)]
#[CoversClass(HeadingAnchorOptions::class)]
#[CoversClass(FootnoteOptions::class)]
#[CoversClass(MarkdownException::class)]
final class CommonMarkParserTest extends TestCase
{
	private function parser(MarkdownConfig $config = new MarkdownConfig(), ?MentionResolver $mentions = null): CommonMarkParser
	{
		return new CommonMarkParser($config, mentions: $mentions);
	}

	private static function people(): MentionResolver
	{
		return new class implements MentionResolver {
			#[Override]
			public function url(string $name): ?string
			{
				return $name === 'jane' ? '/profiles/jane' : null;
			}
		};
	}

	public function testConvertsTheDialect(): void
	{
		$html = $this->parser()->toHtml("# Hi\n\n~~old~~ ==new== https://example.com\n\n| a |\n|---|\n| b |\n\n- [x] done\n\nNote[^1]\n\n[^1]: A note.\n\n<b>raw</b>");

		$this->assertStringContainsString('<h1>Hi', $html);
		$this->assertStringContainsString('<del>old</del>', $html);
		$this->assertStringContainsString('<mark>new</mark>', $html);
		$this->assertStringContainsString('<a href="https://example.com">', $html);
		$this->assertStringContainsString('<table>', $html);
		$this->assertStringContainsString('type="checkbox"', $html);
		$this->assertStringContainsString('class="footnotes"', $html);
		$this->assertStringContainsString('<b>raw</b>', $html);
	}

	public function testRendersWithTheDefaults(): void
	{
		$html = $this->parser()->toHtml("## Get \"started\"\n\nOne line\nand the next -- with ...");

		$this->assertStringContainsString('<h2>Get “started”<a id="get-started" href="#get-started" class="heading-anchor" aria-hidden="true" tabindex="-1" title="Link to this section">#</a></h2>', $html);
		$this->assertStringContainsString("<p>One line<br>\nand the next – with …</p>", $html);
	}

	public function testEachSettingTurnsOff(): void
	{
		$html = $this->parser(new MarkdownConfig(smartPunctuation: false, headingAnchors: false, lineBreaks: false))->toHtml("## \"Hi\"\n\nOne\ntwo");

		$this->assertSame("<h2>&quot;Hi&quot;</h2>\n<p>One\ntwo</p>\n", $html);
	}

	public function testAnchorsAndFootnotesTakeTheirOptions(): void
	{
		$parser = $this->parser(new MarkdownConfig(
			anchors: new HeadingAnchorOptions(class: 'anchor', symbol: '¶', title: '', prefix: 'h', before: true),
			footnotes: new FootnoteOptions(container: 'notes', reference: 'notes__ref', rule: false)
		));

		$html = $parser->toHtml("## Hi\n\nText[^a]\n\n[^a]: Note.");

		$this->assertStringContainsString('<h2><a id="h-hi" href="#h-hi" class="anchor" aria-hidden="true" tabindex="-1">¶</a>Hi</h2>', $html);
		$this->assertStringContainsString('class="notes__ref"', $html);
		$this->assertStringContainsString('<h2 id="top"><a href="#top" class="anchor" aria-hidden="true" tabindex="-1">¶</a>Top</h2>', $parser->toHtml('## Top {#top}'), 'A heading\'s own id wins.');
		$this->assertStringContainsString('<div class="notes" role="doc-endnotes"><ol>', $html);
	}

	public function testRawHtmlCanBeFilteredOrEscaped(): void
	{
		$markdown = "<b>bold</b> <script>x()</script> [go](javascript:alert(1))";

		$filtered = $this->parser(new MarkdownConfig(html: RawHtml::Filter))->toHtml($markdown);

		$this->assertStringContainsString('<b>bold</b>', $filtered);
		$this->assertStringContainsString('&lt;script>', $filtered);
		$this->assertStringContainsString('<a>go</a>', $filtered);

		$escaped = $this->parser(new MarkdownConfig(html: RawHtml::Escape))->toHtml($markdown);

		$this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $escaped);
	}

	public function testMentionsLinkThroughTheResolver(): void
	{
		$parser = $this->parser(mentions: self::people());

		$this->assertSame("<p>Thanks, <a class=\"mention\" href=\"/profiles/jane\">@jane</a>.</p>\n", $parser->toHtml('Thanks, @jane.'));
		$this->assertSame("<p>Thanks, @nobody.</p>\n", $parser->toHtml('Thanks, @nobody.'), 'Nobody\'s name stays text.');
		$this->assertStringNotContainsString('class="mention"', $parser->toHtml('Write to me@jane.example'), 'Not inside a word.');
		$this->assertStringNotContainsString('class="mention"', $parser->toHtml('`@jane`'), 'Not in code.');
		$this->assertSame("<p>@jane</p>\n", $this->parser(new MarkdownConfig(mentions: false), self::people())->toHtml('@jane'));
		$this->assertSame("<p>@jane</p>\n", $this->parser()->toHtml('@jane'), 'Not without a resolver.');
	}

	public function testFindsRawHtmlAndAddressesOutsideCode(): void
	{
		$found = $this->parser()->find("<div class=\"x\">\nBlock\n</div>\n\nInline <span>here</span>, `<code>` and [a](/a) ![b](/b.png)\n\n    <pre>indented</pre>\n\n```\n<script>\n```\n\n::button[Go]{url=\"javascript:x\"}");

		$this->assertSame(["<div class=\"x\">\nBlock\n</div>", '<span>', '</span>'], $found->html);
		$this->assertSame(['/a', '/b.png', 'javascript:x'], $found->urls);
	}

	public function testDescriptionListsTakeAttributes(): void
	{
		$html = $this->parser()->toHtml("{#terms .dl}\nFirst {#one}\n: A definition. {.note}\n\nSecond\n: Two\n: Three {.more}\n{.after}\n\nLoose\n\n: A paragraph. {.wide}\n\nPlain\n: None");

		$this->assertStringContainsString('<dl class="dl after" id="terms">', $html, 'Above and below the list.');
		$this->assertStringContainsString('<dt id="one">First</dt>', $html);
		$this->assertStringContainsString('<dd class="note">A definition.</dd>', $html, 'A tight definition\'s go on the <dd>.');
		$this->assertStringContainsString('<dd>Two</dd>', $html);
		$this->assertStringContainsString('<dd class="more">Three</dd>', $html);
		$this->assertStringContainsString('<dd><p class="wide">A paragraph.</p></dd>', $html, 'A loose one keeps them on its <p>.');
		$this->assertStringContainsString("<dt>Plain</dt>\n<dd>None</dd>", $html, 'Nothing to add, nothing added.');
	}

	public function testBracketsWithAttributesAreSpans(): void
	{
		$parser = $this->parser(new MarkdownConfig(absoluteLinks: false));
		$cases  = [
			'A [big *news*]{.big #news} day.' => '<p>A <span class="big" id="news">big <em>news</em></span> day.</p>',
			'[a [b]{.inner} c]{.outer}'       => '<p><span class="outer">a <span class="inner">b</span> c</span></p>',
			'[[text]{.x}](/url)'              => '<p><a href="/url"><span class="x">text</span></a></p>',
			'**[bold]{.x}**'                  => '<p><strong><span class="x">bold</span></strong></p>',
			'[link](/url){.x}'                => '<p><a class="x" href="/url">link</a></p>',
			"[docs]{.x}\n\n[docs]: /docs"     => '<p><a class="x" href="/docs">docs</a></p>',
			'[not a span]{oops'               => '<p>[not a span]{oops</p>',
			'[x]{onclick=alert(1)}'           => '<p><span>x</span></p>'
		];

		foreach ($cases as $markdown => $html) {
			$this->assertSame("{$html}\n", $parser->toHtml((string) $markdown), (string) $markdown);
		}
	}

	public function testBadSettingsFailOnFirstUse(): void
	{
		$this->expectException(InvalidConfig::class);

		MarkdownConfig::fromArray(['html' => 'sometimes']);
	}

	public function testConfigRoundTrips(): void
	{
		$config = new MarkdownConfig(mentions: false, html: RawHtml::Filter, anchors: new HeadingAnchorOptions(symbol: '¶'));

		$this->assertEquals($config, MarkdownConfig::fromArray($config->toArray()));
		$this->assertEquals(new MarkdownConfig(), MarkdownConfig::fromArray([]));
	}

	public function testLibraryKeysAreUnknown(): void
	{
		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('unknown key(s): options, extensions');

		MarkdownConfig::fromArray(['options' => [], 'extensions' => []]);
	}
}
