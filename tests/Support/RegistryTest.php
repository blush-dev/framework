<?php

/**
 * Registry tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Support\RegistrationException;
use Blush\Support\Registry;
use Blush\Tests\Fixtures\Support\Circle;
use Blush\Tests\Fixtures\Support\Decorated;
use Blush\Tests\Fixtures\Support\NoContractRegistry;
use Blush\Tests\Fixtures\Support\Polygon;
use Blush\Tests\Fixtures\Support\Shape;
use Blush\Tests\Fixtures\Support\ShapeRegistry;

#[CoversClass(Registry::class)]
#[CoversClass(RegistrationException::class)]
final class RegistryTest extends TestCase
{
	public function testRegistersAndReadsClasses(): void
	{
		$registry = new ShapeRegistry(['circle' => Circle::class]);

		$this->assertTrue($registry->isRegistered('circle'));
		$this->assertSame(Circle::class, $registry->get('circle'));
		$this->assertNull($registry->get('missing'));
		$this->assertSame(['circle' => Circle::class], $registry->all());
		$this->assertSame(['circle' => Circle::class], iterator_to_array($registry));
		$this->assertCount(1, $registry);

		$registry->unregister('circle');

		$this->assertFalse($registry->isRegistered('circle'));
	}

	public function testRegisterIfKeepsTheExistingClass(): void
	{
		$registry = new ShapeRegistry(['shape' => Circle::class]);

		$registry->registerIf('shape', Circle::class);
		$registry->registerIf('round', Circle::class);

		$this->assertSame(Circle::class, $registry->get('shape'));
		$this->assertSame(Circle::class, $registry->get('round'));
	}

	public function testRejectsClassesOutsideTheContract(): void
	{
		$this->expectException(RegistrationException::class);

		$registry = new ShapeRegistry();

		// @phpstan-ignore argument.type (verifies the runtime guard)
		$registry->register('other', Decorated::class);
	}

	public function testRejectsTheContractItself(): void
	{
		$this->expectException(RegistrationException::class);

		new ShapeRegistry(['shape' => Shape::class]);
	}

	public function testRejectsUninstantiableClasses(): void
	{
		$this->expectException(RegistrationException::class);

		new ShapeRegistry(['polygon' => Polygon::class]);
	}

	public function testRequiresAContract(): void
	{
		$this->expectException(RegistrationException::class);

		new NoContractRegistry(['decorated' => Decorated::class]);
	}
}
