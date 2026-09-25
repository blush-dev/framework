<?php

/**
 * Discovers listeners from attributes trait.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Event\Listener;

use Generator;
use Override;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use Blush\Event\Listener\Attributes\ListenerAttribute;

/**
 * Implements `ListenerSubscriber::subscribeTo()` by reading listener
 * attributes — `Listen`, `ListenTo`, `ListenOnce`, `ListenToOnce`,
 * `ListenUntil`, `ListenToUntil` — off the class's own methods, instead of
 * writing those registry calls by hand. Each attribute already knows which
 * `Listenable` method it stands for, so this trait only has to find them and
 * hand each one the registry and its method's callable; it never branches on
 * which attribute it found.
 *
 * The discovered attributes are cached per class, since they are fixed at
 * class definition time and reflecting the same class twice would only
 * repeat work.
 */
trait DiscoversListeners
{
	/**
	 * Caches each class's discovered attributes, keyed by class name and
	 * then by method name, so repeated calls — and repeated instantiation
	 * — only reflect once per class.
	 *
	 * @var array<class-string, array<string, list<ListenerAttribute>>>
	 */
	private static array $discoveredListeners = [];

	/**
	 * @inheritDoc
	 * @throws ReflectionException
	 */
	#[Override]
	public function subscribeTo(Listenable $registry): void
	{
		foreach ($this->discoverListeners() as $method => $attributes) {
			$callable = $this->$method(...);

			foreach ($attributes as $attribute) {
				$attribute->registerOn($registry, $callable);
			}
		}
	}

	/**
	 * @return array<string, list<ListenerAttribute>>
	 * @throws ReflectionException
	 */
	private static function discoverListeners(): array
	{
		return self::$discoveredListeners[static::class] ??= iterator_to_array(
			self::reflectListeners(static::class)
		);
	}

	/**
	 * Yields each method's `ListenerAttribute` instances, keyed by method
	 * name, skipping methods that carry none. Matches by `IS_INSTANCEOF`
	 * rather than a specific class, so any current or future
	 * `ListenerAttribute` implementation is found without this trait
	 * needing to know its name.
	 *
	 * @param  class-string $class
	 * @return Generator<string, list<ListenerAttribute>>
	 * @throws ReflectionException
	 */
	private static function reflectListeners(string $class): Generator
	{
		foreach ((new ReflectionClass($class))->getMethods() as $method) {
			$attributes = $method->getAttributes(
				ListenerAttribute::class,
				ReflectionAttribute::IS_INSTANCEOF
			);

			if ([] === $attributes) {
				continue;
			}

			yield $method->getName() => array_map(
				static fn (ReflectionAttribute $attribute): ListenerAttribute
				=> $attribute->newInstance(),
				$attributes
			);
		}
	}
}
