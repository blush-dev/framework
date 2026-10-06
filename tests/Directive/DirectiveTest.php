<?php

/**
 * Directive base tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Directive\Callout;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveName;
use Blush\Directive\Inline\Kbd;
use Blush\Directive\Layout\Figure;
use Blush\Directive\Variant;
use Blush\Tests\Fixtures\Directive\Box;
use Blush\Tests\Fixtures\Directive\Stamp;
use Blush\View\Renderable;

#[CoversClass(Directive::class)]
#[CoversClass(Renderable::class)]
#[CoversClass(Callout::class)]
#[CoversClass(Figure::class)]
#[CoversClass(Kbd::class)]
final class DirectiveTest extends TestCase
{
	public function testAttributesPrintTheBlockModifiersAndProps(): void
	{
		$callout = new Callout('Heads up');
		$callout->attach(new DirectiveName('blush', 'callout'), ['class' => ' wide ', 'id' => 'note-1', 'variant' => 'warning'], variant: new Variant('warning', 'blush'));

		$this->assertSame('directive-callout directive-callout--warning wide', $callout->classes());
		$this->assertSame('class="directive-callout directive-callout--warning wide" id="note-1" role="note"', $callout->attributes());

		// Extra attributes add to them, and a class joins the others.
		$this->assertSame(
			'class="directive-callout directive-callout--warning wide open" id="note-1" role="region" data-open',
			$callout->attributes(['class' => 'open', 'role' => 'region', 'data-open' => true, 'hidden' => false, 'title' => null])
		);
		$this->assertSame('warning', $callout->prop('variant'));
		$this->assertSame('warning', $callout->variant);
		$this->assertTrue($callout->isVariant('warning'));
		$this->assertSame('fallback', $callout->prop('missing', 'fallback'));
	}

	public function testAttributesAreEscaped(): void
	{
		$directive = new Box();
		$directive->attach(new DirectiveName('app', 'box'), ['class' => '"><script>']);

		$this->assertSame('class="directive-box &quot;&gt;&lt;script&gt;" title="a &amp; b"', $directive->attributes(['title' => 'a & b']));
		$this->assertSame('data-n="3" hidden', Directive::html(['data-n' => 3, 'hidden' => true, 'empty' => '']));

		// URL attributes are escaped as URLs, and an unsafe one is left out.
		$this->assertSame('src="https://example.com/a?b=1&amp;c=2" title="x"', Directive::html(['src' => 'https://example.com/a?b=1&c=2', 'title' => 'x']));
		$this->assertSame('title="javascript:alert(1)"', Directive::html(['href' => 'javascript:alert(1)', 'title' => 'javascript:alert(1)']));
	}

	public function testUnattachedDirectivesNameTheirBlockFromTheirClass(): void
	{
		$this->assertSame('directive-stamp', new Stamp()->block());
		$this->assertSame('class="directive-callout" role="note"', new Callout()->attributes());
		$this->assertSame('default', new Callout()->variant, 'Default adds no modifier.');
	}

	public function testCalloutsAreTitledByTheLabelOrTitle(): void
	{
		$this->assertSame('Label &amp; more', new Callout(label: 'Label & more', title: 'Title')->heading());
		$this->assertSame('Title', new Callout(label: ' ', title: 'Title')->heading());
		$this->assertSame('', new Callout()->heading());
	}

	public function testContentComesFromTheDirective(): void
	{
		$directive = new Box();

		$this->assertSame('', $directive->content());

		$directive->attach(new DirectiveName('app', 'card'), [], '<p>Body</p>');

		$this->assertSame('<p>Body</p>', $directive->content());
	}

	public function testRoleNamedContentFallsBackToTheLabel(): void
	{
		// A figure's caption is its label, escaped; its content is what it
		// wraps, and without any, it doesn't render.
		$figure = new Figure('Tom & Jerry');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertFalse($figure->shouldRender());

		$figure->attach(new DirectiveName('blush', 'figure'), [], '<table></table>');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertTrue($figure->shouldRender());

		$this->assertSame('', new Figure()->caption());
		$this->assertSame('Ctrl', new Kbd('Ctrl')->text());
		$this->assertFalse(new Kbd('Ctrl')->isCombination());
		$this->assertTrue(new Kbd('Ctrl+S')->isCombination());
	}
}
