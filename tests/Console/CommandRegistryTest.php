<?php

/**
 * Command registry tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\BuiltInCommand;
use Blush\Console\CommandRegistrar;
use Blush\Console\CommandRegistry;
use Blush\Console\Commands\Serve;
use Blush\Console\ConsoleServiceProvider;
use Blush\Console\InvalidCommand;
use Blush\Tests\Fixtures\Console\CustomServe;
use Blush\Tests\Fixtures\Console\Greet;
use Blush\Tests\Fixtures\Console\NoAttribute;
use Blush\Tests\Fixtures\Console\ServeOverrideProvider;

#[CoversClass(CommandRegistry::class)]
#[CoversClass(CommandRegistrar::class)]
#[CoversClass(BuiltInCommand::class)]
#[CoversClass(ConsoleServiceProvider::class)]
final class CommandRegistryTest extends TestCase
{
	use BuildsConsole;

	public function testRegistersByNameAndAlias(): void
	{
		$registry = new CommandRegistry();
		$registry->register(Greet::class);

		$this->assertSame(Greet::class, $registry->get('greet'));
		$this->assertSame(Greet::class, $registry->get('hi'));
		$this->assertNull($registry->get('nope'));
		$this->assertSame(['greet', 'hi'], $registry->names());
		$this->assertCount(1, $registry);
		$this->assertSame(['greet' => Greet::class], iterator_to_array($registry));

		$registry->unregister('greet');

		$this->assertFalse($registry->has('hi'));
	}

	public function testRejectsInvalidCommands(): void
	{
		$this->expectException(InvalidCommand::class);

		new CommandRegistry()->register(NoAttribute::class);
	}

	public function testBuiltInsDoNotReplaceRegisteredCommands(): void
	{
		$registry = new CommandRegistry();
		$registry->register(CustomServe::class);

		new CommandRegistrar($registry)->register();

		$this->assertSame(CustomServe::class, $registry->get('serve'));
		$this->assertCount(count(BuiltInCommand::cases()), $registry);

		foreach (BuiltInCommand::cases() as $command) {
			$this->assertTrue($registry->has($command->value));
		}
	}

	public function testProviderRegistersTaggedCommandsBeforeBuiltIns(): void
	{
		$registry = $this->application(ServeOverrideProvider::class)->container()->make(CommandRegistry::class);

		$this->assertSame(Greet::class, $registry->get('greet'));
		$this->assertSame(CustomServe::class, $registry->get('serve'));
		$this->assertNotSame(Serve::class, $registry->get('serve'));
		$this->assertTrue($registry->has('cache:clear'));
	}
}
