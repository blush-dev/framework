<?php

/**
 * Admin field controls catalog tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\FieldControlsController;
use Blush\Field\ControlType;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldType;
use Blush\Tests\Fixtures\Content\ColorField;

#[CoversClass(FieldControlsController::class)]
final class AdminFieldControlsTest extends TestCase
{
	use BootsAdmin;

	public function testDescribesEveryControlAndFieldType(): void
	{
		$this->boot();
		$this->app->container()->make(FieldRegistry::class)->register('color', ColorField::class);
		$this->login();

		$catalog  = self::json($this->send('GET', '/fields/controls'));
		$controls = array_column(is_array($catalog['controls'] ?? null) ? $catalog['controls'] : [], null, 'value');
		$types    = array_column(is_array($catalog['types'] ?? null) ? $catalog['types'] : [], null, 'type');

		$this->assertSame(array_column(ControlType::cases(), 'value'), array_keys($controls), 'In the picker\'s order.');
		$this->assertSame([...array_column(FieldType::cases(), 'value'), 'color'], array_keys($types));

		$this->assertSame('Radio Buttons', self::key($controls, 'radios', 'label'));
		$this->assertSame(['value' => 'choices', 'label' => 'Choices'], self::key($controls, 'radios', 'section'));
		$this->assertSame('enum', self::key($controls, 'radios', 'type'));
		$this->assertSame(['format' => 'url'], (array) self::key($controls, 'link', 'implies'));
		$this->assertSame(['placeholder'], array_keys((array) self::key($controls, 'text', 'options')));
		$this->assertNull(self::key($controls, 'code', 'section'), 'The picker doesn\'t offer it.');
		$this->assertNull(self::key($controls, 'code', 'type'), 'It edits several types.');

		$this->assertSame('Formatted text', self::key($types, 'markdown', 'label'));
		$this->assertSame([['value' => 'select', 'label' => 'Select Dropdown'], ['value' => 'radios', 'label' => 'Radio Buttons']], self::key($types, 'enum', 'controls'));
		$this->assertSame(['text', 'textarea', 'code'], array_column((array) self::key($types, 'text', 'controls'), 'value'), 'Without the ones that imply a format.');
		$this->assertSame(['integer', 'min', 'max', 'step'], array_keys((array) self::key($types, 'number', 'options')), 'Without `default`.');

		$this->assertSame('color', self::key($types, 'color', 'label'), 'A type without a label is named by its key.');
		$this->assertSame([], self::key($types, 'color', 'controls'));
	}

	/**
	 * Reads an item's key from the catalog.
	 *
	 * @param array<mixed> $items
	 */
	private static function key(array $items, string $item, string $key): mixed
	{
		$described = $items[$item] ?? null;

		return is_array($described) ? $described[$key] ?? null : null;
	}

	public function testNeedsAnAccount(): void
	{
		$this->boot();

		$this->assertSame(401, $this->send('GET', '/fields/controls')->getStatusCode());
	}
}
