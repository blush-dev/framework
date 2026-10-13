<?php

/**
 * Block base tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Block;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Block\Callout;
use Blush\Block\Block;
use Blush\Block\BlockName;
use Blush\Block\Inline\Kbd;
use Blush\Block\Layout\Figure;
use Blush\Block\Variant;
use Blush\Tests\Fixtures\Block\Box;
use Blush\Tests\Fixtures\Block\Stamp;
use Blush\View\Renderable;

#[CoversClass(Block::class)]
#[CoversClass(Renderable::class)]
#[CoversClass(Callout::class)]
#[CoversClass(Figure::class)]
#[CoversClass(Kbd::class)]
final class BlockTest extends TestCase
{
	public function testAttributesPrintTheBlockModifiersAndProps(): void
	{
		$callout = new Callout('Heads up');
		$callout->attach(new BlockName('core', 'callout'), ['class' => ' wide ', 'id' => 'note-1', 'variant' => 'warning'], variant: new Variant('warning', 'core'));

		$this->assertSame('block-callout block-callout--warning wide', $callout->classes());
		$this->assertSame('class="block-callout block-callout--warning wide" id="note-1" role="note"', $callout->attributes());

		// Extra attributes add to them, and a class joins the others.
		$this->assertSame(
			'class="block-callout block-callout--warning wide open" id="note-1" role="region" data-open',
			$callout->attributes(['class' => 'open', 'role' => 'region', 'data-open' => true, 'hidden' => false, 'title' => null])
		);
		$this->assertSame('warning', $callout->prop('variant'));
		$this->assertSame('warning', $callout->variant);
		$this->assertTrue($callout->isVariant('warning'));
		$this->assertSame('fallback', $callout->prop('missing', 'fallback'));
	}

	public function testAttributesAreEscaped(): void
	{
		$block = new Box();
		$block->attach(new BlockName('app', 'box'), ['class' => '"><script>']);

		$this->assertSame('class="block-box &quot;&gt;&lt;script&gt;" title="a &amp; b"', $block->attributes(['title' => 'a & b']));
		$this->assertSame('data-n="3" hidden', Block::html(['data-n' => 3, 'hidden' => true, 'empty' => '']));

		// URL attributes are escaped as URLs, and an unsafe one is left out.
		$this->assertSame('src="https://example.com/a?b=1&amp;c=2" title="x"', Block::html(['src' => 'https://example.com/a?b=1&c=2', 'title' => 'x']));
		$this->assertSame('title="javascript:alert(1)"', Block::html(['href' => 'javascript:alert(1)', 'title' => 'javascript:alert(1)']));
	}

	public function testUnattachedBlocksNameTheirBlockFromTheirClass(): void
	{
		$this->assertSame('block-stamp', (string) new Stamp()->blockClass());
		$this->assertSame('class="block-callout" role="note"', new Callout()->attributes());
		$this->assertSame('default', new Callout()->variant, 'Default adds no modifier.');
	}

	public function testCalloutsAreTitledByTheLabelOrTitle(): void
	{
		$this->assertSame('Label &amp; more', new Callout(label: 'Label & more', title: 'Title')->heading());
		$this->assertSame('Title', new Callout(label: ' ', title: 'Title')->heading());
		$this->assertSame('', new Callout()->heading());
	}

	public function testContentComesFromTheBlock(): void
	{
		$block = new Box();

		$this->assertSame('', $block->content());

		$block->attach(new BlockName('app', 'card'), [], '<p>Body</p>');

		$this->assertSame('<p>Body</p>', $block->content());
	}

	public function testRoleNamedContentFallsBackToTheLabel(): void
	{
		// A figure's caption is its label, escaped; its content is what it
		// wraps, and without any, it doesn't render.
		$figure = new Figure('Tom & Jerry');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertFalse($figure->shouldRender());

		$figure->attach(new BlockName('core', 'figure'), [], '<table></table>');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertTrue($figure->shouldRender());

		$this->assertSame('', new Figure()->caption());
		$this->assertSame('Ctrl', new Kbd('Ctrl')->text());
		$this->assertFalse(new Kbd('Ctrl')->isCombination());
		$this->assertTrue(new Kbd('Ctrl+S')->isCombination());
	}
}
