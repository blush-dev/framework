<?php

/**
 * Component base tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Component;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Component\Callout;
use Blush\Component\Component;
use Blush\Component\ComponentName;
use Blush\Component\Inline\Kbd;
use Blush\Component\Layout\Figure;
use Blush\Component\Slots;
use Blush\Component\TemplateComponent;
use Blush\Component\Variant;
use Blush\Tests\Fixtures\Component\Card;

#[CoversClass(Component::class)]
#[CoversClass(TemplateComponent::class)]
#[CoversClass(Callout::class)]
#[CoversClass(Figure::class)]
#[CoversClass(Kbd::class)]
final class ComponentTest extends TestCase
{
	public function testAttributesPrintTheBlockModifiersAndProps(): void
	{
		$callout = new Callout('Heads up');
		$callout->attach(new ComponentName('blush', 'callout'), ['class' => ' wide ', 'id' => 'note-1', 'variant' => 'warning'], variant: new Variant('warning', 'blush'));

		$this->assertSame('component-callout component-callout--warning wide', $callout->classes());
		$this->assertSame('class="component-callout component-callout--warning wide" id="note-1" role="note"', $callout->attributes());

		// Extra attributes add to them, and a class joins the others.
		$this->assertSame(
			'class="component-callout component-callout--warning wide open" id="note-1" role="region" data-open',
			$callout->attributes(['class' => 'open', 'role' => 'region', 'data-open' => true, 'hidden' => false, 'title' => null])
		);
		$this->assertSame('warning', $callout->prop('variant'));
		$this->assertSame('warning', $callout->variant);
		$this->assertTrue($callout->isVariant('warning'));
		$this->assertSame('fallback', $callout->prop('missing', 'fallback'));
	}

	public function testAttributesAreEscaped(): void
	{
		$component = new TemplateComponent();
		$component->attach(new ComponentName('app', 'box'), ['class' => '"><script>']);

		$this->assertSame('class="component-box &quot;&gt;&lt;script&gt;" title="a &amp; b"', $component->attributes(['title' => 'a & b']));
		$this->assertSame('data-n="3" hidden', Component::html(['data-n' => 3, 'hidden' => true, 'empty' => '']));

		// URL attributes are escaped as URLs, and an unsafe one is left out.
		$this->assertSame('src="https://example.com/a?b=1&amp;c=2" title="x"', Component::html(['src' => 'https://example.com/a?b=1&c=2', 'title' => 'x']));
		$this->assertSame('title="javascript:alert(1)"', Component::html(['href' => 'javascript:alert(1)', 'title' => 'javascript:alert(1)']));
	}

	public function testUnattachedComponentsNameTheirBlockFromTheirClass(): void
	{
		$this->assertSame('component-card', new Card()->block());
		$this->assertSame('class="component-callout" role="note"', new Callout()->attributes());
		$this->assertSame('default', new Callout()->variant, 'Default adds no modifier.');
	}

	public function testCalloutsAreTitledByTheLabelOrTitle(): void
	{
		$this->assertSame('Label &amp; more', new Callout(label: 'Label & more', title: 'Title')->heading());
		$this->assertSame('Title', new Callout(label: ' ', title: 'Title')->heading());
		$this->assertSame('', new Callout()->heading());
	}

	public function testContentAndSlotsComeFromTheComponent(): void
	{
		$component = new TemplateComponent();

		$this->assertSame('', $component->content());
		$this->assertSame('', $component->slots->footer);
		$this->assertFalse($component->slots->has('footer'));

		$component->attach(new ComponentName('app', 'card'), [], '<p>Body</p>', new Slots(['footer' => '<small>Foot</small>']));

		$this->assertSame('<p>Body</p>', $component->content());
		$this->assertSame('<small>Foot</small>', $component->slots->footer);
		$this->assertTrue($component->slots->has('footer'));
		$this->assertFalse($component->slots->has('header'));
	}

	public function testRoleNamedContentFallsBackToTheLabel(): void
	{
		// A figure's caption is its label, escaped; its content is what it
		// wraps, and without any, it doesn't render.
		$figure = new Figure('Tom & Jerry');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertFalse($figure->shouldRender());

		$figure->attach(new ComponentName('blush', 'figure'), [], '<table></table>');
		$this->assertSame('Tom &amp; Jerry', $figure->caption());
		$this->assertTrue($figure->shouldRender());

		$this->assertSame('', new Figure()->caption());
		$this->assertSame('Ctrl', new Kbd('Ctrl')->text());
		$this->assertFalse(new Kbd('Ctrl')->isCombination());
		$this->assertTrue(new Kbd('Ctrl+S')->isCombination());
	}
}
