<?php

/**
 * Markdown directive tests.
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
use Blush\Event\EventDispatcher;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Markdown\CommonMark\Directive\ContainerDirective;
use Blush\Markdown\CommonMark\Directive\ContainerDirectiveParser;
use Blush\Markdown\CommonMark\Directive\ContainerDirectiveStartParser;
use Blush\Markdown\CommonMark\Directive\DirectiveAttributes;
use Blush\Markdown\CommonMark\Directive\DirectiveExtension;
use Blush\Markdown\CommonMark\Directive\DirectiveNodeRenderer;
use Blush\Markdown\CommonMark\Directive\InlineDirective;
use Blush\Markdown\CommonMark\Directive\InlineDirectiveParser;
use Blush\Markdown\CommonMark\Directive\LeafDirective;
use Blush\Markdown\CommonMark\Directive\LeafDirectiveParser;
use Blush\Markdown\CommonMark\Directive\LeafDirectiveStartParser;
use Blush\Markdown\CommonMarkParser;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveKind;
use Blush\Markdown\MarkdownConfig;
use Blush\Tests\Fixtures\Markdown\EchoDirectives;

#[CoversClass(DirectiveExtension::class)]
#[CoversClass(DirectiveAttributes::class)]
#[CoversClass(DirectiveNodeRenderer::class)]
#[CoversClass(ContainerDirective::class)]
#[CoversClass(ContainerDirectiveParser::class)]
#[CoversClass(ContainerDirectiveStartParser::class)]
#[CoversClass(LeafDirective::class)]
#[CoversClass(LeafDirectiveParser::class)]
#[CoversClass(LeafDirectiveStartParser::class)]
#[CoversClass(InlineDirective::class)]
#[CoversClass(InlineDirectiveParser::class)]
#[CoversClass(Directive::class)]
#[CoversClass(DirectiveKind::class)]
final class DirectiveTest extends TestCase
{
	private EchoDirectives $directives;

	private function parser(MarkdownConfig $config = new MarkdownConfig(), bool $renderer = true): CommonMarkParser
	{
		$this->directives = new EchoDirectives();

		return new CommonMarkParser($config, new EventDispatcher(new ListenerRegistry()), directives: $renderer ? $this->directives : null);
	}

	public function testParsesAttributes(): void
	{
		$this->assertSame(
			['tone' => 'info', 'title' => 'Two words', 'alt' => 'it\'s', 'class' => 'a b', 'id' => 'x', 'flag' => 'true', 'data-n' => '3'],
			DirectiveAttributes::parse('tone=info title="Two words" alt="it\'s" .a #x .b flag data-n=3')
		);
		$this->assertSame(['single' => 'quoted'], DirectiveAttributes::parse("single='quoted' ="));
		$this->assertSame([], DirectiveAttributes::parse(''));
	}

	public function testRendersTheThreeForms(): void
	{
		$html = $this->parser()->toHtml(<<<'MD'
			Before
			:::callout[Heads up]{tone=warning}
			Some *text* and :badge[new]{tone=info}.
			:::

			::embed[A caption]{url="https://youtu.be/abc123"}
			MD);

		$this->assertSame(
			"<p>Before</p>\n<container-callout><p>Some <em>text</em> and <inline-badge>new</inline-badge>.</p></container-callout>\n<leaf-embed>A caption</leaf-embed>\n",
			$html
		);

		[$badge, $callout, $embed] = $this->directives->seen;

		$this->assertSame(DirectiveKind::Inline, $badge->kind);
		$this->assertSame(['tone' => 'info'], $badge->attributes);
		$this->assertSame('new', $badge->label);
		$this->assertSame(DirectiveKind::Container, $callout->kind);
		$this->assertSame('Heads up', $callout->label);
		$this->assertSame(['tone' => 'warning'], $callout->attributes);
		$this->assertSame(DirectiveKind::Leaf, $embed->kind);
		$this->assertSame(['url' => 'https://youtu.be/abc123'], $embed->attributes);
	}

	public function testNestsContainersByFenceLength(): void
	{
		$html = $this->parser()->toHtml("::::outer\n:::inner\nDeep\n:::\nStill outer\n::::\nAfter");

		$this->assertSame("<container-outer><container-inner><p>Deep</p></container-inner>\n<p>Still outer</p></container-outer>\n<p>After</p>\n", $html);
	}

	public function testNestedContainersCanAllUseThreeColons(): void
	{
		$html = $this->parser()->toHtml(":::stack\n:::row\n:::group\nDeep\n:::\nIn row\n:::\nIn stack\n:::\nAfter");

		$this->assertSame("<container-stack><container-row><container-group><p>Deep</p></container-group>\n<p>In row</p></container-row>\n<p>In stack</p></container-stack>\n<p>After</p>\n", $html);
	}

	public function testALongFenceSkipsInnerContainersTooLongForIt(): void
	{
		// The outer container is the innermost one `:::` is long enough
		// for, so it closes, and the inner one with it.
		$html = $this->parser()->toHtml(":::outer\n::::inner\nDeep\n:::\nAfter");

		$this->assertSame("<container-outer><container-inner><p>Deep</p></container-inner></container-outer>\n<p>After</p>\n", $html);
	}

	public function testUnclosedContainersRunToTheEnd(): void
	{
		$this->assertSame("<container-note><p>Open</p></container-note>\n", $this->parser()->toHtml(":::note\nOpen"));
	}

	public function testUnknownDirectivesRenderAsPlainContent(): void
	{
		$html = $this->parser()->toHtml(":::unknown\nKept *inside*\n:::\n\n::unknown[Label only]\n\n::unknown\n\nText :unknown[inline]{a=1} here.");

		$this->assertSame("<p>Kept <em>inside</em></p>\n<p>Label only</p>\n\n<p>Text inline here.</p>\n", $html);
	}

	public function testWithoutARendererDirectivesArePlainContent(): void
	{
		// Labels are plain text, so markup in them is escaped.
		$this->assertSame("<p>Tip</p>\n<p>Hi &lt;b&gt;x&lt;/b&gt;</p>\n", $this->parser(renderer: false)->toHtml(":::callout\nTip\n:::\n\nHi :badge[<b>x</b>]"));
	}

	public function testLooksAlikesStayText(): void
	{
		$html = $this->parser()->toHtml("At 10:30[1] see https://x.com/a:b[c] and a::b[c]\n\n:: not[a] directive\n\n::: also not");

		$this->assertSame([], $this->directives->seen);
		$this->assertStringContainsString('At 10:30[1] see', $html);
		$this->assertStringContainsString('<p>::: also not</p>', $html);
	}

	public function testDirectivesCanBeTurnedOff(): void
	{
		$this->assertSame("<p>:::callout\nTip\n:::</p>\n", $this->parser(new MarkdownConfig(directives: false))->toHtml(":::callout\nTip\n:::"));
		$this->assertFalse(MarkdownConfig::fromArray(['directives' => false])->directives);
		$this->assertTrue(new MarkdownConfig()->toArray()['directives']);
	}
}
