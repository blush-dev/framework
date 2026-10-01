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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use Blush\Config\InvalidConfig;
use Blush\Event\EventDispatcher;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Markdown\CommonMark\BracketedSpanParser;
use Blush\Markdown\CommonMark\BracketedSpanRenderer;
use Blush\Markdown\CommonMark\DescriptionAttributes;
use Blush\Markdown\CommonMark\DescriptionListRenderer;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\Events\MarkdownEnvironmentBuilding;
use Blush\Markdown\MarkdownConfig;
use Blush\Markdown\MarkdownException;
use Blush\Tests\Fixtures\Markdown\ShoutParser;

#[CoversClass(CommonMarkParser::class)]
#[CoversClass(BracketedSpanParser::class)]
#[CoversClass(BracketedSpanRenderer::class)]
#[CoversClass(DescriptionAttributes::class)]
#[CoversClass(DescriptionListRenderer::class)]
#[CoversClass(MarkdownConfig::class)]
#[CoversClass(MarkdownEnvironmentBuilding::class)]
#[CoversClass(MarkdownException::class)]
final class CommonMarkParserTest extends TestCase
{
	private ListenerRegistry $listeners;

	protected function setUp(): void
	{
		$this->listeners = new ListenerRegistry();
	}

	private function parser(MarkdownConfig $config = new MarkdownConfig()): CommonMarkParser
	{
		return new CommonMarkParser($config, new EventDispatcher($this->listeners));
	}

	public function testConvertsMarkdownWithTheDefaultExtensions(): void
	{
		$html = $this->parser()->toHtml("# Hi\n\n~~old~~ https://example.com\n\n| a |\n|---|\n| b |\n\n<b>raw</b>");

		$this->assertStringContainsString('<h1>Hi</h1>', $html);
		$this->assertStringContainsString('<del>old</del>', $html);
		$this->assertStringContainsString('<a href="https://example.com">', $html);
		$this->assertStringContainsString('<table>', $html);
		$this->assertStringContainsString('<b>raw</b>', $html);
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

		$plain = $this->parser(new MarkdownConfig(extensions: [CommonMarkCoreExtension::class]));

		$this->assertSame("<p>[text]{.x}</p>\n", $plain->toHtml('[text]{.x}'), 'Only with the attributes extension.');
	}

	public function testUsesTheConfiguredOptionsExtensionsAndInlineParsers(): void
	{
		$parser = $this->parser(new MarkdownConfig(
			options: ['html_input' => 'escape', 'renderer' => ['soft_break' => '<br />']],
			extensions: [CommonMarkCoreExtension::class, SmartPunctExtension::class],
			inlineParsers: [ShoutParser::class]
		));

		$html = $parser->toHtml("\"quoted\"\nnext !!loud <b>x</b>");

		$this->assertSame("<p>“quoted”<br />next LOUD &lt;b&gt;x&lt;/b&gt;</p>\n", $html);
	}

	public function testListenersCanExtendTheEnvironmentOnce(): void
	{
		$built = 0;

		$this->listeners->listen(MarkdownEnvironmentBuilding::class, static function (MarkdownEnvironmentBuilding $event) use (&$built): void {
			$built++;
			$event->environment->addInlineParser(new ShoutParser());
		});

		$parser = $this->parser();

		$this->assertSame("<p>HEY</p>\n", $parser->toHtml('!!hey'));
		$this->assertSame("<p>YOU</p>\n", $parser->toHtml('!!you'));
		$this->assertSame(1, $built);
	}

	public function testBadOptionsFailOnFirstUse(): void
	{
		$this->expectException(MarkdownException::class);

		$this->parser(new MarkdownConfig(options: ['max_nesting_level' => 'deep']))->toHtml('text');
	}

	public function testConfigAcceptsThe1xKeys(): void
	{
		$config = MarkdownConfig::fromArray([
			'config'         => ['html_input' => 'strip'],
			'extensions'     => [CommonMarkCoreExtension::class],
			'inline_parsers' => [ShoutParser::class]
		]);

		$this->assertSame(['html_input' => 'strip'], $config->options);
		$this->assertSame([ShoutParser::class], $config->inlineParsers);
		$this->assertEquals($config, MarkdownConfig::fromArray($config->toArray()));
		$this->assertSame(MarkdownConfig::DEFAULT_EXTENSIONS, MarkdownConfig::fromArray([])->extensions);
	}

	public function testRejectsClassesOfTheWrongKindOnFirstUse(): void
	{
		$parser = $this->parser(MarkdownConfig::fromArray(['extensions' => [ShoutParser::class]]));

		$this->expectException(MarkdownException::class);
		$this->expectExceptionMessage('MarkdownConfig "extensions"');

		$parser->toHtml('text');
	}

	public function testConfigOptionsMustBeAMap(): void
	{
		$this->expectException(InvalidConfig::class);

		MarkdownConfig::fromArray(['options' => ['a', 'b']]);
	}
}
