<?php

/**
 * Component factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use TypeError;
use Blush\Container\Container;
use Blush\Container\ContainerException;
use Blush\View\ViewException;

/**
 * Builds class-backed components through the container. Props that match
 * constructor parameters are passed by name; string props are cast to a
 * parameter's `int`, `float`, `bool`, or backed enum type, since Markdown
 * directive attributes are always strings. A value the enum doesn't have
 * is dropped, so the parameter's default applies. Props the constructor
 * doesn't take are left for the template (as `$props`).
 */
final readonly class ComponentFactory
{
	public function __construct(private Container $container)
	{}

	/**
	 * Builds a component.
	 *
	 * @param  class-string<Component> $class
	 * @param  array<string, mixed>    $props
	 * @throws ViewException When the props don't fit the constructor.
	 */
	public function make(string $class, array $props): Component
	{
		$constructor = new ReflectionClass($class)->getConstructor();
		$parameters  = [];

		foreach ($constructor?->getParameters() ?? [] as $parameter) {
			$name = $parameter->getName();

			if (! array_key_exists($name, $props)) {
				continue;
			}

			$type  = $parameter->getType();
			$value = $props[$name];

			if ($type instanceof ReflectionNamedType && is_string($value)) {
				$value = self::cast($value, $type->getName());

				if ($value === null && $parameter->isDefaultValueAvailable()) {
					continue;
				}
			}

			$parameters[$name] = $value;
		}

		try {
			return $this->container->build($class, $parameters);
		} catch (ContainerException | TypeError $error) {
			throw new ViewException(sprintf('The "%s" component\'s props don\'t fit: %s', $class, $error->getMessage()), 0, $error);
		}
	}

	/**
	 * Casts a string to a scalar or backed enum type. An enum value that
	 * doesn't exist is `null`.
	 */
	private static function cast(string $value, string $type): mixed
	{
		return match (true) {
			$type === 'int'                      => is_numeric($value) ? (int) $value : $value,
			$type === 'float'                    => is_numeric($value) ? (float) $value : $value,
			$type === 'bool'                     => ! in_array(strtolower($value), ['', '0', 'false', 'no', 'off'], true),
			is_a($type, BackedEnum::class, true) => self::enumCase($type, $value),
			default                              => $value
		};
	}

	/**
	 * Returns a backed enum's case for a string, or `null`.
	 *
	 * @param class-string<BackedEnum> $enum
	 */
	private static function enumCase(string $enum, string $value): ?BackedEnum
	{
		return array_find($enum::cases(), static fn (BackedEnum $case): bool => (string) $case->value === $value);
	}
}
