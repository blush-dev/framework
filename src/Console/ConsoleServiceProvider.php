<?php

/**
 * Console service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Override;
use Blush\Container\Container;
use Blush\Core\ServiceProvider;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageResolver;

/**
 * Binds the command registry and the console. The registry is built on first
 * use: commands tagged with `CommandRegistry::TAG` register first, then the
 * storage drivers' own (`Storage::commands()`), then the built-ins fill
 * any names left free. Nothing here costs anything on a web
 * request.
 */
final class ConsoleServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Console::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		ProcessRunner::class => SystemProcessRunner::class
	];

	/**
	 * Binds the registry, and registers each built-in command class so
	 * its plan is compiled (D-066).
	 */
	#[Override]
	public function register(): void
	{
		foreach (BuiltInCommand::cases() as $command) {
			$this->container->transientIf($command->className());
		}

		$this->container->singleton(
			CommandRegistry::class,
			static function (Container $container): CommandRegistry {
				$registry = new CommandRegistry();

				foreach ($container->taggedAbstracts(CommandRegistry::TAG) as $class) {
					$registry->register($class);
				}

				// The storage drivers' own tools, for the areas they keep (D-654).
				foreach (StorageArea::cases() as $area) {
					foreach ($container->make(StorageResolver::class)->commands($area) as $class) {
						$registry->registerIf($class);
					}
				}

				new CommandRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
