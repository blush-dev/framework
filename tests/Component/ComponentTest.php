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
use Blush\Component\Component;
use Blush\Component\ComponentName;
use Blush\Component\Slots;
use Blush\Component\TemplateComponent;
use Blush\Tests\Fixtures\Component\Card;
use Blush\View\Renderable;

#[CoversClass(Component::class)]
#[CoversClass(ComponentName::class)]
#[CoversClass(Renderable::class)]
#[CoversClass(Slots::class)]
#[CoversClass(TemplateComponent::class)]
final class ComponentTest extends TestCase
{
	public function testNamesAlwaysHaveANamespace(): void
	{
		$this->assertSame('acme/card', (string) ComponentName::parse('acme/card'));
		$this->assertNull(ComponentName::parse('card'), 'There are no core components (D-532).');
		$this->assertNull(ComponentName::parse('acme/card/more'));
		$this->assertNull(ComponentName::parse('../x'));
		$this->assertNull(ComponentName::parse('acme/'));

		$this->assertSame('components/acme-card', new ComponentName('acme', 'card')->view());

		// A file name takes the longest namespace that fits.
		$this->assertSame('my-theme/card', (string) ComponentName::fromFileName('my-theme-card', ['my', 'my-theme']));
		$this->assertSame('my/card', (string) ComponentName::fromFileName('my-card', ['my', 'my-theme']));
		$this->assertNull(ComponentName::fromFileName('card', ['my']));
	}

	public function testAttributesPrintTheComponentBlock(): void
	{
		$component = new TemplateComponent();
		$component->attach(new ComponentName('app', 'box'), ['class' => ' wide "', 'id' => 'b']);

		$this->assertSame('component-box wide "', $component->classes());
		$this->assertSame('class="component-box wide &quot;" id="b" data-open', $component->attributes(['data-open' => true]));
		$this->assertSame('component-card', new Card()->block(), 'An unattached one names its block from its class.');
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
}
