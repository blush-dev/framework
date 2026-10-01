<?php

/**
 * Admin field types API tests.
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
use Blush\Admin\FieldTypesController;
use Blush\Field\Control;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldType;
use Blush\Tests\Fixtures\Content\ColorField;

#[CoversClass(FieldTypesController::class)]
final class AdminFieldTypesTest extends TestCase
{
	use BootsAdmin;

	public function testDescribesEveryFieldTypeAndControl(): void
	{
		$this->boot();
		$this->app->container()->make(FieldRegistry::class)->register('color', ColorField::class);
		$this->login();

		$catalog = self::json($this->send('GET', '/fields/types'));
		$types   = array_column(is_array($catalog['types'] ?? null) ? $catalog['types'] : [], null, 'type');

		$this->assertSame([...array_column(FieldType::cases(), 'value'), 'color'], array_keys($types));
		$this->assertSame(array_column(Control::cases(), 'value'), array_column(is_array($catalog['controls'] ?? null) ? $catalog['controls'] : [], 'value'));

		$this->assertSame('Formatted text', self::key($types, 'markdown', 'label'));
		$this->assertSame([['value' => 'select', 'label' => 'Menu'], ['value' => 'radios', 'label' => 'Radio buttons']], self::key($types, 'enum', 'controls'));
		$this->assertSame(['integer', 'min', 'max'], array_keys((array) self::key($types, 'number', 'options')), 'Without `default`.');

		$this->assertSame('color', self::key($types, 'color', 'label'), 'A type without a label is named by its key.');
		$this->assertSame([['value' => 'readonly', 'label' => 'Read-only']], self::key($types, 'color', 'controls'));
	}

	/**
	 * Reads a type's key from the catalog.
	 *
	 * @param array<mixed> $types
	 */
	private static function key(array $types, string $type, string $key): mixed
	{
		$described = $types[$type] ?? null;

		return is_array($described) ? $described[$key] ?? null : null;
	}

	public function testNeedsAnAccount(): void
	{
		$this->boot();

		$this->assertSame(401, $this->send('GET', '/fields/types')->getStatusCode());
	}
}
